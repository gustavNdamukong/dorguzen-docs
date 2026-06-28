# Testing

## How to Write Values to Console During PHPUnit Tests

Do a `try {}` and catch block, and in the catch block, throw a `RuntimeException` exception like this:

```php
throw new \RuntimeException(
    'Config WAS loaded successfully in bootstrap/testing.php Config dump: ' .
    json_encode($this->config->getConfig())
);
```

---

## PHPUnit Testing

### PHPUnit Testing in Dorguzen

This document explains how PHPUnit works inside Dorguzen, not how PHPUnit works in general.

Dorguzen's testing system is intentionally explicit, predictable, and framework-aware. There is no hidden magic. Every test boots exactly what it depends on, and nothing more.

By the end of this section, you will understand:

- How Dorguzen boots the test environment
- How HTTP and feature tests are dispatched
- How request and response state is isolated
- How authentication is simulated in tests
- What helpers and assertions are available
- How to write clean, reliable tests for your application

---

### Testing Philosophy in Dorguzen

Dorguzen follows three core testing principles:

- Tests must bootstrap what they depend on
- Each test runs in a clean request-response lifecycle
- No global state leakage between tests

This means:

- No shared request objects
- No shared response objects
- No "magic" authentication state
- No reliance on test order

If a test passes, it passes for the right reason.

---

### Test Entry Point

`bootstrap/testing.php`

This file is the entry point for all tests.

It is responsible for:

- Loading environment variables for testing
- Booting framework configuration
- Preparing Dorguzen for parallel-safe execution

⚠️ Tests do not use `bootstrap/app.php`

Instead, all tests boot through `bootstrap/testing.php`, which ensures:

- The correct `.env.testing` configuration is used
- No application state leaks from development or production

This file is loaded inside the base `TestCase` during setup.

---

### Base Test Case

`src/Testing/TestCase.php`

All tests extend this class.

Responsibilities:

- Bootstraps the test environment
- Loads `bootstrap/testing.php`
- Resets framework state between tests
- Registers core testing traits

Every test you write ultimately inherits from this class.

---

### Database Resetting

`src/Testing/RefreshDatabase.php`

This trait provides database isolation between tests by running `migrate:fresh` once per test suite.

What it does:

- Drops all application tables
- Re-runs all migrations against the test database
- Gives every test run a clean, fully-migrated schema

This is wired into the base `TestCase` automatically — you do not need to add anything to your test class. The reset happens exactly once per PHP process (the first time any test boots the framework), and the resulting clean schema is shared across all tests in that run.

```php
class UserTest extends TestCase
{
    use RefreshDatabase;

    // tests...
}
```

---

### HTTP Testing System

Dorguzen's HTTP testing system mirrors a real request lifecycle — but runs entirely in memory.

#### Core HTTP Testing Classes

```
src/Testing/http/
├── DispatchesHttpRequests.php
├── InteractsWithHttp.php
├── KernelResponse.php
├── TestInputStream.php
├── TestResponse.php
```

Each has a clearly defined responsibility.

#### Dispatching HTTP Requests

`DispatchesHttpRequests` (trait)

This trait provides the entry point for HTTP tests.

It exposes helpers like:

```php
$this->get('/ping');
$this->post('/echo', ['message' => 'Hello']);
$this->http('POST', '/login', [...]);
```

Internally, these helpers:

- Prepare the request
- Bootstrap the HTTP kernel
- Dispatch the request
- Capture the response
- Return a `TestResponse` instance

#### HTTP Glue Layer

`InteractsWithHttp` (trait)

This trait acts as the glue between PHPUnit and Dorguzen's HTTP layer.

It:

- Prepares request headers
- Handles JSON payloads
- Injects the test input stream
- Returns a fluent `TestResponse` object

You never call this directly — it powers the higher-level helpers.

#### Input Stream Handling

`TestInputStream`

This class wraps `php://input` for testing.

Why this exists:

- `php://input` is read-once
- JSON requests need predictable input
- Tests must not interfere with each other

Each HTTP test gets a fresh input stream, ensuring isolation.

#### Kernel Dispatch for Testing

`dispatchForTesting()`

The HTTP kernel exposes a dedicated testing entry point.

During each test request:

- The request object is reset
- The response object is reset
- Routes are loaded explicitly
- Headers and status codes are cleared
- Output buffering is used to capture response body

This guarantees:

- One test = one clean request-response lifecycle

#### Response Handling

`KernelResponse`

This is a low-level container that holds:

- HTTP status code
- Headers
- Raw response body

It is converted into a `TestResponse` for assertions.

#### TestResponse

`src/Testing/http/TestResponse.php`

This is what your tests interact with.

Available assertions include:

```php
$response->assertStatus(200);
$response->assertJson(['status' => 'ok']);
$response->assertJsonPath('data.email', 'test@example.com');
```

The response object parses JSON automatically and provides helpful error output when assertions fail.

---

### JSON Testing Helpers

Dorguzen provides first-class JSON testing support.

Example:

```php
$this->postJson('/echo-json', [
    'name' => 'Gustav',
])
->assertStatus(200)
->assertJson([
    'data' => [
        'name' => 'Gustav',
    ],
]);
```

Internally:

- The request content type is set correctly
- The JSON body is injected via `TestInputStream`
- The request object exposes the parsed JSON payload

---

### HTTP Request & Assertion Reference

The helpers below are all provided by the testing traits mixed into the base `TestCase` (`DispatchesHttpRequests`, `InteractsWithHttp`, `InteractsWithAuthentication`) and the `TestResponse` object every request returns. They are listed here as a single reference.

#### Request helpers

```php
// Plain requests
$this->get('/blog');
$this->post('/contact', ['field' => 'value']);
$this->http('PUT', '/resource', ['name' => 'Updated']);   // any method

// JSON requests (Content-Type + Accept set to application/json, body sent via php://input)
$this->getJson('/api-v1/products');
$this->postJson('/api-v1/auth/login', ['email' => '...', 'password' => '...']);
$this->putJson('/api-v1/products/1', ['name' => 'Updated']);
$this->deleteJson('/api-v1/products/1');

// Fluent modifiers (chain before the request)
$this->withHeaders(['HTTP_ACCEPT' => 'application/json'])->get('/page');
$this->withSession(['key' => 'value'])->get('/dashboard');
```

#### Status assertions

```php
$response->assertStatus(200);
$response->assertOk();            // 200
$response->assertCreated();       // 201
$response->assertNoContent();     // 204
$response->assertNotFound();      // 404
$response->assertForbidden();     // 403
$response->assertUnauthorized();  // 401
$response->assertServerError();   // status >= 500
```

#### Body, header & content-type assertions

```php
$response->assertSee('Welcome');
$response->assertDontSee('Error');
$response->assertHeader('Content-Type', 'application/json');  // value optional
$response->assertJsonResponse();   // Content-Type: application/json
$response->assertHtmlResponse();   // Content-Type: text/html
$response->assertEmpty();          // body is empty
```

#### JSON assertions

```php
$response->assertJson(['status' => 'ok']);                  // subset match
$response->assertExactJson(['success' => true, 'data' => null]); // strict equality (no extra keys)
$response->assertJsonPath('data.user.email', 'a@b.c');      // value at dot path
$response->assertJsonCount(3);                              // count at root
$response->assertJsonCount(5, 'data.items');                // count at dot path
$response->assertJsonMissing('data.password');             // key absent (dot path)
$response->assertJsonFragment(['name' => 'Jane']);          // loose fragment
$response->assertJsonStructure(['id', 'email', 'created_at']); // top-level keys present
$response->assertError('Email is required');               // checks error|message key
```

#### Auth-state assertions

```php
$response->assertAuthenticated();
$response->assertGuest();
```

---

### Authentication Helpers (Testing Only)

`src/Testing/auth/InteractsWithAuthentication.php`

This trait provides authentication simulation, not real authentication.

Key helper:

```php
$this->actingAs($user);
```

What this does:

- Attaches a user object to the request
- Makes the user available via `request()->user()`
- Does not log in, set cookies, or touch sessions

This keeps tests fast and deterministic.

Example:

```php
$user = new TestUser([
    'id' => 1,
    'email' => 'test@example.com',
]);

$this->actingAs($user)
     ->get('/me')
     ->assertStatus(200)
     ->assertJson([
         'email' => 'test@example.com',
     ]);
```

#### Acting as a Guest

To explicitly clear any acting user (and assert that protected routes reject guests):

```php
$this->actingAsGuest()
     ->get('/dashboard')
     ->assertStatus(302); // redirected to login
```

#### Important Rule: No Persistent Auth State

Authentication state is cleared after every request.

This prevents:

- Auth leakage between tests
- False positives
- Order-dependent failures

If a test needs a user, it must call `actingAs()` explicitly.

---

### Writing Feature Tests

#### Example: Ping Test

```php
class PingTest extends TestCase
{
    public function test_ping_endpoint_returns_ok()
    {
        $this->get('/ping')
            ->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
            ]);
    }
}
```

#### Example: Auth Guard Test

```php
class AuthGuardTest extends TestCase
{
    public function test_guest_cannot_access_me_endpoint()
    {
        $this->get('/me')
            ->assertStatus(401);
    }
}
```

#### Example: Authenticated User Test

```php
class MeTest extends TestCase
{
    public function test_authenticated_user_can_access_me_endpoint()
    {
        $user = new TestUser([
            'id' => 1,
            'email' => 'test@example.com',
        ]);

        $this->actingAs($user)
             ->get('/me')
             ->assertStatus(200)
             ->assertJson([
                 'email' => 'test@example.com',
             ]);
    }
}
```

---

### Test Support Classes

`tests/support/TestUser.php`

A lightweight user object for testing.

This avoids:

- Database dependencies
- ORM coupling
- Authentication complexity

---

### Unit Tests

Unit tests live under:

```
tests/unit/
```

Example:

```php
class SanityTest extends TestCase
{
    public function test_true_is_true()
    {
        $this->assertTrue(true);
    }
}
```

---

### Running Tests

To run all tests:

```bash
dgz test
```

This command:

- Boots the testing environment
- Runs PHPUnit
- Supports parallel-safe execution
- Uses `phpunit.xml`

It also forwards a small set of common PHPUnit options, and accepts one or more test paths to narrow the run:

```bash
dgz test --filter SomeTest        # filter by class or method name
dgz test tests/feature/http/      # run only a directory (or a single file)
dgz test --stop-on-failure        # stop at the first failing test
dgz test --coverage-text          # text coverage report
```

Under the hood `dgz test` simply invokes PHPUnit with the project config, so you can always run PHPUnit directly instead:

```bash
vendor/bin/phpunit -c phpunit.xml
```

---

### Creating New Tests

Dorguzen provides a test generator command:

```
TestCommand.php
```

This scaffolds:

- Proper namespaces
- Correct base class
- Consistent structure

The generator takes the test type (`unit` or `feature`) and a name, and writes the file into the matching directory with the matching namespace (`Dorguzen\Tests\Unit` or `Dorguzen\Tests\Feature`):

```bash
dgz make:test feature Checkout    # -> tests/feature/CheckoutTest.php
dgz make:test unit Orders         # -> tests/unit/OrdersTest.php
```

A `Test` suffix is appended automatically if you omit it.

---

### Final Notes

- Controllers must never store request or response objects
- Always access them via `request()` and `response()` helpers
- Tests are isolated by design — do not rely on shared state
- If a test fails intermittently, it usually means state leakage

Dorguzen's testing system favors clarity over convenience — and that is exactly what makes it reliable.

---

## Environment Isolation in Dorguzen PHPUnit Testing

Dorguzen is designed so that application runtime and test runtime are completely isolated from each other. This is not an accidental side effect — it is a deliberate architectural choice that protects real application data, avoids state leakage, and enables safe, repeatable, and parallel test execution.

> "Dorguzen has two completely separate entry points: one for the web application, and one for testing. Running tests does not change the web environment, and visiting the web app does not activate testing mode."

This separation is one of the core guarantees of Dorguzen's testing system.

### Two Independent Entry Points

Dorguzen never "switches modes" at runtime. Instead, it uses explicit bootstrapping.

| Context | Entry Point | Environment Loaded |
| --- | --- | --- |
| Web application | `bootstrap/app.php` | `.env` |
| PHPUnit tests | `bootstrap/testing.php` | `.env.testing` |

Because these entry points are different, the environments are parallel and independent.

- Visiting the application in a browser never loads the testing environment
- Running tests never touches the web application environment

Developers do not need to manually toggle anything.

---

### How the Testing Environment Is Activated

When a developer runs:

```bash
dgz test
```

PHPUnit bootstraps Dorguzen through:

```
TestCase::setUp()
└── bootstrap/testing.php
```

This file:

- Loads `.env.testing`
- Loads testing-specific configuration
- Prepares the framework for safe, isolated test execution

The normal application bootstrap (`bootstrap/app.php`) is not involved.

---

### The Role of .env.testing

The `.env.testing` file is mandatory for meaningful testing. It is not a replacement for `.env` — it is a set of overrides that are layered on top.

#### How env loading works in the test environment

`EnvLoader` always loads files in this order:

```
1. .env           ← your development defaults (always loaded first)
2. .env.testing   ← test overrides (loaded second, wins on any key it defines)

(.env.local is explicitly skipped when APP_ENV=testing)
```

This means `.env.testing` only needs to contain the values that differ from your development environment. Anything not listed in `.env.testing` falls through transparently from `.env`. You never need to duplicate your full development config into `.env.testing`.

#### Default setup — SQLite :memory: (recommended)

The `.env.testing` file that ships with Dorguzen uses an in-memory SQLite database:

```bash
APP_ENV=testing

DB_CONNECTION=sqlite
DB_SQLITE_PATH=:memory:
```

The `:memory:` path is a special SQLite keyword. It means the database exists only in RAM for the duration of the PHP process — nothing is written to disk, and nothing persists between test runs.

Why this is the recommended default:

```
✅ Zero infrastructure — no database server needed
✅ Process-local — the web app cannot reach the test database at all
✅ Ephemeral — every test run starts from a guaranteed clean slate
✅ Fast — RAM is faster than any on-disk database
✅ Safe — no risk of accidentally wiping development data
```

The web app and the test process are in completely separate PHP processes. Because the test database lives only in the memory of the PHPUnit process, it is structurally impossible for the web application to access or interfere with it, even if both are running at the same time.

#### Alternative setup — MySQL/other driver

Dorguzen's test infrastructure is fully driver-agnostic. `bootstrap/testing.php` reads `DB_CONNECTION` from the resolved env and instantiates the correct driver automatically:

```
DB_CONNECTION=sqlite   → DGZ_SQLiteDriver
DB_CONNECTION=mysqli   → DGZ_MySQLiDriver
DB_CONNECTION=pdo      → DGZ_PDODriver
DB_CONNECTION=postgres → DGZ_PostgresDriver
```

If you prefer a dedicated MySQL test database, override the relevant values in `.env.testing`:

```bash
APP_ENV=testing

DB_CONNECTION=mysqli
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dorguzen_test    # ← must be a dedicated test-only database
DB_USERNAME=test_user
DB_PASSWORD=secret
DB_KEY=takeThisWith@PinchOfSalt
```

Notice you only need to list the DB values that differ from `.env`. All other application config (app name, URLs, mail settings, modules, etc.) continues to fall through from `.env` unless you explicitly override them here.

⚠️ When using a server-based driver, the test database MUST be dedicated and separate from your development database. `RefreshDatabase` runs `migrate:fresh` on every test run — it drops all tables and rebuilds the schema from scratch. If you point it at your development database you will lose all your development data.

#### What else you can add to .env.testing

Because `.env.testing` is just a layered override file, you can add any env key your application supports. Common additions include:

```bash
# Force debug mode on in tests to surface errors clearly
APP_DEBUG=true

# Use synchronous queue processing in tests (no worker needed)
QUEUE_DRIVER=sync

# Pin log output to file only, so test output stays clean
APP_LOG_DRIVER=file

# Disable modules you don't want active during tests
MODULES_PAYMENTS_STATUS=off
MODULES_SMS_STATUS=off

# Override JWT secret to a known test value
APP_JWT_SECRET=test-secret-key-not-for-production

# Exempt API routes from CSRF in tests
APP_API_CSRF_EXCEPTION='/api/'

# Cap file uploads to something small for faster tests
MAX_UPLOAD_FILE_SIZE=1024
```

Only add what you actually need to change. The principle is: `.env.testing` should be as short as possible — just the overrides that make the test environment behave differently from development.

---

### Database Safety and Testing Best Practices

Dorguzen's testing tools assume that tests are destructive by design.

During testing:

- Databases may be wiped
- Tables may be dropped
- Migrations may be re-run repeatedly

Because of this:

#### ✅ Use a Dedicated Testing Database

Your `.env.testing` database must not be shared with:

```
Local development
Staging
Production
```

This provides a final layer of protection even if a test behaves unexpectedly.

#### ✅ Always Create Migrations for All Tables

Dorguzen tests frequently rely on the built-in command:

```bash
migrate:fresh
```

This command:

```
Drops all tables
Re-runs all migrations
Produces a clean database state
```

For this reason:

- Every table in your application should have a migration.
- Tests cannot safely recreate schema that only exists manually in a database.

---

### Why This Design Matters — True Parallel Isolation

The web app and the test suite can run simultaneously without interfering with each other. This is not a soft guarantee enforced by discipline — it is a structural guarantee enforced by the architecture. Here is why each layer holds:

**Separate processes**
PHPUnit runs in the CLI as its own PHP process. The web app runs under Apache/MAMP as a separate PHP process. They share no memory, no container, no session.

**Separate entry points**
The web app boots through `bootstrap/app.php`. Tests boot through `bootstrap/testing.php`. Neither calls the other. There is no code path that can accidentally cross the boundary.

**Separate env resolution**
`APP_ENV=testing` is forced via `putenv()` before `EnvLoader` is ever called. The web app never sees `.env.testing`. The test process never loads `.env.local`. Each process arrives at a completely independent set of resolved configuration values.

**Separate DB connections**
The resolved `DB_CONNECTION` determines which driver is instantiated in that process. With the default SQLite `:memory:` setup, the test database lives only in the RAM of the PHPUnit process — the web app has no socket, no file, no port to connect to. Even if both processes are running at the same time, the test database is structurally unreachable from the web app.

**Driver-aware SQL generation**
`Blueprint`, `ColumnDefinition`, and the migration infrastructure all generate the correct SQL dialect for whichever driver is active (`ENGINE=InnoDB` for MySQL, no engine clause for SQLite, `SERIAL` for Postgres). Running `migrate:fresh` in tests produces valid SQLite DDL. Running it via `php dgz migrate:fresh` against the web app produces valid MySQL DDL. The same migration files serve both without modification.

By isolating environments at the bootstrap level, Dorguzen avoids an entire class of bugs and risks:

```
❌ Accidental data loss in development
❌ State leaking between tests
❌ Config or cache collisions
❌ Test mode being exposed to web users
❌ Tests interfering with a running web app
```

Instead, Dorguzen provides:

```
✅ Predictable, order-independent test behavior
✅ Safe parallel execution of web app and test suite
✅ Clean request/response lifecycles per test
✅ Confidence that tests mirror real usage
✅ Freedom to run tests without stopping the dev server
```

A simple way to think about it:

> Running your application and running tests are two different programs.
> They share code, but not state, configuration, environment, or data.

This is the foundation that makes Dorguzen's PHPUnit integration reliable, professional, and production-safe.

### Summary

- Dorguzen uses explicit environment bootstrapping
- Web and test environments are fully independent
- A `.env.testing` is required and should use a separate database
- Tests are allowed to freely reset database state
- Migrations for all your application's tables are essential, for reliable testing

This design ensures that testing in Dorguzen is powerful without ever being dangerous.

---

## Manual Testing

Sometimes when developing software, you often want to do a quick sanity test to convince yourself that something is working as it should, or returning some data, and or, in the right format/type/structure you expect. That is why in Dorguzen, all that is possible. Look at it this way; while PHPUnit is a more official way to prove to others within your organisation or an external body that what you wrote works as expected; DGZ manual testing is meant to prove to yourself, first of all, and your organisation or colleagues that what you have written works. Let's go straight into it.

The manual testing is a simple cloning of Dorguzen request engine made available for you in the command line, via the `CliKernel`, a child of Dorguzen's Kernel (`Dorguzen\Core\Kernel\CliKernel`). This ensures that you do not have to pass through Dorguzen's routing system of controllers and views to test the logic of a class you have written. This makes you work faster. This `CliKernel` bootstraps Dorguzen for you, including loading all the config environment, autoloads all your classes, and helper classes, and environmental variables etc to make it available to you in the CLI.

To run manual tests, simply place your test code in the `tests/manual/` directory. Then navigate in the CLI to the root of your application and run it like so:

```bash
php tests/manual/testFileName.php
# or
./vendor/bin/phpunit tests/unit/LazyLoadTest.php
```

This test file must always include the `tests/manual/cliTestHeader.php` file. For example:

```php
require_once __DIR__ . '/cliTestHeader.php';
```

The code in that included file is what bootstraps Dorguzen, giving you its full power there in the CLI.

To see an example in action, see this example manual test file and the others.

```
tests/manual/pdo_test_1.php
```

They are there to serve as examples of how you can write your own manual tests. The examples in those manual test files are simple, but feel free to pull in controllers, models, services, modules etc to write your test code.

This whole manual testing system is there so you do not write dummy manual test code in your actual application files.

If you have to manually test other things like views, then you have no choice but to temporarily write test code side-by-side your application code. The idea is to keep that to a minimum, and write Unit tests, to make the tests more formal.
