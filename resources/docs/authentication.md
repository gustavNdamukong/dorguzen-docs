# Authentication

Dorguzen ships with a complete authentication system covering registration, email verification, login, session management, role-based access control, and password reset — all wired up out of the box.

---

## User Roles

Every user has a `users_type` column with one of four roles:

| Role | Description |
|---|---|
| `member` | Regular registered user. Default role at registration. |
| `admin` | Regional/location manager. Moderate content in assigned areas. Cannot manage other admins. |
| `admin_gen` | General administrator. Full access, can manage admin-level users. |
| `super_admin` | Unrestricted access. Can manage all users and perform platform-wide operations. |

---

## The Auth() Helper

`Auth()` returns the singleton `DGZ_Auth` instance, which always reflects the current database state for the authenticated user.

```php
Auth()->check()              // bool — true if logged in
Auth()->guest()              // bool — true if not logged in
Auth()->id()                 // ?int — current user's PK
Auth()->user()               // ?object — full Users model for current user
Auth()->username()           // ?string
Auth()->userType()           // ?string — e.g. 'admin_gen'
Auth()->isAdmin()            // bool — true if admin, admin_gen, or super_admin
Auth()->isType('super_admin') // bool — exact role match
Auth()->can('seo')           // bool — role is in the 'seo' permissions array
Auth()->isEmailVerified()    // bool
Auth()->logout()             // void — destroys session, clears cookies
```

```php
// Bounce unauthenticated visitors
if (Auth()->guest()) {
    $this->redirect('auth', 'login');
    return;
}

// Restrict to admin-tier
if (!Auth()->isAdmin()) {
    $this->redirect('home');
    return;
}

// In a view
<?php if (Auth()->isAdmin()): ?>
    <a href="...admin/dashboard">Admin Panel</a>
<?php endif; ?>
```

---

## Feature-Level Permissions

Fine-grained permission gates defined in `configs/app.php`:

```php
'permissions' => [
    'seo'          => ['admin', 'admin_gen', 'super_admin'],
    'manage_users' => ['admin', 'admin_gen', 'super_admin'],
    'settings'     => ['super_admin'],
],
```

`Auth()->can('seo')` returns `true` if the current user's role appears in that array.

---

## Session Keys Written at Login

| Key | Value |
|---|---|
| `$_SESSION['authenticated']` | `'Let Go-{appName}'` |
| `$_SESSION['custo_id']` | User's numeric PK |
| `$_SESSION['user_type']` | Role string |
| `$_SESSION['first_name']`, `last_name`, `email` | User details |
| `$_SESSION['start']` | Unix timestamp of login |

The session ID is regenerated via `session_regenerate_id()` after login to prevent session fixation.

---

## Authentication Flows

### Registration

1. `POST /auth/register` — sanitizes input, checks honeypot field for bots
2. Validates all fields and password strength (min 6 characters)
3. Saves user with `users_emailverified = 'no'` and a random `users_eactivationcode`
4. Sends activation email; shows instructions to check inbox

Registration can be disabled: `ALLOW_REGISTRATION=false` in `.env`.

### Email Verification

1. User clicks link: `GET /auth/verifyEmail?em={code}`
2. Sets `users_emailverified = 'yes'`, clears `users_eactivationcode`
3. Redirects to login

### Login

1. `POST /auth/doLogin` with `login_email` and `login_pwd`
2. Validates input, authenticates against AES-encrypted password
3. Writes session keys, regenerates session ID
4. Sets `rem_me` cookie (4 days) if "remember me" checked
5. Logs admin-tier logins to the `logs` table

### Forgot Password / Reset

1. User submits email on login form with `forgotstatus=yes`
2. Reset code generated, stored in `password_reset` table, emailed
3. User clicks link: `GET /auth/reset?em={code}` (expires in 2 hours)
4. `POST /auth/resetPw` — updates hashed password
5. Reset record deleted immediately on retrieval (links are single-use)

### Logout

Clears `$_SESSION`, destroys session, removes `rem_me` cookie, redirects home.

---

## Protecting Routes

### Method 1 — BaseMiddleware (controller-wide)

Add the controller short-name to `BaseMiddleware::boot()`:

```php
'admin' => 'authenticated',
```

All requests to any `/admin/*` URL now require a valid session.

### Method 2 — Route Middleware Groups

```php
$router->middleware(['auth'])->group(function() use ($router) {
    $router->get('/user/dashboard', 'UserController@dashboard');
});
```

### Method 3 — Inline Auth() checks

```php
public function sensitiveAction(): void
{
    if (Auth()->guest()) {
        $this->redirect('auth', 'login');
        return;
    }
    if (!Auth()->can('manage_users')) {
        $this->redirect('home');
        return;
    }
}
```

---

## Admin Views

Admin view files in `views/admin/` extend `DGZ_AdminHtmlView`. This base class performs a session-level guard check when the view is initialised — unauthenticated visitors are redirected to `auth/login` before any content renders.

---

## AuthService

`AuthService` owns all database operations and business logic. The controller handles view rendering, reading `$_POST`, session keys, and redirects. `AuthService` never touches `$_POST`, `$_SESSION`, or HTTP headers directly.

Key methods: `registerNewUser()`, `authenticateUser()`, `activateUserEmail()`, `fetchAndConsumePasswordReset()`, `resetUserPassword()`, `logAdminLogin()`.
