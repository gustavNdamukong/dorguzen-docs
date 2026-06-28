# Validation

## Using the DGZ_Validator

Dorguzen provides you with a robust validation object to secure all kinds of requests to your web application. This includes forms and API requests. First of all, be aware that the validator is available to all your controllers as long as they extend the parent controller `DGZ_Controller` as they should. They will then be able to access the validator via their validator property, or an accessor convenient method `validator()` like so:

```php
$this->validator ...
```

or

```php
$this->validator(...)
```

Let's break down this `DGZ_Validator` to you completely master how it works.

---

## Examples — Using the validator in controllers

Example A — validating a web form submission (classic)

```php
public function saveProfile()
{
    $input = $_POST; // or use sanitized input wrapper you already have

    $rules = [
        'first_name' => 'required|string|min:2|max:50',
        'last_name'  => 'required|string|min:2|max:50',
        'email'      => 'required|email',
        'age'        => 'nullable|integer|between:18,120',
        'bio'        => 'nullable|string|max:1000',
    ];

    $validator = $this->validator($input, $rules, [
        'email.required' => 'We need your email address.',
        'age.between' => 'You must be at least 18.',
    ]);

    if ($validator->fails()) {
        $errors = $validator->errors();
        // send back to view:
        $this->addErrors($errors, 'Validation failed');

        // redirect back to another controller & method
        $this->redirect('auth', 'dashboard');

        or render a view and pass in the error data
        $view = Dorguzen\Core\DGZ_View::getView('register', $this, 'html');
        $view->show(['errors' => $errors, 'old' => $input]);
    }

    // validation passed — proceed to save
    ...
}
```

---

## Validating JSON payload for API

```php
public function apiCreateAd()
{
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];

    $rules = [
        'title' => 'required|string|min:3|max:120',
        'price' => 'required|numeric|min:0',
        'category_id' => ['required','integer'],
        'images' => 'nullable|array',
    ];

    $validator = $this->validator($payload, $rules);

    if ($validator->fails()) {
        $errors = $validator->errors();
        header('Content-Type: application/json', true, 422);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // continue...
}
```

---

## A Custom callback rule (closure or callable)

A rule can be a closure or a callback:myFuncName:

```php
$rules = [
    'username' => [
        'required',
        'min:5',
        function($value, $all, $field) {
            if (!preg_match('/^[a-z0-9_-]+$/i', $value)) {
                return 'Only letters, numbers, - and _ allowed.';
            }
            // return true if passes
            return true;
        }
    ],
    'unique_slug' => 'required|callback:\Client\Helpers::isUniqueSlug'
];
```

In this closure above, the 3 parameter values `$value`, `$all`, and `$field` come from the validator engine. For example:

`$value` is the value of the single field currently being validated eg 'johnny_walker'.
`$all` is the full data array being validated (all request data-the entire submitted form).
`$field` is the field name currently being validated eg 'username'.

For example, here is another function (closure) you can use to ensure that the user's email address is not the same as their username:

```php
function($value, $all, $field) {
    // Example: ensure email does not equal username
    if ($field === 'email' && $value === $all['username']) {
        return 'Email cannot be the same as username';
    }

    return true;
}
```

If the callable returns true validation passes; if it returns false or a string, failure message is registered (string used as error message if provided).

When you write:

```php
'unique_slug' => 'required|callback:\Client\Helpers::isUniqueSlug'
```

this means:

- there exists a class Helpers inside namespace `\Client`
- inside that class there is a static method: `isUniqueSlug($value)`
- and that method must return true or false

Typically, you would want you callback method/function to be one that is in the same controller where the validation is being done. Say for example, in the current controller you have a method named `validateCustomerAccount()`, here is an example syntax of how you will use that method in the callback of the validation rules:

```php
$rules = [
    'fieldName' => 'required|callback:$this->validateCustomerAccount'
];
```

Note that any arguments that the callback function needs-which is data submitted from the form will be automatically injected for you by the validator from the data array you passed in to it.

You could just as well make this callback be any method in your application. For example, if you wish to call a method in one of your models, say a Users model, you can do that in Dorguzen like so:

```php
use Dorguzen\Models\Users;

$users = container(Users::class);
```

or

```php
$users = new \src\models\Users();
```

then

```php
$rules = [
    'email' => [
        'required',
        ['callback', [$users, 'checkIfInUse']]
    ]
];
```

Apart from one-line rules like required, or string, or integer etc, a rule should be followed by a colon, and then its value e.g.

```php
min:8
```

which means at least 8 characters are expected of that field, or 8 items if it is an array for example. Multiple rules for a field are separated by pipe characters if they are defined as a string and not as an array e.g

```php
$rules = [
    'field_name' => "rule1:value|rule2:value"
];
```

---

## Rules available (built-in)

- `required` — value must exist and not be empty

- `nullable` — allow null/empty to skip other checks

- `sometimes` — only validate when key present in data

- `string` — must be string

- `integer` / `int` — integer

- `numeric` — number or numeric string

- `boolean` — boolean-ish (0/1/true/false)

- `email` — RFC-ish email validation via filter_var

- `url` — filter_var URL

- `min:x` — for strings, numbers, arrays

- `max:x` — for strings, numbers, arrays

- `between:x,y` — for strings, numbers, arrays

- `regex:pattern` — pattern (without delimiters)

- `in:one,two,three` — value must be one of the list

- `not_in:one,two` — value must not be one of the list

- `same:otherField` — equal to other field

- `different:otherField` — not equal

- `date` — validateable by strtotime

- `before:YYYY-MM-DD` / `after:YYYY-MM-DD`

- `array` — must be array

- `callback:callableName` — call a callable

Also, the closure rules are allowed directly inside the rules array

You can extend the class with extra `validate_*` methods if you need special behaviour.

---

## Messages & translations

You passed customMessages as second param to `DGZ_Validator::make()` or to `$this->validator()`. Keys can be either field.rule or just rule. The validator uses them in priority order.

Examples:

```php
$messages = [
    'email.required' => 'We need your email',
    'min' => 'Some field is too small'
];

$validator = $this->validator($input, $rules, $messages);
```

---

## Performance & extension notes

Validation runs in PHP synchronously; it's fast for typical form payloads.
Keep complex checks (e.g., DB uniqueness) as callbacks that call model methods — they can be slower (DB roundtrip), so use them sparingly or add caching.

The `'sometimes'` rule is handy for PATCH/partial updates.

For file validations you can add validate_mime or validate_max_filesize methods and call them from the same validator.

---

## Full controller example flow

```php
public function register()
{
    $input = $_POST;

    $validator = $this->validator($input, [
        'username' => 'required|min:5|callback:\MyApp\User::usernameAvailable',
        'password' => 'required|min:8',
        'email' => 'required|email',
    ], [
        'username.callback' => 'That username is already taken.'
    ]);

    if ($validator->fails()) {
        return $this->render('auth/register', ['errors' => $validator->errors(), 'old' => $input]);
    }

    // create user...
}
```

---

## Short tutorial

Create validator instance

```php
$validator = $this->validator($_POST, ['email' => 'required|email', 'age' => 'nullable|integer|min:18']);
```

Check result

```php
if ($validator->fails()) { $errors = $validator->errors(); /* show errors */ }
```

Custom message

```php
$validator = $this->validator($data, $rules, ['email.required' => 'Please provide an email']);
```

Custom rule

Use closure:

```php
'username' => [
  'required',
  function($value, $all, $field) {
      return preg_match('/^[a-z0-9]+$/i', $value) ? true : 'Invalid username';
  }
]
```

Or callable:

```php
'slug' => 'required|callback:\App\Helpers::isSlugUnique'
```

API usage

Validate `json_decode(file_get_contents('php://input'), true)` and return 422 with `$validator->errors()`.

Extending rules

Add protected `validate_customname(...)` to `DGZ_Validator`. It will automatically be used for rule customname.

---

## Form submission and Controller validation example

### View with form

```php
$form = new DGZ_Form();

$form::open(
    'chooseCategory',
    $this->controller->config->getFileRootPath().'data/test-process-form',
    'post'); ?>

<div class="col-md-12">
    <?php
    $form::input(
        'name',
        'text',
        [
            'name' => 'name',
            'placeholder' => 'your name',
            'class' => 'col-md-12 form-control'
        ]);
    ?>
</div>

<div class="col-md-12">
    <?php
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
        true,
        [
            'name' => 'category',
            'class' => 'col-md-12 form-select',
        ]
    ); ?>
</div>

<div class="form-group col-md-12">
    <?php
    $form::submit('submit', 'Save data', ['class' => 'btn btn-primary btn-sm ml-3']);
    ?>
</div>
<?php

$form::close();
```

### Controller validation

As we can see from the `Form::open()` section, this line: `($this->controller->config->getFileRootPath().'data/test-process-form')` tells us that the form submission handler is the `src\controllers\DataController`, in the method named `testProcessForm()`.

The form has only two fields
- name: where the user is expected to enter their name
- category: where the user is expected to make a selection from a list of categories

In the form handler method, which is `DataController->testProcessForm()`, here is how the form submission is processed:

```php
public function testProcessForm()
{
    $input = request()->post();

    $rules = [
        'name' => 'required|max:8',
        'category' => 'min:2' // user must choose at least two items from the select field
    ];

    $customMessages = [
        'name.required' => 'name is required my dawg!',
        'name.max:8' => '8 characters max, for name please',
        'category.min:2' => 'common man, pick at least two ok'
    ];

    $validator = $this->validator($input, $rules, $customMessages);

    if ($validator->fails()) {
        $errors = $validator->errors();

        $errorMsg = "";

        foreach ($errors as $key => $error)
        {
            $errorMsg .= $error[0].'<br>';
        }

        // send back to view:
        $this->addErrors($errorMsg, 'Validation failed');

        // redirect back to another controller & method
        $this->redirect('data', 'privacy');

        // or render a view and pass in the error data
    }

  $this->addSuccess('Submission was successful', 'Yay');

    $view = Dorguzen\Core\DGZ_View::getView('privacy', $this, 'html');
    $this->setPageTitle('Data privacy');
    $view->show();
}
```

---

## DGZ_Validation Rules — Full List With Usage + Custom Messages

Here is a clean, comprehensive, copy-ready list of all DGZ validation rules, each with:

- What the rule does
- How to write the rule inside your `$rules` array
- How to write custom messages for that rule

No tables. Pure list format with indentation, perfect for your notes app.

---

### 1. required

- Ensures the field is present and not empty.
- **Usage in rules:**
  `'username' => ['required']`
- **Custom message:**
  `'username.required' => 'Please enter a username.'`

---

### 2. email

- Validates that the field contains a valid email address.
- **Usage:**
  `'email' => ['required', 'email']`
- **Custom message:**
  `'email.email' => 'Enter a valid email address.'`

---

### 3. numeric

- Ensures the field is a number.
- **Usage:**
  `'age' => ['numeric']`
- **Custom message:**
  `'age.numeric' => 'Age must be a number.'`

---

### 4. min:X

- Minimum length (for strings) OR minimum numeric value (if numeric).
- **Usage:**
  `'password' => ['min:6']`
- **Custom message:**
  `'password.min' => 'Password must be at least 6 characters.'`

---

### 5. max:X

- Maximum length (strings) OR max numeric value.
- **Usage:**
  `'username' => ['max:20']`
- **Custom message:**
  `'username.max' => 'Username cannot exceed 20 characters.'`

---

### 6. between:min,max

- Ensures the value length or number is between two values.
- **Usage:**
  `'age' => ['numeric', 'between:18,65']`
- **Custom message:**
  `'age.between' => 'Age must be between 18 and 65.'`

---

### 7. same:otherField

- Ensures two fields match.
- **Usage:**
  `'password_confirmation' => ['same:password']`
- **Custom message:**
  `'password_confirmation.same' => 'Passwords do not match.'`

---

### 8. in:a,b,c

- Ensures the value is one of a list.
- **Usage:**
  `'role' => ['in:admin,user,guest']`
- **Custom message:**
  `'role.in' => 'Invalid role selected.'`

---

### 9. not_in:a,b,c

- Ensures the value is *not* one of a list.
- **Usage:**
  `'username' => ['not_in:root,admin']`
- **Custom message:**
  `'username.not_in' => 'This username is not allowed.'`

---

### 10. regex:/pattern/

- Validates a value using a regex.
- **Usage:**
  `'username' => ['regex:/^[a-z0-9_-]+$/i']`
- **Custom message:**
  `'username.regex' => 'Only letters, numbers, hyphens, and underscores allowed.'`

---

### 11. url

- Validates a proper URL.
- **Usage:**
  `'website' => ['url']`
- **Custom message:**
  `'website.url' => 'Enter a valid URL.'`

---

### 12. date

- Checks if the value is a valid date string.
- **Usage:**
  `'start_date' => ['date']`
- **Custom message:**
  `'start_date.date' => 'Enter a valid date.'`

---

### 13. before:otherDateField

- Ensures a date occurs before another date.
- **Usage:**
  `'start_date' => ['before:end_date']`
- **Custom message:**
  `'start_date.before' => 'Start date must be before end date.'`

---

### 14. after:otherDateField

- Ensures a date occurs after another date.
- **Usage:**
  `'end_date' => ['after:start_date']`
- **Custom message:**
  `'end_date.after' => 'End date must be after start date.'`

---

### 15. boolean

- Value must be true/false, 1/0, yes/no.
- **Usage:**
  `'active' => ['boolean']`
- **Custom message:**
  `'active.boolean' => 'Invalid boolean value.'`

---

### 16. array

- Ensures the field is an array.
- **Usage:**
  `'tags' => ['array']`
- **Custom message:**
  `'tags.array' => 'Tags must be an array.'`

---

### 17. closure-based rule

- For fully custom logic.
- **Usage:**

  ```php
  'username' => [
      'required',
      function($value, $all, $field) {
          if (!preg_match('/^[a-z0-9_-]+$/i', $value)) {
              return 'Only letters, numbers, - and _ allowed.';
          }
          return true;
      }
  ]
  ```
- **Custom message:**
  Defined inside the closure return string.

---

### 18. file

- Ensures the uploaded field is a file.
- **Usage:**
  `'avatar' => ['file']`
- **Custom message:**
  `'avatar.file' => 'Please upload a valid file.'`

---

### 19. mimes:jpg,png,gif

- Ensures uploaded file matches MIME types.
- **Usage:**
  `'avatar' => ['file', 'mimes:jpg,png']`
- **Custom message:**
  `'avatar.mimes' => 'Only JPG and PNG images allowed.'`

---

### 20. size:X

- Validates file size in kilobytes.
- **Usage:**
  `'avatar' => ['size:2048']`
- **Custom message:**
  `'avatar.size' => 'File must be exactly 2MB.'`

---

### 21. min_size:X

- Minimum file size (KB).
- **Usage:**
  `'avatar' => ['min_size:50']`
- **Custom message:**
  `'avatar.min_size' => 'File must be at least 50KB.'`

---

### 22. max_size:X

- Maximum file size (KB).
- **Usage:**
  `'avatar' => ['max_size:2048']`
- **Custom message:**
  `'avatar.max_size' => 'File cannot exceed 2MB.'`

---

### 23. unique:table,column(optional)

- Ensures the value doesn't already exist in DB.
- **Usage:**
  `'email' => ['unique:users,email']`
- **Custom message:**
  `'email.unique' => 'This email is already registered.'`

---

### 24. exists:table,column(optional)

- Ensures a value already exists in DB.
- **Usage:**
  `'country_id' => ['exists:countries,id']`
- **Custom message:**
  `'country_id.exists' => 'Invalid country selected.'`

---

### 25. nullable

- Allows field to be null and skip other validation unless a value is provided.
- **Usage:**
  `'middle_name' => ['nullable', 'min:2']`
- **Custom message:**
  Works with whatever rule(s) follow.

---

## DGZ_Validate (Legacy, Field-by-Field)

Alongside the rule-based `DGZ_Validator`, Dorguzen ships an older, lightweight helper, `DGZ_Validate`, which provides one method per field type. Each method returns an HTML error string (with a trailing `<br />`) when the value is invalid, or an empty string `''` when it passes. It is still used inside service classes (e.g. `AuthService`, `AdminService`) where simple, concatenated error strings are convenient.

```php
use Dorguzen\Core\DGZ_Validate;

$val  = new DGZ_Validate();
$fail  = $val->validate_firstname($firstname);
$fail .= $val->validate_surname($surname);
$fail .= $val->validate_password($password);
$fail .= $val->validate_email($email);

if ($fail !== '') {
    $this->addErrors($fail, 'Validation failed');
    $this->redirect('register');
    return;
}
```

### Available methods

- `validate_firstname($value)` — required (non-empty).
- `validate_surname($value)` — required (non-empty).
- `validate_username($value)` — required, at least 5 characters, only letters, numbers, `-` and `_`.
- `validate_password($value)` — required, at least 6 characters.
- `validate_email($value)` — required, must look like a valid email.
- `validate_phonenumber($value)` — required, numeric (digits, `-` and spaces allowed).
- `validate_age($value)` — required, must be between 18 and 110.
- `validate_shop_name($value)` — required, only letters, numbers, `-` and `_` (no spaces).

### Sanitising input — fix_string()

`DGZ_Validate::fix_string()` cleans a raw input string before validation or storage. It applies `stripslashes()`, then `trim()`, then `htmlentities()`:

```php
$val   = new DGZ_Validate();
$email = $val->fix_string($_POST['email']);
```

---

## DGZ_CheckPassword (Password Strength)

For stronger password policies than `validate_password()`'s minimum-length check, use `DGZ_CheckPassword`. It collects human-readable error messages you can surface to the user.

```php
use Dorguzen\Core\DGZ_CheckPassword;

$checkPwd = new DGZ_CheckPassword($password, 8); // 2nd arg = minimum length (default 6)
$checkPwd->requireMixedCase();   // must contain both upper- and lowercase letters
$checkPwd->requireNumbers(1);    // at least N digits
$checkPwd->requireSymbols(1);    // at least N non-alphanumeric characters

if (!$checkPwd->check()) {
    foreach ($checkPwd->getErrors() as $error) {
        $fail .= $error;
    }
}
```

Methods:

- `__construct($password, $minimumChars = 6)` — the password to test and its minimum length.
- `requireMixedCase()` — require both upper- and lowercase letters.
- `requireNumbers($num = 1)` — require at least `$num` digits.
- `requireSymbols($num = 1)` — require at least `$num` non-alphanumeric characters.
- `check()` — returns `bool`; also rejects passwords containing whitespace.
- `getErrors()` — returns the array of error message strings collected by `check()`.

---

## JetForms Validation (Middleware-Based)

JetForms let you move validation out of the controller and into a reusable form class. The `FormValidationMiddleware` intercepts the request, validates it against the form's own `$rules`, and only lets the request reach the controller when validation passes.

A JetForm extends the abstract `Dorguzen\Core\JetForms\JetForms` class (itself a child of `DGZ_Form`). Each child declares `$name`, `$handler`, `$method` and `$redirectBack`, plus its `$rules`/`$messages`, and implements `renderFields()`:

```php
namespace src\forms;

use Dorguzen\Core\JetForms\JetForms;

class RegistrationForm extends JetForms
{
    protected string $name         = 'registrationForm';
    protected string $handler      = '/register/store';
    protected string $method       = 'post';
    protected string $redirectBack = '/register';

    protected array $rules = [
        'firstname' => 'required|min:2',
        'surname'   => 'required|min:2',
        'email'     => 'required|email',
        'password'  => 'required|min:6',
        'confirm'   => 'required|same:password',
    ];

    protected array $messages = [
        'confirm.same' => 'Passwords do not match.',
    ];

    protected function renderFields(): void
    {
        // build fields with self::input(...), self::select(...), etc.
    }
}
```

The form must be registered by its `$name` (the key used in `bootstrap/app.php` must match the `$name` property). `render()` automatically emits three hidden fields the middleware relies on: `_form_name`, `_handler` and `_redirectBack`.

When the form is submitted, `FormValidationMiddleware` (priority `5`, so it runs after CSRF):

1. Detects the submission via the hidden `_form_name` field and resolves the form class through the `JetFormsRegistry`.
2. Fills the form with the request payload and runs its `validate()` (which delegates to `DGZ_Validator`).
3. On failure, stores the submitted values in `$_SESSION['old_input']` and the field errors in `$_SESSION['validation_errors']`, then throws a `ValidationException`. The `DGZ_Router` catches it, flashes the errors and redirects back to `_redirectBack`.
4. On success, stores the validated data in `$_SESSION['old_input']` and lets the request continue to the controller.

See [Forms](/docs/forms) for the full JetForms walkthrough.

---

## Displaying Validation Errors

Errors added with `$this->addErrors($message, $title)` are stored in `$_SESSION['_errors']`. The layout renders them automatically via the built-in `ErrorsListView` (`Dorguzen\Core\DGZ_views\ErrorsListView`) as Bootstrap `alert alert-danger` blocks, then clears the session key — so you do not normally need to add error-display markup to your views.

For inline, field-level errors (e.g. JetForms submissions), read `$_SESSION['validation_errors']` directly in the view:

```php
<?php $errors = $_SESSION['validation_errors'] ?? []; ?>

<input type="email" name="email">
<?php if (!empty($errors['email'])): ?>
    <div class="invalid-feedback d-block">
        <?= htmlspecialchars($errors['email'][0]) ?>
    </div>
<?php endif; ?>
```
