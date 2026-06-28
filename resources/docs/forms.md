# Forms

This section covers:

- How to secure your forms against CSRF attacks
- How to submit non-conventional PUT, PATCH, & DELETE requests
- How to send a test AJAX request from the CLI with a CSRF token
- How to make an API request
- Example of creating a form with the DGZ_Form class
    - Create a select form field
- Jet forms, Dorguzen's re-usable forms
    - Introduction
    - Purpose of JetForms
    - JetForms Architecture Overview
    - Lifecycle of a DGZ Jet Form
        - i) Create the Jet form
        - ii) Register the Jet form
        - iii) Display the Jet form
    - Using a jet form in a controller
    - Passing more than one Jet form to a view
    - How Jet forms are validated
    - Adding extra hidden fields on the fly
    - Prepopulating form with sample data
    - How to make non POST or GET form requests
    - Optional business logic

This demonstrates how your programming language allows you to manage web forms and user-submitted input. For validation of form submissions and all types of requests, see the Security section for documentation on using the DGZ_Validator class.

---

## How to secure your forms against CSRF attacks

To secure your forms with Cross-Site Request Forgery (CSRF) protection, all you have to do is add a hidden field. The name has to be `_csrf_token` and its value must be a Dorguzen-generated csrf token, and DGZ has a global function to do this for you easily, the function is `getCsrfToken()`. Add the hidden field to your form like this:

```html
<input type="hidden" name="_csrf_token" value="<?=getCsrfToken()?>">
```

Alternatively, if you are displaying the form from your controller method without generating a template (view) file, you can reference that same function on the controller's request property like so:

```php
$token = $this->request->getCsrfToken();
```

Here is an example:

```php
class TestController extends DGZ_Controller
{
    ...

    public function form()
    {
        $token = $this->request->getCsrfToken();
        $app = $this->config->getFileRootPath();
        echo "
            <form method='POST' action='{$app}test/submit'>
                <input type='hidden' name='_csrf_token' value='{$token}'>
                <input type='text' name='message' placeholder='Type something'>
                <button type='submit'>Send</button>
            </form>
        ";
    }

    public function submit()
    {
        echo "✅ CSRF validation passed — request accepted!";
    }
}
```

But because the helper function `getCsrfToken()` exists, you can just use it directly like this:

```php
$token = getCsrfToken();
```

or just call it inline, within the value attribute of the form's `_csrf_token` field like this:

```html
<input type="hidden" name="_csrf_token" value="<?=getCsrfToken()?>">
```

With that in place, you do not have to do anything else, as Dorguzen will validate the token for you on the other end when it's submitted. The submission will error if the form is missing the token field, and the clear message will be logged to inform you the application owner of what file and line the error occurred at.

If you are using Dorguzen's DGZ_Form class, to create your form—which is the recommended way to create forms in the DGZ framework, you do not have to worry about adding that csrf input field in the form. All that is handled for you behind the scenes so you can just focus on creating your form input fields and handling the submission in your controllers.

In `configs/Config.php`, there is an array `'csrf_except'` in which you can define certain request paths that you want the csrf validation to be ignored. So, decide what routes you want to make CSRF validation exemptions for and add them to that array.

```php
'csrf_except' => [
    '/api/',
]
```

Currently, as you can see CSRF will not be applied to any route that matches 'api' in the browser. For all other routes, Dorguzen is currently applying CSRF validation for the following request methods:

```
POST,
PATCH,
PUT,
DELETE
```

Why Dorguzen is exempting API requests is because APIs already have their own validation going on, and that is by using a JSON Web Tokens (JWT), which should suffice.

---

## How to submit non-conventional PUT, PATCH, & DELETE requests

While backend systems running in languages like PHP understand and can handle PUT, PATCH, and DELETE requests, standard HTML forms (`<form>`) only support GET and POST request methods. To simulate PUT, PATCH, or DELETE from a form, you'll need this small trick to deceive the browser into thinking you are submitting a POST request, while you actually use a hidden field with some value in it which you can read on the backend after submitting the form, to determine if the intention of the request was to make a PUT, PATCH, or DELETE request.

```html
<form action="/users/1" method="POST">
  <input type="hidden" name="_csrf_token" value="<?=getCsrfToken()?>">
  <input type="hidden" name="_method" value="DELETE">
  <button type="submit">Delete</button>
</form>
```

On the server side, when the form is submitted, you can detect request methods in PHP, using the super global `$_REQUEST_METHOD`. Here is an example of detecting and handling a request as per the method name submitted via that form's hidden field. This method will correctly detect and handle all HTTP methods, including:

```
GET
POST
PUT
PATCH
DELETE
```

and even others like OPTIONS or HEAD.

```php
public function method(): string
{
  $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

  // Allow method override (for forms)
  if ($method === 'POST' && isset($this->post['_method']))
  {
    $method = strtoupper($this->post['_method']);
  }

  return $method;
}
```

This is exactly how form request methods are being detected by Dorguzen in the DGZ_Request object. So, to detect the method used after a request has been submitted, do it in your controller like so:

```php
$method = $this->request->method();
```

---

## How to send a test AJAX request from the CLI with a CSRF token

You will find that when you send a test AJAX request from the command line to your Dorguzen application, if it's one of those requests method types for which CSRF validation is enforced, you will get an "invalid or missing token" error. This will happen even if you sent through a CSRF token you got from `$_SESSION['csrf_token']`, or generated by calling `getCsrfToken()` like so:

```bash
curl -X POST http://localhost:8888/camerooncom/test/submit \
-H "X-CSRF-TOKEN: 3838f4c71de59ac1c16b34bc554c6c886484dfae4e5f8322cf092e30794697dd" \
-H "Content-Type: application/json" \
-d '{"foo": "bar"}'
```

Here is why that is failing. First of all, let's understand how CSRF systems work. Here is what Dorguzen does:

A CSRF generator — e.g. `getCsrfToken()` — that:

- Generates a random token
- Saves it in the session
- Returns it to embed in forms or headers

Whenever requests are send eg via forms, its validator grabs the token you sent with the form and compares it to the one in the session.

Here's why your test AJAX call from the CLI is not working:

- As explained above, when you open a browser page (like /test/form), a CSRF token is generated and stored in that browser session.
- But when you run your curl command from the CLI, it's a different session — it doesn't share cookies with your browser, so the server has no session data for that token. That's why the backend says "invalid or missing token" even though the token string matches. It's not in the same session context.

Here is how to fix this. When making an API call or simulating an AJAX request from the CLI—and this applies to whether you are using Curl or Postman, you need to send the session cookie as well, so PHP can find the stored token. You can get your current session ID by inspecting cookies in your browser's DevTools > under Application, and in Cookies. What you need is the value of the PHPSESSID cookie, which is PHP's session cookie stored on your computer. Its value looks something like this: `h9vfjdpa0hevanpgqf1un7nfia`. Add that to your CLI AJAX request like this:

```bash
curl -X POST http://localhost:8888/camerooncom/test/submit \
-H "X-CSRF-TOKEN: 3838f4c71de59ac1c16b34bc554c6c886484dfae4e5f8322cf092e30794697dd" \
-H "Content-Type: application/json" \
-H "Cookie: PHPSESSID=h9vfjdpa0hevanpgqf1un7nfia" \
-d '{"foo": "bar"}'
```

Now your CLI requests should work just fine and pass Dorguzen's CSRF validation.

Here is an alternative approach for APIs. To allow you to build API routes that don't rely on session-based CSRF (like mobile or SPA clients), Dorguzen skips the CSRF check for routes starting with `/api/`. This happens in the Dorguzen middleware already in place, and it skips API requests because of the `'csrf_except'` config key entry in `configs/Config.php`.

Basically, CSRF protection is only enforced for web routes, not stateless API endpoints. That is how it is meant to be. API routes already have their own separate validation, and that is by using JSON Web Tokens (JWT).

---

## How to make an API request

The endpoint to API requests looks like this:

```
http://localhost:8888/camerooncom/api/someController-methodName
```

Dorguzen ships with CSRF validation turned off for API requests, but you need to submit your JWT tokens along in the request. This should be done except for login requests where tokens will be created for you, and sent back with the response. Here is an example of an API call made from the CLI using httpie to the refresh token endpoint:

```bash
http POST http://localhost:8888/camerooncom/api/auth-refresh_token \
"Authorization:Bearer eyJ0eXAiOi7N345YNo8bM..."
```

Here is the syntax of another API request made to grab all the favourite products by a user:

```bash
http POST http://localhost:8888/camerooncom/api/ad-favourites "Authorization:Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1N..." Content-Type:application/json <<< '{"caller-origin":"api", "user_id":"1"}'
```

OR:

```bash
http POST http://localhost:8888/camerooncom/api/ad-favourites \
"Authorization:Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
caller-origin=api user_id:=1
```

---

## Example of creating a form with the DGZ_Form class

The following example is from a PHP script that creates a co-manager for an existing shop. Just focus on the syntax of the DGZ_Form in use.

```php
<?php
$form = new DGZ_Form();
$shopId = $shopData['shop_id'];
$shopOwnerId = $shopData['gold_members_users_id'];


$form::open('createCoManager', $this->controller->config->getFileRootPath().'shop/saveCoManager', 'post');

echo '<div class="form-group">';
$form::label('coManagerId', 'Enter the user ID <small>(A user can get their ID from the User Dashboard when they are logged in )</small> <span id="shopNameInfo" style="color:red;">*</span>');
$form::input(
        'coManagerId',
        'text',
        ['class' => 'form-control'],
        isset($_GET['cm'])?htmlentities($_GET['cm'], ENT_COMPAT, 'UTF-8'):'');
echo '</div>';

$form::hidden('shop_id', $shopId);
$form::hidden('shop_owner_id', $shopOwnerId);
if (isset($_GET['cm'])) {
    $form::hidden('old_comanager_id', htmlentities($_GET['cm'], ENT_COMPAT, 'UTF-8'));
}

echo '<div class="form-group">';
$form::submit('button', 'Cancel', ['class' => 'btn btn-warning btn-sm', 'href' => $this->controller->config->getFileRootPath().'shop/manage-shop?userId='.$shopOwnerId]);
$form::submit('submit', isset($_GET['cm'])?'Update Manager':'Create Manager', ['class' => 'btn btn-primary btn-sm ml-3']);
echo '</div>';

$form::close(); ?>
```

As you can see, here are the key points to note:

- You have to first of all instantiate the form:

```php
$form = new DGZ_Form();
```

- The first thing you must do after instantiating the DGZ_Form class is to open the form using its `open()` method. For example:

```php
$form::open('createCoManager', $this->controller->config->getFileRootPath().'shop/saveCoManager', 'post');
```

- Build form labels and their fields using `label()` and `input()`, respectively eg:

```php
$form::label(...)
$form::input(...)
```

- You create a hidden input field like this:

```php
$form::hidden(...)
```

- Add any other fields you wish to add to the form. See the class for all the fields supported.

- Finally, you must call `close()` to close the syntax of the form element.

```php
$form::close(); ?>
```

That is it. The great thing about using the DGZ Form class is that, it already handles CSRF protection for you. If you are curious, that happens when you start the form, like this:

```php
DGZ_Form::open().
```

---

### Create a select form field

The `DGZ_Form::select()` method supports the following:

- allows nested optgroups
- supports preSelected values
- escapes values (safe HTML)
- supports both single & multi-select

The method accepts the following arguments:

```
@param mixed $selectName the name of the select field. This will also be used as its ID
@param mixed $data an associative array of data to display in the select field in 'key => value' pairs
    where the keys will be the option values, & values the option text shown to the user
@param mixed $preSelected this will contain a numerically-indexed, single-level array of
    strings matching the value(s) that you want preselected
@param mixed $multipleSelect whether you want the field to be a multi-select field or not
@param mixed $attributes any attributes you want applied to the select tag
@return string containing the created select field
```

To use it, pass it an associative array as the `$data` argument. Here is an example:

```php
$myform = DGZ_Form::select(
        'gender',
        [
          '1' => 'Male',
          '2' => 'Female'
        ]
      );
```

OR

if the data is a multi-dimensional array, it automatically becomes an optgroup.

```php
$myform = DGZ_Form::select(
        'gender',
        [
          'Gender' =>
              [
                '1' => 'Male',
                '2' => 'Female'
              ]
        ]
      );
```

You can create a standard select field with optgroups like this:

```php
DGZ_Form::select(
  'category',
  [
      'Phones' => [
          'iphone' => 'Apple iPhone',
          'samsung' => 'Samsung Galaxy',
      ],
      'Laptops' => [
          'macbook' => 'MacBook Pro',
          'lenovo' => 'Lenovo Thinkpad',
      ],
      'other' => 'Miscellaneous'
  ],
  ['iphone'], // pre-selected
);
```

Here's a full example:

```php
$form = new DGZ_Form();

$form::open('chooseCategory', $this->controller->config->getFileRootPath().'shop/saveCategory', 'post');

$form::select(
  'category',
  [
      'Phones' => [
          'iphone' => 'Apple iPhone',
          'samsung' => 'Samsung Galaxy',
      ],
      'Laptops' => [
          'macbook' => 'MacBook Pro',
          'lenovo' => 'Lenovo Thinkpad',
      ],
      'other' => 'Miscellaneous'
  ],
  ['iphone'], // pre-selected
);

$form::close(); ?>
```

---

## DGZ_Form method reference

The `DGZ_Form` class exposes the following helpers for building form fields:

| Method | Description |
|---|---|
| `DGZ_Form::open($name, $action, $method, $attributes)` | Opens the `<form>` tag and injects the `_csrf_token` hidden field |
| `DGZ_Form::input($name, $type, $attributes, $value)` | An `<input>` element |
| `DGZ_Form::hidden($name, $value)` | A hidden input |
| `DGZ_Form::label($targetField, $value, $attributes)` | A `<label>` element |
| `DGZ_Form::checkbox($name, $value, $multiple, $attributes)` | A checkbox (or a row of checkboxes when `$multiple` is set) |
| `DGZ_Form::radio($name, $value, $attributes, $preselected)` | A radio button |
| `DGZ_Form::select($name, $data, $preSelected, $multiple, $attributes)` | A `<select>` element (see the select section above) |
| `DGZ_Form::submit($type, $value, $attributes)` | A submit / reset / button input |
| `DGZ_Form::close()` | Closes `</form>` and clears the old-input session state |

---

## Jet forms, Dorguzen's re-usable forms

- Introduction
- Purpose of JetForms
- JetForms Architecture Overview
- Lifecycle of a DGZ Jet Form
    - i) Create the Jet form
    - ii) Register the Jet form
    - iii) Display the Jet form
- Using a jet form in a controller
- Passing more than one Jet form to a view
- How Jet forms are validated
- Adding extra hidden fields on the fly
- Prepopulating form with sample data
- How to make on-POST / non-GET Form Requests (PUT, PATCH, DELETE)
- Optional business logic

---

### Introduction

Forms are like the portal into your application from the outside world. Any dynamic software application or website needs a form, whether it is to get feedback or input from users to provide data to the application to respond to. With that comes the task to collect the data, paying attention to the data type needed, sending it to the right part of the application, also known as the handler, and then knowing which form was submitted, and from what part of the application so that feedback can be returned to the user in the form of an acknowledgement of receipt, or feedback if there were errors with the submission. With this transaction also comes security concerns, as malicious data can be fed into your application. Validation and sanitization of the submitted data is very crucial.

The DGZ_Form provides you a great interface for building any type of form, but it does not meet all those needs. For an application that needs many forms, especially similar forms to use in different areas of the application, Dorguzen has the solution via Jet forms. The principle behind it is to let you create one form and use it everywhere within the application.

This guide explains:

- How the JetForms system is structured.
- What class component (JetForms, JetFormsRegistry, your reusable form classes) is responsible for.
- The lifecycle of a form from creation → population → validation → persistence.
- How developers should use forms in DGZ (with a ContactForm as an example).

---

### Purpose of JetForms

JetForms gives DGZ a reusable, object-oriented, framework-level forms system, similar to Symfony, Laravel Form Requests, and Falkon, only with less abstraction and complexity. The interface is meant to be so easy for any developer to use to create and re-use large and complex forms with ease, without worrying too much about syntax and security. There should be no manual typing of HTML code to create the form fields, and ensuring they are syntactically correct for the backend handler. So, ease of use, speed, re-usability and out-of-the-box security are the buzzwords at play. Jet forms was the name born from the solution to all of that.

It solves these problems:

- Avoid manually constructing forms in controllers/views
- Avoid rewriting the same validation rules
- Automatically repopulate form fields after submission
- Provide a clean API for defining fields
- Provide a standard place to run validation
- All while allow the same form to implement custom logic when used in different places

---

### JetForms Architecture Overview

There are three main components:

**a. JetForms (base class)**

Every reusable form extends this. It handles:

- Defining form fields
- Populating submitted data
- Providing validation rules
- Running validation
- Providing sanitized input access
- Returning errors
- There is room for Hooks like `afterValidate()` or `persist()` if the developer wants to add functionality

**b. JetFormsRegistry**

A global registry that:

- Stores all form instances
- Allows DGZ (especially the Router) to retrieve forms anytime
- Makes it possible to use forms across controllers, middleware, and views
- Think of it like a "container" that keeps track of active form objects.

**c. Reusable forms inside src/forms/**

Example: ContactForm

These forms:

- Extend JetForms
- Define their own fields
- Define their own validation rules
- May be extended by being made to define optional behaviour (send email, save to DB, etc.)
- Every reusable form must have the following members:

```php
public string $name = 'contactForm';
public string $handler = 'data/test-contact-form';
public string $method = 'contactForm';
public string $redirectBack = 'data/privacy';
public array $rules = [];
public array $messages = [];
protected function renderFields()
```

The `$name` property is the key used to register the form in Dorguzen. More on this below.

The `$handler` is the path string of the script to handle the form submission—usually a controller and method. This must be a legal (existing) route.

The `$method` property is the HTTP method to be used in submitting the form. It will be used in the 'action' attribute of the form.

The `$redirectBack` is the opposite routing of `$handler`. It should be a legal (existing) route that displays the form. This will be used by the system to know where the form was submitted from so it can redisplay any feedback—e.g. submission acknowledgement or errors after validation.

The `$rules` property is the same as all forms that use DGZ_Form. It contains an associative array of field names, and the validation rules that will guide the validation.

The `$messages` property is also the same as all forms that use DGZ_Form. It contains an associative array of the defined rules as the keys, and the messages you would like to be shown to the user if the validation of that field fails.

The method `renderFields()` is where you will create the actual form fields for the form class. To do so, you will use the form helper methods of DGZ_Form like so:

```php
public function renderFields() {
  self::label('name', 'Your name');
  self::input('name', 'text', ['class' => 'form-control'], $this->data['name'] ?? null);

  self::label('email', 'Email');
  self::input('email', 'email', ['class' => 'form-control'], $this->data['email'] ?? null);

  self::submit('submit', 'Send', ['class' => 'btn btn-primary']);
}
```

Then to display the form in any view, you just have to instantiate the specific form, set any parameters using the available setter methods, and then call `render()`.

```php
<?php
  $form = new \src\forms\ContactForm();
  $form->setHandler('seo/test-contact-form-from-module');
  $form->setRedirectBack('seo');
  $form->render(['class'=>'my-form']);
?>
```

The `render()` method is a method on the JetForms class which wraps the `renderFields()` method of the form you are creating while generating the full form including opening and closing tags, with any parameters you have set for it. In this example above, we are creating a form directly in a view file, but you can also create the form on the fly in a controller, and pass it to a view. We will see an example shortly.

---

### Lifecycle of a DGZ Jet Form

Below is the step-by-step process from request → registry → validation → view rendering. This will another example of creating a form in a view file. Let's go in steps:

- i) Create the Jet form
- ii) Register the form
- iii) Display the form

#### i) Create the Jet form

So assuming we already have a Jet form named ContactForm, and it lived in `src/forms/`. Here is the full example code:

```php
namespace Dorguzen\Forms;

class ContactForm extends JetForms
{
    public string $name = 'contact_form';


    /**
     * handler string. The valid route to the form handler,
     * usually a controller to process the form.
     * This must be a valid route
     */
    public string $handler = 'data/test-contact-form'; // optional


    /** handler string. HTTP method to use to submit the form */
    public string $method = 'POST';


    /**
     * redirectBack string. Path to send the user back to if validation fails.
     * This must be the valid route to the view that displays the form.
     * This is how the application knows which view the form was submitted from.
     */
    public string $redirectBack = 'feedback/contact';


    protected array $rules = [
        'name' => 'required|min:3|max:100',
        'email' => 'required|email',
        'message' => 'required|min:10',
        'category' => 'nullable|in:support,sales,other'
    ];


    protected array $messages = [
        'name.required' => 'Please tell us your name.',
        'email.email' => 'Please provide a valid email address.',
        'message.min:10' => 'Message must be at least 10 characters.'
    ];



    protected function renderFields(): void
    {
        // Use DGZ_Form helpers to build the form fields
        self::label('name', 'Your name');
        self::input('name', 'text', ['class' => 'form-control'], $this->data['name'] ?? null);

        self::label('email', 'Email');
        self::input('email', 'email', ['class' => 'form-control'], $this->data['email'] ?? null);

        self::label('message', 'Message');
        self::input('message', 'textarea', ['class' => 'form-control'], $this->data['message'] ?? null);

        self::label('category', 'Category');
        self::select('category', [
            'support' => 'Support',
            'sales' => 'Sales',
            'other' => 'Other'
        ], [$this->data['category'] ?? ''], false, ['class' => 'form-select col-md-12']);

        echo '<br>';

        self::submit('submit', 'Send', ['class' => 'btn btn-primary']);
    }
}
```

#### ii) Register the Jet form

For a Jet form to work, we have to register it with the `src/forms/JetFormsRegistry` class. This class keeps track of all re-usable form classes in the system. To do so, visit `bootstrap/app.php` and find the section where Jet forms are registered. Then add this to the code:

```php
$container->get(JetFormsRegistry::class)->register('contact_form', ContactForm::class);
```

Don't forget to use the Jet form class at the top of the file:

```php
...
use Dorguzen\Forms\ContactForm;
```

This registration step provides an extra level of security. This is in the sense that, the only forms processed on submission will be forms that are known by the system.

#### iii) Display the form

This is so simple, because it can be done in as little as two lines. In the view where you wish to create the form, do it like this:

```php
$form = new \src\forms\ContactForm();
$form->render(['class'=>'my-form']);
```

Tip: the route of the view where you are displaying this form should match the route you specified as the value of the `redirectBack` property in the form. This just tells Dorguzen where to send feedback to the user after the form submission, or errors in case validation failed.

---

### Using a jet form in a controller

Most often you would create a Jet form on the fly within a controller and pass it to the view. This is how; say you are in the `contact()` method of a controller, FeedbackController. Before displaying the `views/contact.php` create the form and send it through as follows:

```php
public function contact()
{
    $form = new \src\forms\ContactForm();

    $view = Dorguzen\Core\DGZ_View::getView('contact', $this, 'html');
    $this->setPageTitle('Our contact page');
    $view->show(['form' => $form]);
}
```

Then in the view file (in this case 'views/contact'), retrieve the form from the data array passed through and render it:

```php
namespace views;

class contact extends Dorguzen\Core\DGZ_HtmlView
{
    ...
    function show($data)
    {
        $form = $data['form'];
        $form->render(['class'=>'contact-form']);
    }
}
```

---

### Passing more than one Jet form to a view

You can pass more than one Jet form from the controller to the view. This is how; say you are in the `contact()` method of a controller, FeedbackController. Before displaying the `views/contact.php` create the forms and send them through as follows:

```php
public function contact()
{
    // Passing two forms to a view file
    $data = [
        'name' => 'Donald',
        'email' => 'don@google.com',
        'message' => 'test message',
        'category' => 'sales'
    ];
    $form = new ContactForm();
    $form->addHiddenField('_method', 'PUT');
    $form->fill($data);

    // another form
    $form2 = new AnotherForm();

    $view = DGZ_View::getView('privacy', $this, 'html');
    $this->setPageTitle('privacy policy');
    $view->show(['form' => $form, 'form2' => $form2]);
}
```

Then in the view file (in this case 'views/contact'), retrieve the form from the data array passed through and render it:

```php
namespace views;

class contact extends Dorguzen\Core\DGZ_HtmlView
{
    ...
    function show($data)
    {
        $form = $data['form'];
        $form->render(['class'=>'my-form']);
        echo "<br>";

        // the second form
        $form2 = $data['form2'];
        $form2->render();
    }
}
```

---

### How Jet forms are validated

The great thing about Jet forms is that they are validated automatically, so we'd say its developers hands-off. The magic happens in `middleware/FormValidationMiddleware.php`. Here is a breakdown of what happens once the Jet form submission is intercepted.

First, the FormValidationMiddleware detects a submitted form. It does this by checking for the existence of an input named `'_form_name'`, which is in the hidden input field in the form containing the name of the given form. It then resolves the form by that name from FormRegistry, then proceeds to do the following:

- fills the form class with the request data,
- validates the form based on its own defined rules,
- throws a ValidationException if validation fails,
- the DGZ router catches the ValidationException & handles it then sets `SESSION['old_input']` & `SESSION['validation_errors']` (which is useful in re-populating form in the next steps)
- if validation passes, it sets on `SESSION['old_input']`, and then lets the controller handle further processing.

Dorguzen's middlewares are ran by priority, so the middleware CSRF validation checks have priority 1, so that will always be checked for first, before detecting if the request is a Jet form. However, you can adjust ordering as you see fit.

If validation fails, a `ValidationException()` exception is thrown, which the router will catch. After catching this ValidationException, the router sets a session flash message & redirects to referer. That is how a wrongly submitted Jet form is redisplayed with errors for the user to fix.

---

### Adding extra hidden fields on the fly

Use the `addHiddenField()` method of the JetForms class. It takes two arguments, the name of the hidden field, and its value.

```php
$form = new \src\forms\ContactForm();
$form->addHiddenField('_method', 'PUT');
```

---

### Pre-populating a form with sample data

There will be times when you want to display a form and have some sample/dummy data populating the fields, ready to test a submission with, or it could be just some default data you want to be submitted for a field if nothing is entered by the user. Just pass an associative array of keys and values where the keys exactly match the names of your form fields. Here is an example of creating a form with some data for pre-populating the form:

```php
$data = [
    'name' => 'Donald',
    'email' => 'don@google.com',
    'message' => 'test message',
    'category' => 'sales'
  ];
  $form = new \src\forms\ContactForm();
  $form->addHiddenField('_method', 'PUT');
  $form->fill($data);

  $view = Dorguzen\Core\DGZ_View::getView('contact', $this, 'html');
  $this->setPageTitle('Our contact page');
  $view->show(['form' => $form]);
```

---

### How to make on-POST / non-GET Form Requests (PUT, PATCH, DELETE)

HTML forms only support two HTTP methods natively:

```
GET
POST
```

This is not a Dorguzen limitation — it is an actual browser limitation. Browsers do not send PUT, PATCH, or DELETE requests through normal `<form>` submissions.

To send those methods, you normally need to use JavaScript techniques such as:

- AJAX requests (XMLHttpRequest)
- `fetch()` requests
- A JavaScript framework (Vue, React, etc.)

However, server-side frameworks have long used a simple trick to simulate PUT/PATCH/DELETE even when using standard HTML forms. Dorguzen supports the exact same trick. This is the Hidden `_method` Field Trick. If you want a form to behave like a PUT, PATCH, or DELETE request, the form should still submit using:

```html
<form method="POST">
```

…but inside the form, include a hidden field called `_method` telling Dorguzen which actual method you intended:

```html
<input type="hidden" name="_method" value="PUT">
```

Your controller or middleware can then treat the request as if it were truly a PUT request. This pattern is used by frameworks like Laravel, Symfony, Rails, and now Dorguzen JetForms.

**How to Add the _method Hidden Field in JetForms**

If you are building the form using JetForm in a view file, or controller on the fly, simply call:

```php
$form->addHiddenField('_method', 'PUT');
```

Or PATCH / DELETE, depending on what you want.

Example (in a view):

```php
$form = new \src\forms\EditPostForm();
$form->addHiddenField('_method', 'PATCH');
$form->render();
```

Example (in a controller):

```php
$editForm = new EditPostForm();
$editForm->addHiddenField('_method', 'DELETE');

$view = Dorguzen\Core\DGZ_View::getView('edit_post', $this, 'html');
$view->show(['form' => $editForm]);
```

You then need to check for the existence of that hidden `_method` field in the backend and use it to do what you need to do. e.g.

```php
$method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'];
```

If `_method` exists, treat it as the request method instead of POST.

Typical use-cases for such request methods include:

- when you need to perform REST-style actions without JavaScript, for example:
- update a record → use `_method = PATCH` or `_method = PUT`
- deleting a record → use `_method = DELETE`

If you really prefer true PUT/PATCH/DELETE HTTP requests, then you must use JavaScript (AJAX / fetch), because the browser will not generate these natively.

---

### How JetForms are validated

The validation is done in middleware upon submission. So when the request finally gets to the handler, you need no longer worry about validation. You can then just do whatever else you wish to do, like persist the data to the DB.

The actual validation process is done like any other form using the DGZ_Validator. The submitted data, together with the validation rules and custom validation messages of the given form are passed to the `validate()` method, and another method; `passes()` returns true or false depending on if there were errors or not.

---

### Optional business logic

You can optionally expand what the FormValidationMiddleware code does. For example; instead of letting the request to be escalated up the stack till it gets to the target handler (controller), you can choose to perform a task right there after the passed validation and do something like logging, send off an email, or even persist the data to DB. To give you a random hint; you could add a method or two to the Jet form (in this case ContactForm) class e.g.

- `afterValidate()` — this will contain code that can be called to run after the validation
- `persist()` — this can contain code to save the data to the DB

If you decide to go this route, you must add code to call these extra methods of the form class from within `middleware/globalMiddleware/FormValidationMiddleware`'s `handle()` method. Do it below, towards the end, after validation passes, but before it returns true.

---

## Handling a form submission in a controller

Apart from Jet forms (whose validation runs automatically in middleware), a plain form posts to a controller method where you handle it yourself. The typical flow is: sanitize the input, validate it, and on failure flash an error and send the user back with their input preserved.

```php
public function store(): void
{
    if (isset($_POST['name'])) {
        // 1. Sanitize
        $name    = DGZ_Validate::fix_string($_POST['name']);
        $email   = DGZ_Validate::fix_string($_POST['email']);
        $message = DGZ_Validate::fix_string($_POST['message']);

        // 2. Validate (see the Security docs for the DGZ_Validator)
        $fail = $this->contactService->validateInput($name, $email, $message);

        if ($fail !== '') {
            // 3a. On failure: flash an error, restore old input, redirect back
            $this->addErrors($fail);
            $this->postBack($_POST);
            $this->redirect('contact');
            return;
        }

        // 3b. On success
        $this->contactService->save($name, $email, $message);
        $this->addSuccess('Your message has been sent.');
        $this->redirect('contact');
    }

    $this->display('contact');
}
```

### Flashing feedback messages

`DGZ_Controller` provides four helpers for returning feedback to the user. Each stores its messages in a session key that survives the redirect and is rendered automatically by the layout (as a Bootstrap alert) before being cleared:

| Method | Session key | Rendered as |
|---|---|---|
| `$this->addErrors($msg)`  | `_errors`   | `alert-danger`  |
| `$this->addWarning($msg)` | `_warnings` | `alert-warning` |
| `$this->addNotice($msg)`  | `_notices`  | `alert-info`    |
| `$this->addSuccess($msg)` | `_success`  | `alert-success` |

### Sticking old input

```php
$this->postBack($_POST);
```

`postBack()` stores `$_POST` in `$_SESSION['postBack']`, so the values can be re-displayed after the redirect (see below).

---

## Old input repopulation

`DGZ_Form::getOldValue($key, $default)` retrieves a previously submitted value, checking three session sources in order:

1. `$_SESSION['old_input']` — set by the JetForms validation middleware
2. `$_SESSION['postBack']` — set by `$this->postBack($_POST)`
3. `$_SESSION['old_input_for_forms']` — set by `DGZ_Form::setOld()`

Use it to repopulate a field after a failed submission:

```html
<input type="email" name="email"
       value="<?= htmlspecialchars(DGZ_Form::getOldValue('email', '')) ?>">
```

`DGZ_Form::close()` clears all three sources, so old values are shown once and then discarded on the next render.

---

## File uploads in forms

For a form that uploads files, add `enctype="multipart/form-data"` to the opening tag:

```html
<form action="/profile/photo" method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf_token" value="<?= getCsrfToken() ?>">
    <input type="file"   name="photo">
    <button type="submit">Upload</button>
</form>
```

Process the upload in the controller with `DGZ_Uploader`. See the File Uploads documentation for the full upload API.

---

## AJAX form submissions

For AJAX submissions, send the CSRF token as an `X-CSRF-TOKEN` request header instead of a hidden field. Dorguzen's request object looks for the token in that header (`DGZ_Request::getCsrfTokenFromRequest()`). Expose the token in the layout `<head>`:

```html
<meta name="csrf-token" content="<?= getCsrfToken() ?>">
```

Then read it and send it with the request:

```js
const token = document.querySelector('meta[name="csrf-token"]').content;

fetch('/contact', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
    },
    body: JSON.stringify({ name, email, message }),
});
```

For REST API endpoints CSRF is exempted (set `APP_API_CSRF_EXCEPTION='/api/'` in `.env`, which populates the `csrf_except` config), so no token is needed there.
