# Authentication

This refers to the ways in which your computer program will try to identify and control who has access to your application.

---

## User Roles and View-Level Authentication

Dorguzen ships with a four-tier role system built into the users table. Every registered user has a `users_type` column whose value is one of:

| Role | Description |
|---|---|
| `member` | A regular registered user of the application. The default role assigned at registration. Can access their own account pages but has no admin capabilities. |
| `admin` | A regional/location manager. Typically responsible for moderating content (ads, orders, etc.) within one or more assigned geographic locations. Cannot manage other admins. |
| `admin_gen` | A general administrator. Has full access to all regions and can manage admin-level users. Cannot manage super_admin accounts. |
| `super_admin` | Full unrestricted access. The only role that can manage general admins, delete users of any type, and perform irreversible platform-wide operations. |

---

## Public registration & the default member role

Self-service registration from the public site is a deployment-level toggle, set by ALLOW_REGISTRATION in .env and read in code as `config('app.allow_registration')`. When it is true the registration page (auth/signup) and its POST handler (`AuthController::register()`) are both active; when it is false BOTH redirect to the home page, so the feature is fully off. It is intentionally a developer/deployment decision — there is no admin UI toggle for it at runtime.

Every account created through public registration is given `users_type = 'member'`, the lowest tier. A member can log in and reach their own dashboard, but has no admin capabilities — DGZ_AdminHtmlView turns a member away from any views/admin/ page.

---

## Changing a user's role

A users_type value is never changed by self-service. It can only be changed in one of two places:

1. The admin UI — Admin Dashboard -> Manage Users (admin/manageUsers). Access to this feature is governed by the 'manage_users' entry in the configs/app.php 'permissions' map, which ships allowing admin, admin_gen and super_admin (only the 'settings' feature is super_admin-only). The role hierarchy described above still applies inside the screen — e.g. an admin_gen cannot manage a super_admin.

2. Directly in the database — by updating the users.users_type column.

There is deliberately no public or member-facing path for a user to promote themselves.

---

## The member dashboard vs the admin dashboard

Members get their OWN dashboard, separate from the admin backend:

```
user/dashboard    UserController::dashboard() -> views/dashboard.php
                  Extends DGZ_HtmlView and is guarded by Auth()->check(),
                  so any logged-in user (including a member) can reach it.

admin/dashboard   AdminController::dashboard() -> views/admin/adminHome.php
                  Extends DGZ_AdminHtmlView, so only admin / admin_gen /
                  super_admin can reach it.
```

Out of the box the member dashboard ships with a single card — Change Password (user/changePw) — and nothing else. This is intentionally minimal: a clone application is expected to build out the member area (profile, orders, saved items, etc.) on top of it.

---

## How roles are stored

When a user logs in via `Auth()->login()` or `AuthController::doLogin()`, the following session keys are written:

| Session key | Description |
|---|---|
| `$_SESSION['authenticated']` | `'Let Go-{appName}'` — presence proves login |
| `$_SESSION['start']` | Unix timestamp of login time |
| `$_SESSION['custo_id']` | the user's numeric primary key |
| `$_SESSION['user_type']` | one of the four role strings above |
| `$_SESSION['username']` | the user's username |
| `$_SESSION['email']` | the user's email address |
| `$_SESSION['first_name']` | |
| `$_SESSION['last_name']` | |
| `$_SESSION['google_id']` | populated if user registered via Google OAuth |
| `$_SESSION['phone_number']` | |
| `$_SESSION['mm_account']` | mobile money account number, if set |
| `$_SESSION['emailverified']` | `'yes'` \| `'no'` |
| `$_SESSION['created']` | account creation timestamp |

Admin logins (any role other than member) are additionally written to the logs table automatically.

Note: $_SESSION['user_type'] is written at login and stays unchanged for the duration of the session. DGZ_Auth does NOT rely on it for role checks — isAdmin(), hasRole(), can(), and role() all read from the Users model that is loaded fresh from the database on every request (see above). This means if a user's type is changed in the DB, Auth() reflects it immediately on their next request without requiring a re-login. $_SESSION['user_type'] is used only by DGZ_AdminHtmlView::guardAdminAccess() and is available as a convenience for raw session reads in edge cases.

---

## The Auth() helper — full method reference

`Auth()` returns the singleton `DGZ_Auth` instance. It is available globally in controllers and views via the `Auth()` helper function (bootstrap/helpers.php).

IMPORTANT — Auth() reads from the Users model, not from $_SESSION['user_type']. At construction, DGZ_Auth calls loadUserFromSession(), which loads a fresh Users model from the database using $_SESSION['custo_id']. All role and identity methods (isAdmin, hasRole, can, role, username, id) read from that model object. This means Auth() always reflects the current database state, not the session snapshot set at login time. DGZ_AdminHtmlView::guardAdminAccess() is the only place that reads $_SESSION['user_type'] directly — it does so intentionally, before DGZ_Auth is available in the view lifecycle.

| Method | Returns | Description |
|---|---|---|
| `Auth()->check()` | bool | true if a user is logged in (model loaded successfully from DB) |
| `Auth()->guest()` | bool | true if NO user is logged in |
| `Auth()->id()` | ?int | the current user's primary key, or null |
| `Auth()->user()` | ?object | the full Users model object for the current user, or null if not logged in |
| `Auth()->username()` | ?string | the current user's username, or null |
| `Auth()->userType()` | ?string | the current user's tier string (e.g. 'admin_gen'), or null if not logged in |
| `Auth()->isAdmin()` | bool | true if tier is admin, admin_gen, or super_admin; false if not logged in |
| `Auth()->isType($type)` | bool | true if the user's tier exactly matches $type; false if not logged in |
| `Auth()->can($feature)` | bool | true if the current user's tier is in the allowed list for $feature in the configs/Config.php 'permissions' map; false if not logged in or feature not mapped |
| `Auth()->permissions()` | array | all feature aliases accessible to the current user's tier; empty array if not logged in |
| `Auth()->hasRoles()` | array | convenience alias for permissions() |
| `Auth()->isEmailVerified()` | bool | true if users_emailverified == 'yes' |
| `Auth()->login($u,$p,$rememberMe)` | bool | attempt login; $rememberMe sets a 4-day cookie; returns true on success |
| `Auth()->logout()` | void | destroy session, clear cookies, redirect to auth/login |

Examples:

```php
// In a controller — bounce non-logged-in visitors
if (Auth()->guest()) {
    $this->redirect('auth', 'login');
}

// Only let admins proceed
if (!Auth()->isAdmin()) {
    $this->redirect('home');
}

// Check an exact user tier
if (Auth()->isType('super_admin')) {
    // only super_admin reaches here
}

// Check feature-level permission (config-driven)
if (!Auth()->can('seo')) {
    $this->redirect('home');
    return;
}

// Get all features the current user can access
$features = Auth()->permissions();  // e.g. ['seo', 'manage_users']
$features = Auth()->hasRoles();     // same result — convenience alias

// In a view — show a link only to admins
<?php if (Auth()->isAdmin()): ?>
    <a href="<?= $this->controller->config->getFileRootPath() ?>user/dashboard">
        Admin Panel
    </a>
<?php endif; ?>
```

---

## The views/admin/ directory and DGZ_AdminHtmlView

All view files that render admin backend pages live in views/admin/ and extend `\Dorguzen\Core\DGZ_AdminHtmlView` instead of the usual `\Dorguzen\Core\DGZ_HtmlView`.

The distinction matters:

```
views/                       Accessible to any visitor (logged in or not)
└── home.php                 Public pages extend DGZ_HtmlView
└── details.php
└── ...

views/admin/                 Admin-only pages — extend DGZ_AdminHtmlView
└── allAds.php
└── manageUsers.php
└── goldUsers.php
└── ...
```

---

## How the guard works

`DGZ_AdminHtmlView` overrides `setContext()`, the method the framework calls to inject the current controller into every view before `show()` is called. This makes it the earliest safe point at which config (and therefore the app name) is available — without it, the session token comparison cannot be made.

```php
class DGZ_AdminHtmlView extends DGZ_HtmlView
{
    private const ADMIN_TYPES = ['admin', 'admin_gen', 'super_admin'];

    public function setContext(DGZ_Controller &$pageController): void
    {
        parent::setContext($pageController);   // store the controller
        $this->guardAdminAccess();             // immediately run the guard
    }

    private function guardAdminAccess(): void
    {
        $expectedToken   = 'Let Go-' . $this->controller->config->getConfig()['appName'];
        $isAuthenticated = isset($_SESSION['authenticated'])
                           && $_SESSION['authenticated'] === $expectedToken;
        $isAdmin         = isset($_SESSION['user_type'])
                           && in_array($_SESSION['user_type'], self::ADMIN_TYPES, strict: true);

        if ($isAuthenticated && $isAdmin) { return; }

        // Not authorised — redirect to login and halt all further execution.
        header('Location: ' . $this->controller->config->getFileRootPath() . 'auth/login');
        exit;
    }
}
```

The guard checks two things:

1. $_SESSION['authenticated'] equals 'Let Go-{appName}' — proves the user completed a real login, not just set the session key manually.
2. $_SESSION['user_type'] is admin, admin_gen, or super_admin — proves the logged-in user actually has admin rights. A regular member who is authenticated will still be redirected.

If either check fails the visitor is sent to auth/login immediately. No HTML from the view's show() method is rendered at all.

---

## Which views belong in views/admin/

Place a view in views/admin/ (and have it extend DGZ_AdminHtmlView) when:

- The page is part of the backend CMS / admin dashboard
- It displays sensitive data (all users, all orders, transactions, logs)
- It lets the user perform privileged actions (delete ads, manage users, change settings, approve gold memberships, etc.)

Keep a view in views/ (extending DGZ_HtmlView) when:

- The page is part of the public-facing front end
- It is accessible to guests or regular members
- Any finer-grained access control inside that view is done inline using Auth() checks or $_SESSION['user_type'] comparisons

NOTE: having views/admin/ as a separate directory is itself a readability and maintainability benefit — it visually separates the admin CMS from the rest of the application, making it immediately clear to any developer which files power the backend.

---

## Role-based access — tips and patterns

### 1. Restricting controller actions by role

The cleanest place to enforce role checks is at the top of a controller method, before any data is fetched. Always return (or exit) after calling redirect() so no further code in the method runs:

a) Feature-level check (recommended for module/feature access):

```php
public function index(): void
{
    if (!Auth()->can('seo')) {
        $this->redirect('home');
        return;
    }
    // safe to proceed — current user's type is in the 'seo' allowed list
}
```

b) Admin-or-above check (any of the three admin roles):

```php
public function dashboard(): void
{
    if (!Auth()->isAdmin()) {
        $this->redirect('auth', 'login');
        return;
    }
    // ...
}
```

c) Exact tier check (one specific tier only):

```php
public function deleteUser(int $userId): void
{
    if (!Auth()->isType('super_admin')) {
        $this->redirect('home');
        return;
    }
    // only super_admin reaches here
}
```

d) Multiple tiers without a feature map entry:

```php
public function manageRegion(): void
{
    $type = Auth()->userType();
    if ($type !== 'admin_gen' && $type !== 'super_admin') {
        $this->redirect('home');
        return;
    }
    // ...
}
```

Rule of thumb: use can() when the access rule is feature-based and may apply to several tiers; use isType() when the rule is strictly tied to one exact tier; use isAdmin() when any admin tier is sufficient.

### 2. Showing / hiding UI elements by role

In a view, use Auth() to conditionally render controls:

```php
<?php if (Auth()->isType('super_admin')): ?>
    <a href="..." class="btn btn-danger">Delete User</a>
<?php elseif (Auth()->isAdmin()): ?>
    <a href="..." class="btn btn-primary" disabled>Delete User</a>
    <small>Super admin only</small>
<?php endif; ?>

<?php if (Auth()->can('seo')): ?>
    <a href="<?= $root ?>seo">SEO Manager</a>
<?php endif; ?>
```

Prefer Auth() over reading $_SESSION['user_type'] directly. The session value is a snapshot set at login time; Auth() reads from the Users model loaded fresh from the database on every request, so it always reflects the current state.

### 3. The middleware alternative (route groups)

The DGZ_AdminHtmlView guard described above works well for auto-discovery routes (i.e. URLs that the framework resolves automatically without an explicit route definition). For defined routes in routes/web.php, the architecturally preferred approach is a route middleware group:

```php
// routes/web.php
$router->middleware(['auth', 'admin'])->group(function () use ($router) {
    $router->get('/admin/users',       'AdminController@manageUsers');
    $router->get('/admin/deleteAd',    'AdminController@deleteAd');
    $router->get('/admin/goldUsers',   'UserController@goldUsers');
    // ... all admin routes
});
```

With this approach the middleware runs before the controller is even instantiated, and the admin views can safely extend the plain DGZ_HtmlView because the middleware has already guaranteed that only admins reach them.

The two approaches are not mutually exclusive — you can use middleware on defined routes AND DGZ_AdminHtmlView on auto-discovery admin views. Having both ensures that no admin view is ever accidentally reachable without a valid admin session, regardless of how the URL is resolved.

### 4. Feature-level permissions — Auth()->can()

For features that cut across user types (e.g. only certain roles may access the SEO module, regardless of whether the page is "admin" or not), Dorguzen provides a config-driven permissions map.

The map lives in configs/Config.php under the 'permissions' key:

```php
'permissions' => [
    'seo'          => ['admin', 'admin_gen', 'super_admin'],
    'payments'     => ['admin_gen', 'super_admin'],
    'manage_users' => ['admin', 'admin_gen', 'super_admin'],
    'settings'     => ['super_admin'],
],
```

Each key is a feature alias. The value is the list of user types allowed to access it. To add a new feature, add a key/array pair here.

Checking access anywhere in the application:

```php
Auth()->can('seo')         // true  if the logged-in user's type
                           //       is in the 'seo' allowed list
Auth()->can('settings')    // true  only for super_admin
```

If the user is not logged in, can() always returns false. If the feature key does not exist in the map, can() returns false.

Guarding a controller action:

```php
public function index(): void
{
    if (!Auth()->can('seo')) {
        $this->redirect('home');
        return;
    }
    // ... render the page
}
```

Guarding inside a view:

```php
<?php if (Auth()->can('seo')): ?>
    <!-- SEO module content -->
<?php else: ?>
    <h3>You do not have permission to access this page.</h3>
<?php endif; ?>
```

Method distinctions:

```
isType($type)    — raw tier check: is this user's tier exactly $type?
can($feature)    — feature check: is this user's tier in the allowed
                   list for $feature?
permissions()    — returns all feature aliases this user's tier can access
hasRoles()       — convenience alias for permissions()
```

---

## Controller-wide protection — BaseMiddleware::boot()

In addition to the DGZ_AdminHtmlView guard and the route-group middleware described above, an entire controller can be gated by adding its short-name to the map returned by `BaseMiddleware::boot()` (middleware/globalMiddleware/BaseMiddleware.php):

```php
public function boot(): array
{
    return [
        'admin' => 'authenticated',
        // ... 'divert' and 'isActiveModule' entries for other controllers
    ];
}
```

Any request whose controller short-name matches a key is intercepted before the controller method runs. The `'authenticated'` intent requires a valid session — `BaseMiddleware::authenticated()` checks that `$_SESSION['authenticated'] === 'Let Go-{appName}'` — and redirects unauthenticated visitors to `admin/login`. Public methods such as `login` are whitelisted so the login page itself stays reachable. The same `boot()` map also drives `'divert'` re-routing and `'isActiveModule'` module-activation checks for other controllers.

---

## Authentication Flows

The flows below are handled by `AuthController` (src/controllers/AuthController.php); all database work and business logic is delegated to `AuthService` (see below).

### Registration

1. The public registration page is `auth/signup` (`AuthController::signup()`); the form POSTs to `AuthController::register()`. Both are gated by `config('app.allow_registration')` (the `ALLOW_REGISTRATION` env toggle) — when off, both redirect to home.
2. `register()` sanitizes input, requires the Terms & Conditions checkbox, and rejects bots via a honeypot field (`captcha_hidden`): a filled honeypot is logged via `AuthService::logBotAttempt()` and bounced to home.
3. Validates the fields (`AuthService::validateRegistrationInput()`) and enforces a unique email (`AuthService::emailExists()`).
4. Saves the user via `AuthService::registerNewUser()` with `users_type = 'member'`, `users_emailverified = 'no'`, and a random activation code (`md5(uniqid(...))`).
5. Emails an activation link containing `auth/verifyEmail?em={code}`, then redirects to `auth/emailActivationInstructions`.

### Email Verification

1. User clicks the link: `auth/verifyEmail?em={code}` (`AuthController::verifyEmail()`).
2. The code is looked up (`AuthService::getUserByActivationCode()`); `AuthService::activateUserEmail()` sets `users_emailverified = 'yes'` and clears the activation code.
3. Redirects to `auth/login`.

### Login

1. POST to `auth/doLogin` (`AuthController::doLogin()`) with `login_email` and `login_pwd`, plus an optional `rem_me` checkbox.
2. Validates input (`AuthService::validateLoginInput()`) and authenticates (`AuthService::authenticateUser()`).
3. On success, writes the session keys and calls `session_regenerate_id()` to prevent session fixation.
4. For admin-tier users (admin / admin_gen / super_admin) the login is recorded to the `logs` table via `AuthService::logAdminLogin()`.
5. If `rem_me` was checked, sets a `rem_me` cookie valid for 4 days (345600s; HttpOnly, SameSite=Lax, Secure when on HTTPS).
6. Redirects admins to `admin/dashboard` and members to `user/dashboard` (or to a stored `referBack` target).

The same flow is available programmatically via `Auth()->login($u, $p, $rememberMe)`.

### Forgot Password / Reset

1. The login form submits to `doLogin` with `forgotstatus=yes`, routed internally to `handleForgotPassword()`.
2. A single-use reset code is stored in the `password_reset` table (`AuthService::savePasswordResetRecord()`) and emailed.
3. User clicks the link: `auth/reset?em={code}` (`AuthController::reset()`). The record is consumed via `AuthService::fetchAndConsumePasswordReset()` (deleted on retrieval — links are single-use) and rejected if older than 2 hours.
4. POST `auth/resetPw` (`AuthController::resetPw()`) updates the password via `AuthService::resetUserPassword()`.

### Logout

`AuthController::logout()` (and `Auth()->logout()`) empties `$_SESSION`, deletes the session cookie and the `rem_me` cookie, and destroys the session.

---

## AuthService

`AuthController` is intentionally thin — it reads `$_POST`, manages `$_SESSION` keys, sets flash messages and issues redirects. All database operations and business logic live in `\Dorguzen\Services\AuthService`, which never touches `$_POST`, `$_SESSION` or HTTP headers directly. Key methods:

- `registerNewUser()` — persist a new member account
- `authenticateUser()` — verify email + password, return the user row or false
- `activateUserEmail()` / `getUserByActivationCode()` — email verification
- `fetchAndConsumePasswordReset()` — read and delete a single-use reset record
- `resetUserPassword()` — update a user's password during reset
- `logAdminLogin()` — record an admin-tier login to the `logs` table
- plus the validation helpers (`validateRegistrationInput()`, `validateLoginInput()`, `validateForgotPasswordInput()`, `validatePasswordResetInput()`), `emailExists()`, `savePasswordResetRecord()`, `getUserForPasswordReset()` and `logBotAttempt()`.
