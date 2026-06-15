# Validation

Dorguzen provides two validation systems: the legacy `DGZ_Validate` class for quick field checks, and the modern `DGZ_Validator` for rule-based validation. Both are also complemented by `DGZ_CheckPassword` for password strength enforcement.

---

## DGZ_Validator (Modern, Rule-Based)

`DGZ_Validator` supports Laravel-style pipe rules, custom messages, and closures.

```php
use Dorguzen\Core\DGZ_Validator;

$validator = DGZ_Validator::make(
    data:     $_POST,
    rules:    [
        'firstname' => 'required|min:2|max:50',
        'email'     => 'required|email',
        'age'       => 'required|integer|between:18,120',
        'password'  => 'required|min:6',
        'confirm'   => 'required|same:password',
    ],
    messages: [
        'firstname.required' => 'First name is required.',
        'email.email'        => 'Please enter a valid email address.',
        'confirm.same'       => 'Passwords do not match.',
    ]
);

if ($validator->fails()) {
    foreach ($validator->errors() as $field => $messages) {
        // $messages is an array of error strings for that field
    }
}
```

### Rule Reference

| Rule | Description |
|---|---|
| `required` | Field must be present and non-empty |
| `nullable` | Field may be null or absent — skip other rules if absent |
| `sometimes` | Only validate if the field is present |
| `string` | Must be a string |
| `integer` / `int` | Must be an integer |
| `numeric` | Must be numeric (int or float) |
| `boolean` | Must be boolean-ish (`true`, `false`, `1`, `0`, `'1'`, `'0'`) |
| `email` | Must match a valid email format |
| `url` | Must be a valid URL |
| `min:N` | String: min N chars. Number: minimum value N |
| `max:N` | String: max N chars. Number: maximum value N |
| `between:N,M` | Number must be between N and M (inclusive) |
| `regex:/pattern/` | Must match the given regex |
| `in:a,b,c` | Value must be in the comma-separated list |
| `not_in:a,b,c` | Value must not be in the list |
| `same:other_field` | Must equal the value of `other_field` |
| `different:other_field` | Must differ from `other_field` |
| `date` | Must be a valid date string |
| `before:date` | Must be a date before the given date |
| `after:date` | Must be a date after the given date |
| `array` | Must be an array |
| `present` | Key must be present (value may be empty) |
| `callback` | Run a custom closure (see below) |

### Rules as arrays

```php
'age' => ['required', 'integer', 'between:18,120']
```

### Custom closure rule

```php
'username' => [
    'required',
    function ($value, $fail) {
        if (str_contains($value, ' ')) {
            $fail('Username must not contain spaces.');
        }
    },
]
```

### Custom messages

Keys follow `'field.rule'` format:

```php
$messages = [
    'email.required' => 'We need your email address.',
    'email.email'    => 'That does not look like an email.',
    'age.between'    => 'You must be between 18 and 120 years old.',
];
```

### In a controller

```php
$validator = $this->validator($_POST, [
    'name'  => 'required|min:2',
    'email' => 'required|email',
]);

if ($validator->fails()) {
    $errors = implode('<br>', array_merge(...array_values($validator->errors())));
    $this->addErrors($errors);
    $this->postBack($_POST);
    $this->redirect('contact');
    return;
}
```

`$this->validator()` is a shorthand on `DGZ_Controller` that calls `DGZ_Validator::make()`.

---

## DGZ_Validate (Legacy, Field-by-Field)

`DGZ_Validate` provides individual validation methods, each returning an HTML error string (empty string on pass). Still commonly used in service classes.

```php
use Dorguzen\Core\DGZ_Validate;

$val  = new DGZ_Validate();
$fail = '';

$fail .= $val->validate_firstname($firstname);
$fail .= $val->validate_surname($surname);
$fail .= $val->validate_email($email);
$fail .= $val->validate_password($password);

if ($fail !== '') {
    $this->addErrors($fail);
    $this->redirect('register');
    return;
}
```

### Available methods

| Method | Validates |
|---|---|
| `validate_firstname($v)` | Required |
| `validate_surname($v)` | Required |
| `validate_username($v)` | Required, min 5 chars, alphanumeric + `-_` |
| `validate_password($v)` | Required, min 6 chars |
| `validate_email($v)` | Required, valid email format |
| `validate_phonenumber($v)` | Required, numeric |
| `validate_age($v)` | Between 18 and 110 |
| `validate_shop_name($v)` | Required, alphanumeric + `-_` |

### Sanitising input before validation

Always sanitize user input with `DGZ_Validate::fix_string()` before validating or storing:

```php
$email = DGZ_Validate::fix_string($_POST['email']);
// Applies: stripslashes → trim → htmlentities
```

---

## DGZ_CheckPassword (Password Strength)

Used in addition to `validate_password()` for enforcing strength requirements:

```php
use Dorguzen\Core\DGZ_CheckPassword;

$checkPwd = new DGZ_CheckPassword($password, minimumLength: 8);
$checkPwd->requireMixedCase();
$checkPwd->requireNumbers(1);
$checkPwd->requireSymbols(1);

if (!$checkPwd->check()) {
    foreach ($checkPwd->getErrors() as $error) {
        $fail .= $error;
    }
}
```

| Method | Effect |
|---|---|
| `new DGZ_CheckPassword($password, $minLength)` | Constructor — default min length 6 |
| `requireMixedCase()` | Must contain upper and lowercase letters |
| `requireNumbers($n)` | Must contain at least N digits |
| `requireSymbols($n)` | Must contain at least N non-alphanumeric chars |
| `check()` | Returns `bool` — failures populate `getErrors()` |
| `getErrors()` | Array of human-readable error strings |

---

## JetForms Validation (Middleware-Based)

JetForms moves validation out of the controller entirely. The `FormValidationMiddleware` intercepts the request, validates it using the registered form class's `$rules`, and only passes to the controller if validation passes.

```php
// src/JetForms/RegistrationForm.php
class RegistrationForm extends JetForms
{
    protected string $name       = 'registrationForm';
    protected string $redirectTo = '/register';

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
}
```

On validation failure, the middleware:
1. Stores field errors in `$_SESSION['validation_errors']`
2. Stores submitted values in `$_SESSION['old_input']`
3. Throws `ValidationException` — the router catches it, flashes errors, and redirects to `$redirectTo`

The controller method only runs when all rules pass.

See [Forms](/docs/forms) for the full JetForms setup walkthrough.

---

## Displaying Validation Errors

Errors are rendered automatically via the `ErrorsListView` — you do not need to add error-display code to views. After a redirect, `$this->addErrors($message)` stores them in `$_SESSION['_errors']`, and the layout reads and renders them as Bootstrap `alert-danger` divs before clearing the session key.

If you need to display field-level errors inline (next to each field), read `$_SESSION['validation_errors']` directly in your view:

```php
<?php $errors = $_SESSION['validation_errors'] ?? []; ?>

<input type="email" name="email" value="<?= DGZ_Form::getOldValue('email') ?>">
<?php if (!empty($errors['email'])): ?>
    <div class="invalid-feedback d-block">
        <?= htmlspecialchars($errors['email'][0]) ?>
    </div>
<?php endif; ?>
```

---

## Validation in API Controllers

For REST API endpoints, validate the JSON body using `DGZ_Validator::make()` directly, then return a structured JSON error:

```php
public function store(): void
{
    $this->setHeaders();
    $this->validateToken();
    if (!$this->validatedToken) { exit(); }

    $body = request()->json();

    $validator = DGZ_Validator::make($body, [
        'name'  => 'required|string|min:2',
        'price' => 'required|numeric',
    ]);

    if ($validator->fails()) {
        $response = new DGZ_Response();
        $response->json([
            'code'    => 422,
            'status'  => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422)->send();
        return;
    }

    // proceed
}
```
