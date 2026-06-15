# REST API

Dorguzen ships with a versioned REST API layer. API controllers live in `src/api/{version}/Controllers/`, routes are declared in `routes/api.php`, and authentication is JWT-based via `DGZ_APITrait`.

---

## Configuration

```ini
# .env
APP_JWT_SECRET='your-secret-key-here'
APP_JWT_ENCODING='HS256'
APP_API_CSRF_EXCEPTION='/api/'
API_DOCS_ENABLED=true
```

`APP_API_CSRF_EXCEPTION` exempts all `/api/` paths from the CSRF middleware — required for any non-browser API client.

---

## Defining API Routes

All API routes go in `routes/api.php`. Use the `api*` router methods — they resolve controllers from `src/api/{version}/Controllers/` instead of `src/controllers/`.

```php
/** @var Dorguzen\Core\DGZ_Router $router */

$router->apiPost('/api/v1/products',        'Products@store',   'v1');
$router->apiGet( '/api/v1/products/{id}',   'Products@show',    'v1');
$router->apiPatch('/api/v1/products/{id}',  'Products@update',  'v1');
$router->apiDelete('/api/v1/products/{id}', 'Products@destroy', 'v1');
```

Available methods: `apiGet`, `apiPost`, `apiPatch`, `apiPut`, `apiDelete`.

Route parameters (`{id}`) are captured and passed as method arguments.

---

## Creating an API Controller

```bash
php dgz make:api-controller ProductsController --api-version=v1
```

Creates `src/api/V1/Controllers/ProductsController.php`:

```php
namespace Dorguzen\Api\V1\Controllers;

use Dorguzen\Core\DGZ_Controller;
use Dorguzen\Core\DGZ_APITrait;
use Dorguzen\Core\DGZ_Response;

class ProductsController extends DGZ_Controller
{
    use DGZ_APITrait;

    public function show($id): void
    {
        $this->setHeaders();

        $this->validateToken();
        if (!$this->validatedToken) {
            exit();
        }

        $userId = (int) ($this->validatedUser['user_id'] ?? 0);

        $response = new DGZ_Response();
        $response->json([
            'code'    => 200,
            'status'  => true,
            'message' => 'Product retrieved',
            'data'    => ['id' => $id],
        ])->send();
    }
}
```

`setHeaders()` sets `Content-Type: application/json` and CORS headers. Always call it first.

---

## Built-in Auth Routes

```
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/refresh
```

### Register — `POST /api/v1/auth/register`

**Request body (JSON):**
```json
{
  "firstname":        "Jane",
  "surname":          "Doe",
  "email":            "jane@example.com",
  "password":         "secret",
  "confirm_password": "secret",
  "phone":            "+1234567890"
}
```

**Response 201:**
```json
{
  "code":           201,
  "status":         true,
  "message":        "Registration successful",
  "activationLink": "https://yourapp.com/activate?token=...",
  "tokens": {
    "access_token":         "eyJ...",
    "access_token_expiry":  1234567890,
    "refresh_token":        "eyJ...",
    "refresh_token_expiry": 1234567890
  }
}
```

### Login — `POST /api/v1/auth/login`

**Request body:**
```json
{
  "email":    "jane@example.com",
  "password": "secret"
}
```

**Response 200:**
```json
{
  "code":    200,
  "status":  true,
  "message": "Login successful",
  "user": {
    "id":        1,
    "email":     "jane@example.com",
    "firstname": "Jane",
    "lastname":  "Doe"
  },
  "tokens": {
    "access_token":         "eyJ...",
    "access_token_expiry":  1234567890,
    "refresh_token":        "eyJ...",
    "refresh_token_expiry": 1234567890
  }
}
```

---

## JWT Authentication

### Token payload

```json
{
  "iss":  "https://yourapp.com",
  "aud":  "https://yourapp.com",
  "iat":  1234567890,
  "exp":  1234567890,
  "data": { "user_id": 1 }
}
```

### Reading the JSON request body

```php
$body  = request()->json();           // full decoded array
$email = request()->json('email');    // single key with dot notation
```

### Protecting an endpoint

```php
$this->validateToken();
if (!$this->validatedToken) {
    exit();  // validateToken() already sent the error response
}

$userId = (int) ($this->validatedUser['user_id'] ?? 0);
```

### Token error codes

| HTTP | When |
|---|---|
| 401 | Missing `Authorization` header |
| 401 | Header is not `Bearer <token>` format |
| 401 | Invalid token signature |
| 401 `expired_token: true` | Token has expired |
| 401 | Malformed token payload |

All 401 responses also set `WWW-Authenticate: Bearer error='...'`.

---

## Standard Response Envelope

```json
{
  "code":    200,
  "status":  true,
  "message": "Human-readable description",
  "data":    { }
}
```

Error responses:
```json
{
  "code":    422,
  "status":  false,
  "message": "Email is required"
}
```

Use `DGZ_Response`:

```php
$response = new DGZ_Response();
$response->json([
    'code'    => 200,
    'status'  => true,
    'message' => 'Done',
    'data'    => $result,
])->send();
```

---

## API Documentation (Swagger UI)

Dorguzen auto-generates Swagger UI from PHP 8 attributes on your controllers.

Enable in `.env`:

```ini
API_DOCS_ENABLED=true
```

Then browse:

```
GET /api/v1/docs       → Swagger UI
GET /api/v1/docs/spec  → Raw OpenAPI 3.0 JSON
```

Annotate controller methods:

```php
use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/products',
    summary: 'Create a product',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'price'],
            properties: [
                new OA\Property(property: 'name',  type: 'string'),
                new OA\Property(property: 'price', type: 'number'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Product created'),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
public function store(): void { ... }
```

Unannotated routes are auto-discovered and appear as stubs tagged `Auto-discovered`.

Disable in production:

```ini
API_DOCS_ENABLED=false
```

---

## CORS

`setHeaders()` sets:

```
Access-Control-Allow-Origin: <your app homepage>
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: GET, POST, PATCH, DELETE
Access-Control-Max-Age: 3600
Content-Type: application/json
```

The allowed origin is the app's configured homepage — not a wildcard.
