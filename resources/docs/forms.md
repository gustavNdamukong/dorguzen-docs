# Forms

Dorguzen provides two approaches for handling HTML forms: plain HTML forms with the CSRF helper, and `DGZ_Form` — a PHP form builder that generates HTML and manages old input/repopulation automatically.

---

## CSRF Protection

Every mutating form (`POST`, `PATCH`, `PUT`, `DELETE`) must include a CSRF token. The CSRF middleware validates it on every non-GET request.

### Plain HTML

```html
<form action="/contact" method="post">
    <input type="hidden" name="_csrf_token" value="<?= getCsrfToken() ?>">

    <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    <button type="submit">Send</button>
</form>
```

### DGZ_Form builder

`DGZ_Form::open()` automatically injects the `_csrf_token` hidden field — no manual work needed:

```php
use Dorguzen\Core\DGZ_Form;

<?php DGZ_Form::open('contactForm', '/contact', 'post') ?>
    <?php DGZ_Form::input('name', 'text', ['placeholder' => 'Your name']) ?>
    <?php DGZ_Form::submit('submit', 'Send') ?>
<?php DGZ_Form::close() ?>
```

---

## DGZ_Form Reference

| Method | Description |
|---|---|
| `DGZ_Form::open($name, $action, $method, $attributes)` | Opens `<form>` tag, injects CSRF token |
| `DGZ_Form::input($name, $type, $attributes, $value)` | `<input>` element |
| `DGZ_Form::hidden($name, $value)` | Hidden input |
| `DGZ_Form::label($targetField, $value, $attributes)` | `<label>` element |
| `DGZ_Form::checkbox($name, $value, $multiple, $attributes)` | Checkbox input |
| `DGZ_Form::radio($name, $value, $attributes, $preselected)` | Radio button |
| `DGZ_Form::select($name, $data, $preSelected, $multiple, $attributes)` | `<select>` element |
| `DGZ_Form::submit($type, $value, $attributes)` | Submit/reset/button input |
| `DGZ_Form::close()` | Closes `</form>` and clears old-input session state |

---

## Handling POST in a Controller

The standard controller pattern for form submission:

```php
public function store(): void
{
    if (isset($_POST['name'])) {
        // 1. Sanitize
        $name    = DGZ_Validate::fix_string($_POST['name']);
        $email   = DGZ_Validate::fix_string($_POST['email']);
        $message = DGZ_Validate::fix_string($_POST['message']);

        // 2. Validate
        $fail = $this->contactService->validateInput($name, $email, $message);

        if ($fail !== '') {
            // 3a. On failure — flash error, restore old input, redirect back
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

### Controller messaging methods

| Method | Session key | Rendered as |
|---|---|---|
| `$this->addErrors($msg)` | `_errors` | Bootstrap `alert-danger` |
| `$this->addWarning($msg)` | `_warnings` | Bootstrap `alert-warning` |
| `$this->addNotice($msg)` | `_notices` | Bootstrap `alert-info` |
| `$this->addSuccess($msg)` | `_success` | Bootstrap `alert-success` |

All four persist through the redirect via `$_SESSION` and are automatically rendered by the layout before clearing.

### Sticking old input

```php
$this->postBack($_POST);
```

Stores `$_POST` in `$_SESSION['postBack']`. After the redirect, retrieve values in your view:

```php
DGZ_Form::getOldValue('email')
```

---

## Old Input Repopulation

`DGZ_Form::getOldValue()` checks three session sources in order:

1. `$_SESSION['old_input']` — populated by JetForms validation middleware
2. `$_SESSION['postBack']` — populated by `$this->postBack($_POST)`
3. `$_SESSION['old_input_for_forms']` — populated by `DGZ_Form::setOld()`

Use it in form fields to repopulate after a failed submission:

```html
<input type="email" name="email"
       value="<?= htmlspecialchars(DGZ_Form::getOldValue('email', '')) ?>">
```

`DGZ_Form::close()` clears all three sources when the form is rendered again on the next page load.

---

## JetForms (Structured Form Validation)

JetForms is an advanced form system that moves validation out of the controller and into a dedicated form class. Validation runs automatically as middleware before the controller method is called.

### 1. Generate a form class

```bash
php dgz make:jetform ContactForm
```

Creates `src/JetForms/ContactForm.php`:

```php
namespace Dorguzen\JetForms;

use Dorguzen\Core\jetForms\JetForms;

class ContactForm extends JetForms
{
    protected string $name = 'contactForm';

    protected string $redirectTo = '/contact';

    protected array $rules = [
        'name'    => 'required|min:2|max:100',
        'email'   => 'required|email',
        'message' => 'required|min:10',
    ];

    protected array $messages = [
        'name.required'    => 'Your name is required.',
        'email.email'      => 'Please enter a valid email address.',
        'message.min'      => 'Message must be at least 10 characters.',
    ];
}
```

### 2. Register the form

In `configs/jetforms.php` (or wherever `JetFormsRegistry` reads from):

```php
return [
    'contactForm' => \Dorguzen\JetForms\ContactForm::class,
];
```

### 3. Add the hidden form name to your HTML form

The middleware identifies which form class to use via a hidden `_form_name` field:

```html
<form action="/contact" method="post">
    <input type="hidden" name="_csrf_token"  value="<?= getCsrfToken() ?>">
    <input type="hidden" name="_form_name"   value="contactForm">
    <input type="text"   name="name"         value="<?= DGZ_Form::getOldValue('name') ?>">
    <input type="email"  name="email"        value="<?= DGZ_Form::getOldValue('email') ?>">
    <button type="submit">Send</button>
</form>
```

### 4. What happens on submission

1. `FormValidationMiddleware` detects `_form_name` in `$_POST`
2. Resolves the registered form class from `JetFormsRegistry`
3. Calls `$form->fill($payload)` then `$form->validate()`
4. On **failure**: writes `$_SESSION['old_input']` and `$_SESSION['validation_errors']`, throws `ValidationException` — the router catches it, flashes errors via `addErrors()`, and redirects to `$form->redirectTo`
5. On **success**: writes validated data to `$_SESSION['old_input']`, proceeds to the controller method

Your controller method only runs when validation passes:

```php
public function store(): void
{
    // By the time we're here, validation has already passed
    $name    = DGZ_Validate::fix_string($_POST['name']);
    $email   = DGZ_Validate::fix_string($_POST['email']);
    $message = DGZ_Validate::fix_string($_POST['message']);

    $this->contactService->save($name, $email, $message);
    $this->addSuccess('Message sent.');
    $this->redirect('contact');
}
```

---

## File Uploads in Forms

For forms with file uploads, add `enctype="multipart/form-data"` to the opening tag:

```html
<form action="/profile/photo" method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf_token" value="<?= getCsrfToken() ?>">
    <input type="file"   name="photo">
    <button type="submit">Upload</button>
</form>
```

Process the upload in the controller with `DGZ_Uploader`. See [File Uploads](/docs/file-uploads) for the full upload API.

---

## AJAX Forms

For AJAX form submissions, send the CSRF token as a header instead of a hidden field:

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

Output the token in the layout `<head>`:

```html
<meta name="csrf-token" content="<?= getCsrfToken() ?>">
```

For REST API endpoints, CSRF is exempted via `APP_API_CSRF_EXCEPTION='/api/'` — no token needed. See [REST API](/docs/rest-api).
