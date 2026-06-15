# Testing

Dorguzen ships with a full testing layer built on PHPUnit. Tests run against the real framework — real routing, real database (SQLite in-memory by default), real middleware — with no mocking required.

---

## Running Tests

```bash
php dgz test                          # run all tests
php dgz test --filter SomeTest        # filter by class or method name
php dgz test tests/feature/http/      # run a specific directory or file
php dgz test --stop-on-failure
php dgz test --coverage-text
```

Or run PHPUnit directly:

```bash
vendor/bin/phpunit -c phpunit.xml
```

---

## Configuration

`phpunit.xml` in the project root:

```xml
<phpunit
    bootstrap="bootstrap/testing.php"
    colors="true"
    executionOrder="depends,random"
    failOnRisky="true"
    failOnWarning="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_DEBUG" value="true"/>
    </php>
</phpunit>
```

The `bootstrap/testing.php` file boots a minimal framework container — autoloader, config, DB driver, DI bindings — without sessions, routes, or queue bindings.

---

## Test Directory Layout

```
tests/
├── unit/                 # isolated unit tests
├── feature/              # full HTTP + DB integration tests
│   └── http/
├── support/              # shared helpers
│   └── TestUser.php
└── manual/               # raw PHP scripts (not PHPUnit)
```

Custom test namespace: `Dorguzen\Tests\Unit`, `Dorguzen\Tests\Feature`.

---

## Creating a Test

```bash
php dgz make:test ContactFormTest
```

Creates `tests/feature/ContactFormTest.php`. Tests extend the base `TestCase`:

```php
namespace Dorguzen\Tests\Feature;

use Dorguzen\Testing\TestCase;

class ContactFormTest extends TestCase
{
    public function test_contact_form_sends_message(): void
    {
        $response = $this->post('/contact', [
            '_csrf_token' => getCsrfToken(),
            'name'        => 'Jane Doe',
            'email'       => 'jane@example.com',
            'message'     => 'Hello there',
        ]);

        $response->assertStatus(302);
    }
}
```

---

## HTTP Testing

The `DispatchesHttpRequests` trait (mixed into `TestCase`) routes requests through the real `HttpKernel` — the same code path as a live web request.

### Making requests

```php
$this->get('/blog');
$this->post('/contact', ['field' => 'value']);
$this->getJson('/api/v1/products');
$this->postJson('/api/v1/auth/login', ['email' => '...', 'password' => '...']);
$this->putJson('/api/v1/products/1', ['name' => 'Updated']);
$this->deleteJson('/api/v1/products/1');
```

### Setting headers and session

```php
$this->withHeaders(['X-Custom-Header' => 'value'])->get('/page');
$this->withSession(['key' => 'value'])->get('/dashboard');
```

---

## Assertions

```php
$response->assertStatus(200);
$response->assertOk();           // 200
$response->assertCreated();      // 201
$response->assertNoContent();    // 204
$response->assertNotFound();     // 404
$response->assertForbidden();    // 403
$response->assertUnauthorized(); // 401
$response->assertServerError();  // 500

$response->assertSee('Welcome');
$response->assertDontSee('Error');
$response->assertHeader('Content-Type', 'application/json');
$response->assertJsonResponse();
$response->assertHtmlResponse();

// JSON assertions
$response->assertJson(['status' => true]);             // subset match
$response->assertExactJson(['code' => 200, ...]);      // strict equality
$response->assertJsonPath('data.user.email', 'a@b.c'); // dot-path
$response->assertJsonCount(3, 'data');                 // count items at path
$response->assertJsonMissing('data.password');         // key absent
$response->assertJsonFragment(['name' => 'Jane']);
$response->assertJsonStructure(['code', 'status', 'data' => ['id', 'email']]);

$response->assertError('Email is required');
$response->assertEmpty();
```

---

## Authentication in Tests

### Act as an authenticated user

```php
use Dorguzen\Tests\Support\TestUser;

$user = (new TestUser())->make([
    'users_id'        => 1,
    'users_email'     => 'jane@example.com',
    'users_firstname' => 'Jane',
]);

$this->actingAs($user)->get('/dashboard');
```

### Act as a guest

```php
$this->actingAsGuest()->get('/dashboard');
$response->assertStatus(302); // redirected to login
```

### Assert auth state

```php
$response->assertAuthenticated();
$response->assertGuest();
```

---

## Database in Tests

The `RefreshDatabase` trait (mixed into `TestCase`) runs `migrate:fresh` once per test class, giving each class a clean SQLite database:

```php
class BlogTest extends TestCase
{
    // RefreshDatabase is already included via TestCase
    // — the DB is fresh for every test class
}
```

Seed specific data in a test:

```php
public function test_blog_index_shows_posts(): void
{
    // Insert test data directly via the model
    $posts = $this->container->get(\Dorguzen\Models\Posts::class);
    $posts->create([
        'posts_title'   => 'Hello World',
        'posts_content' => 'Content here',
        'posts_status'  => 'published',
    ]);

    $response = $this->get('/blog');
    $response->assertSee('Hello World');
}
```

---

## Unit Tests

For testing pure logic (validators, formatters, helpers) with no HTTP or DB involvement:

```php
namespace Dorguzen\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Dorguzen\Core\DGZ_Validate;

class ValidateTest extends BaseTestCase
{
    public function test_fix_string_strips_html(): void
    {
        $result = DGZ_Validate::fix_string('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $result);
    }
}
```

Unit tests in `tests/unit/` extend `PHPUnit\Framework\TestCase` directly — no `TestCase` base class needed.
