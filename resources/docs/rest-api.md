# REST API

Dorguzen makes building an API easy.

---

## Building an API

This is the easier and new approach for creating APIs. The API routes should be defined in:

```
routes/api.php
```

You have to use the dedicated API methods on the router like:

```
$router->apiPost() for POST requests
$router->apiGet() for GET requests
$router->apiPut() for PUT requests
$router->apiPatch() for PATCH requests
$router->apiDelete() for DELETE requests
```

These methods accept three mandatory arguments;

- 1) the request URI string e.g. `/api/ads/favs/{id}`
- 2) the controller name and the method (separated by @) e.g. `AdController@favourites`
- 3) the version number.

Do it like so:

```php
$router->apiPost('/api/ads/favs/{id}', 'AdController@favourites', 'v1');
```

You must provide all three arguments. The version number (v*) is especially important —
Dorguzen will throw an exception if it is missing. Versioning is enforced as good practice
so that your API can evolve without breaking existing clients. The API
directory structure in Dorguzen looks like this:

```
myApplication/
  |__src/
      └── api/
          ├── v1/
            └── controllers
                  └── apiController.php
                  └── blogController.php
                  └── adController.php
          ├── v2/
            └── controllers
                  └── apiController.php
                  └── blogController.php
                  └── adController.php
      ├── controllers/
      ├── models/
      ├── services/
```

Note that this directory system given above is in the main Dorguzen application directory and
not in the modules sub-directory.
Any of the controllers in your API application can handle any request, so here, there is no
notion of a main/landing controller, as it is in modules.

To create a controller in this directory structure, use the dedicated CLI command (recommended):

```bash
php dgz make:api-controller ControllerName
```

This is the recommended approach — it generates the controller in the correct directory,
pre-wired with DGZ_APITrait, OpenAPI imports, and a documented annotation skeleton.
See 'Creating an API controller (recommended)' below for full details.

Also, any controller class in the API application that needs to enforce JWT authentication must
use the DGZ_APITrait trait that provides methods from the Firebase PHP JWT library.

---

## The importance of using service classes

To maintain a clean, scalable architecture, it's highly recommended that your API controllers delegate
logic to service classes.

Example structure:

```
myApplication/
  |__src/
      └── api/
          ├── v1/
            └── controllers
                  └── apiController.php
                  └── blogController.php
                  └── adController.php
      ├── controllers/
      ├── models/
      ├── services/
          └── AuthService.php
          └── Product.php
```

Your API application and your main application's controllers can both turn to service classes for the
business logic and data processing of your application. This means that, if your main app's controllers
have to handle all logic and requests from your web application, which you may have to repeat
in your API controllers, or if your API controllers have to talk to your main controllers, which will have
to determine if the request is from the web app or the API in order to know how to respond, it will be
too messy. The best solution is to use the concept of centralized service classes that both your web app
and API talks to.

In such a setup; your main web application's as well as your API's controllers will handle HTTP requests
and responses. Services will handle the actual business logic and data access. These controllers will
therefore be lean and easy to read. Services will receive arguments from both sources and spit back data,
and it won't matter who the request is from.

This approach keeps your API code loosely coupled from your main web app (`/src/controllers/`), making it
easier to maintain, scale, or even extract into microservices later. Your API controllers
in `src/api/` can pull from the exact same service classes as your web controllers.

---

## Creating an API controller (recommended)

The recommended way to create an API controller in Dorguzen is with the built-in CLI command:

```bash
php dgz make:api-controller ControllerName
```

This is preferred over creating the file by hand because the generated controller comes
pre-wired with everything you need out of the box:

- The correct namespace for the target API version
- DGZ_APITrait already imported and applied (provides validateToken(), setHeaders(), etc.)
- `use OpenApi\Attributes as OA` already imported
- A fully commented OpenAPI annotation skeleton so you can document your first endpoint
  without having to look anything up
- Inline guidance (in comments) on how to write annotations, handle auth, inject services,
  and register the matching route in routes/api.php

---

## The make:api-controller command

Basic usage — creates the controller in src/api/v1/controllers/:

```bash
php dgz make:api-controller Product
```

This produces: `src/api/v1/controllers/ProductController.php`

### Specifying a version

Use the `--api-version` option to target a different API version. If the version directory does
not yet exist, Dorguzen creates it for you automatically:

```bash
php dgz make:api-controller Product --api-version=v2
```

This produces: `src/api/v2/controllers/ProductController.php`
(and creates `src/api/v2/controllers/` if it did not exist)

You can pass the version with or without the 'v' prefix — both are accepted:

```bash
php dgz make:api-controller Product --api-version=2    // same result as --api-version=v2
```

---

## What the generated controller contains

After running the command you will find the following already in place:

1. Correct namespace
   - e.g. `namespace Dorguzen\Api\V1\Controllers;`

2. DGZ_APITrait applied
   - Gives you validateToken(), setHeaders(), generateTokens(), saveRefreshToken() etc.
   - See the DGZ_APITrait section for full method reference.

3. OpenAPI import
   - `use OpenApi\Attributes as OA;`

4. A docblock on the class showing example route registrations for all four HTTP verbs,
   ready to copy into routes/api.php.

5. An index() example method with:
   - A fully commented `#[OA\Get(...)]` annotation skeleton with every field explained
   - The validateToken() / $this->validatedToken pattern shown in comments
   - A DGZ_Response success response wired up and ready to fill in

6. After creation the CLI prints the next steps:
   - Register your routes in routes/api.php
   - Replace the TODO placeholders in the annotations
   - Inject any service classes you need via the constructor

---

## Adding more methods

The index() method in the stub is just a starting point — it is not special in any way.
You are free to:

- Rename it to anything that suits your resource (show, store, update, destroy, etc.)
- Delete it entirely and write your own methods from scratch
- Add as many additional methods as your resource needs

Each method simply needs a corresponding route in routes/api.php and, optionally, an
`#[OA\*]` annotation above it for full Swagger UI documentation. If you skip the annotation
the route will still appear in the docs as an auto-discovered stub automatically.

Example of a fully built-out controller with multiple methods:

```php
// routes/api.php
$router->apiGet('/api/v1/products',      'ProductApi@index',   'v1');
$router->apiGet('/api/v1/products/{id}', 'ProductApi@show',    'v1');
$router->apiPost('/api/v1/products',     'ProductApi@store',   'v1');
$router->apiDelete('/api/v1/products/{id}','ProductApi@destroy','v1');

// src/api/v1/controllers/ProductApiController.php
public function index(): void   { /* list all products   */ }
public function show(): void    { /* get one product      */ }
public function store(): void   { /* create a product     */ }
public function destroy(): void { /* delete a product     */ }
```

---

## Enforcing JWT Authentication on API Routes

Add DGZ_APITrait to any API controller that needs JWT validation:

```php
use Dorguzen\Core\DGZ_APITrait;

class MyApiController extends DGZ_Controller
{
    use DGZ_APITrait;

    public function protectedEndpoint(): void
    {
        $this->setHeaders();
        $tokenResponse = $this->validateToken();
        if (!$this->validatedToken) {
            $tokenResponse->send();
            exit();
        }
        // $this->validatedUser['user_id'] is now available
        ...
    }
}
```

DGZ_APITrait provides: setHeaders(), validateToken(), refreshToken(), generateTokens(),
and refresh-token persistence helpers (saveRefreshToken, getRefreshToken, updateRefreshToken).

---

## The JWT Secret Key

The JWT secret is set in your .env file:

```ini
APP_JWT_SECRET=your-secret-here
APP_JWT_ENCODING=HS256
```

This value is the private signing key used by the Firebase JWT PHP library to sign and
verify tokens. It does NOT come from the Firebase platform — you generate it yourself.
Any non-empty string will work technically, but for production you should use a strong
random value of at least 32 characters.

The easiest way to generate one is with openssl in your terminal:

```bash
openssl rand -base64 48
```

Copy the output and paste it as your APP_JWT_SECRET value.

Important notes:

- Never commit your real secret to Git. Keep it only in .env (which is in .gitignore).
  Use .env.example to document the key name with an empty or placeholder value.

- If you rotate the secret (change it), all existing tokens are immediately invalidated —
  users will need to log in again. This is expected behaviour and is the correct way to
  revoke all active sessions at once if needed.

- The placeholder value in the repo (xxxxxxxxxx...) is functional but weak. Replace it
  before going to production.

---

## Working API Routes

### User Registration

Here is the request to send (request made with HttPie, an API testing tool in the CLI) to register a user:

```bash
http POST http://localhost/yourAppName/api/v1/auth/register \
  firstname=Test \
  surname=User \
  username=testUser \
  password=Secret123! \
  confirm_password=Secret123! \
  phone=12345678 \
  email=testuser@example.com
```

Here is the response you will get back:

```
HTTP/1.1 201 Created
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: GET, POST, PATCH, DELETE
Access-Control-Allow-Origin: https://camerooncom.com/
Access-Control-Max-Age: 3600
Cache-Control: no-store, no-cache, must-revalidate
Connection: Keep-Alive
Content-Length: 902
Content-Type: application/json; charset=UTF-8
Date: Wed, 25 Mar 2026 12:38:43 GMT
Expires: Thu, 19 Nov 1981 08:52:00 GMT
Keep-Alive: timeout=5, max=100
Pragma: no-cache
Server: Apache/2.4.54 (Unix) OpenSSL/1.0.2u PHP/8.2.0 mod_wsgi/3.5 Python/2.7.18 mod_fastcgi/mod_fastcgi-SNAP-0910052141 mod_perl/2.0.11 Perl/v5.30.1
Set-Cookie: PHPSESSID=hvclcnqpltj0pb4nvkg30jibd2; path=/
X-Powered-By: PHP/8.2.0

{
    "activationLink": "https://camerooncom.com/auth/verifyEmail?em=3c02317a3505459d16826a5e0ef128d3",
    "code": 201,
    "message": "Registration successful. Please check your email to activate your account.",
    "status": true,
    "tokens": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJhdWQiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJpYXQiOjE3NzQ0NDIzMjgsImV4cCI6MTc3NDQ2MDMyOCwiZGF0YSI6eyJ1c2VyX2lkIjoxMDZ9fQ.pctSfeHz1j6VfkraGx__CNo7jmEezvCck8CXa8lvg_Y",
        "access_token_expiry": 1774460328,
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJhdWQiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJpYXQiOjE3NzQ0NDIzMjgsImV4cCI6MTc3NDQ0OTUyOCwiZGF0YSI6eyJ1c2VyX2lkIjoxMDZ9fQ.ba9ltCViZMTuC22dtTLx4FwfLbzOOpuGG9R16rex8_E",
        "refresh_token_expiry": 1774449528
    }
}
```

These tokens `"tokens": {...}` are meant to be stored safely as "access_token" will be needed to be sent with
subsequent requests to protected routes on the API.
If the "access_token" expires, the application will require the user to send the "refresh_token" which will
be used to validate them so they can be issued a new access_token.

The user will click on the link sent via the email to activate their account. If you are testing and wish
to simulate the activation of the account, take that 'activationLink' sent back and visit this link locally:

```
http://localhost/camerooncom/auth/verifyEmail?em=3c02317a3505459d16826a5e0ef128d3
```

and you should get back a success message like this:

```
"Your email was successfully activated, you may now log in"
```

### User Login

Login the test user registered in the registration API route above.
This is the request to send (request made with HttPie, an API testing tool in the CLI) to login the testUser
created by the register request above:

```bash
http POST http://localhost/camerooncom/api/v1/auth/login username=testUser password=Secret123!
```

Here is the response you will get back:

```
HTTP/1.1 200 OK
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: GET, POST, PATCH, DELETE
Access-Control-Allow-Origin: https://camerooncom.com/
Access-Control-Max-Age: 3600
Cache-Control: no-store, no-cache, must-revalidate
Connection: Keep-Alive
Content-Length: 904
Content-Type: application/json; charset=UTF-8
Date: Wed, 25 Mar 2026 13:56:58 GMT
Expires: Thu, 19 Nov 1981 08:52:00 GMT
Keep-Alive: timeout=5, max=100
Pragma: no-cache
Server: Apache/2.4.54 (Unix) OpenSSL/1.0.2u PHP/8.2.0 mod_wsgi/3.5 Python/2.7.18 mod_fastcgi/mod_fastcgi-SNAP-0910052141 mod_perl/2.0.11 Perl/v5.30.1
Set-Cookie: PHPSESSID=2c8hok4iu3eisqsmrggb7daqcp; path=/
X-Powered-By: PHP/8.2.0

{
    "code": 200,
    "message": "Login successful.",
    "status": true,
    "tokens": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJhdWQiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJpYXQiOjE3NzQ0NDcwMjUsImV4cCI6MTc3NDQ2NTAyNSwiZGF0YSI6eyJ1c2VyX2lkIjoxMDZ9fQ.Gc2DaU9UV6LTMta-qvVSsDr7KDr2ewdKWfyvzu49HJ0",
        "access_token_expiry": 1774465025,
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJhdWQiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJpYXQiOjE3NzQ0NDcwMjUsImV4cCI6MTc3NDQ1NDIyNSwiZGF0YSI6eyJ1c2VyX2lkIjoxMDZ9fQ.kzKfXk6X1BIjZq9zpZUdNvBUl9vd1RZ6apBU2xqO5I4",
        "refresh_token_expiry": 1774454225
    },
    "user": {
        "email": "testuser@example.com",
        "firstname": "Test",
        "id": 106,
        "lastname": "User",
        "username": "testUser"
    }
}
```

---

## How Access Tokens Work

These requests (register and login) are not secured requests-as in, we do not need to submit any
tokens with them. But when we registered the user in the register request, an access_token and refresh_token
was returned. Was that necessary? Because after logging in now i got another set of access token and refresh
token:

```json
{
    "code": 200,
    "message": "Login successful.",
    "status": true,
    "tokens": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJhdWQiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJpYXQiOjE3NzQ0NDcwMjUsImV4cCI6MTc3NDQ2NTAyNSwiZGF0YSI6eyJ1c2VyX2lkIjoxMDZ9fQ.Gc2DaU9UV6LTMta-qvVSsDr7KDr2ewdKWfyvzu49HJ0",
        "access_token_expiry": 1774465025,
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJhdWQiOiJodHRwczovL2NhbWVyb29uY29tLmNvbS8iLCJpYXQiOjE3NzQ0NDcwMjUsImV4cCI6MTc3NDQ1NDIyNSwiZGF0YSI6eyJ1c2VyX2lkIjoxMDZ9fQ.kzKfXk6X1BIjZq9zpZUdNvBUl9vd1RZ6apBU2xqO5I4",
        "refresh_token_expiry": 1774454225
    },
    "user": {
        "email": "testuser@example.com",
        "firstname": "Test",
        "id": 106,
        "lastname": "User",
        "username": "testUser"
    }
}
```

So when registering the user, was it necessary to send back tokens from the server? I can understand it after
logging in, because that's what will be used by the server to authenticate the user as they make more requests

It's a design choice, and both approaches are valid — but you're right to question it. Here's the reasoning
for each:

Returning tokens on register (current behaviour):
The idea is to save the user an extra round-trip. They just registered — they're clearly authenticated — so
you issue tokens immediately and they can start making authenticated API calls without a separate login step.
Apps like Spotify and Twitter do this. The UX benefit is the client doesn't need to
call /login right after /register.

Not returning tokens on register:
Simpler and arguably more correct when email verification is required — which you have. The user registered
but their email isn't verified yet (emailverified = 'no'). Issuing them a fully valid access token before
they've verified their email means they can make authenticated API calls on an
unverified account. That may or may not be a problem depending on what your protected routes allow.

My recommendation for apps doing email verification:
If you send an activation email on register, don't issue tokens on register. Return just the success
message and activation link. The user verifies their email, then calls /login to get tokens. This is
cleaner and avoids the question of "should unverified users be able to hit protected endpoints?"

To do that will be a small change — just remove the token generation from register() and keep the 201
response as confirmation only.

The token after login overrides the previously issued token.
If you look at the login flow in AuthApiController::login():

```php
$existing = $this->getRefreshToken($userId);
if ($existing) {
    $this->updateRefreshToken($userId, $tokens['refresh_token'], $tokens['refresh_token_expiry']);
} else {
    $this->saveRefreshToken($userId, $tokens['refresh_token'], $tokens['refresh_token_expiry']);
}
```

On login it checks if a refresh token row already exists for that user and updates it rather than
inserting a new one. So the dgz_refresh_tokens table always has at most one row per user — the most
recently issued token. The old one from registration is gone.

The access token isn't stored server-side at all (that's by design with JWT — the server is stateless
for access tokens), so there's nothing to override there. The client just discards the old one and uses
the new one.

---

## Built-in auth routes

Dorguzen ships three ready-made authentication endpoints, registered in `routes/api.php`:

```
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/refresh
```

`/auth/refresh` accepts a valid refresh token and issues a fresh access/refresh token pair via
`DGZ_APITrait::refreshToken()`.

---

## API configuration (.env)

Besides the JWT keys (see "The JWT Secret Key"), two further `.env` keys configure the API layer:

```ini
APP_API_CSRF_EXCEPTION='/api/'
API_DOCS_ENABLED=true
```

- `APP_API_CSRF_EXCEPTION` exempts matching paths (e.g. all `/api/` routes) from the CSRF middleware.
  This is required for any non-browser API client, which has no CSRF token to send.
- `API_DOCS_ENABLED` toggles the auto-generated API documentation (see "API documentation" below).
  Leave it unset or `false` in production to hide the docs.

---

## Reading the JSON request body

API clients send their payload as a JSON body. Read it through the request object:

```php
$body  = request()->json();          // full decoded array
$email = request()->json('email');   // a single top-level key
```

Calling `json()` with no argument returns the whole decoded array; passing a key returns just that
top-level value (with an optional default as the second argument).

---

## The standard response envelope

API responses are sent with the `DGZ_Response` class and follow a consistent envelope:

```json
{
  "code":    200,
  "status":  true,
  "message": "Human-readable description",
  "data":    { }
}
```

Error responses use the same shape with `status: false`:

```json
{
  "code":    422,
  "status":  false,
  "message": "Email is required"
}
```

Build and send one like so:

```php
use Dorguzen\Core\DGZ_Response;

$response = new DGZ_Response();
$response->json([
    'code'    => 200,
    'status'  => true,
    'message' => 'Done',
    'data'    => $result,
])->send();
```

---

## The JWT token payload

The tokens generated by `DGZ_APITrait` carry a standard JWT payload:

```json
{
  "iss":  "<your app homepage>",
  "aud":  "<your app homepage>",
  "iat":  1234567890,
  "exp":  1234567890,
  "data": { "user_id": 1 }
}
```

`iss` and `aud` are both set to the app's configured homepage, and the authenticated user id is
carried under `data.user_id`. After a successful `validateToken()`, that data is available as
`$this->validatedUser['user_id']`.

---

## Token validation error codes

`validateToken()` returns a `401` for any of the following, and also sets a
`WWW-Authenticate: Bearer error='...'` header:

| HTTP | When |
|---|---|
| 401 | Missing `Authorization` header |
| 401 | Header is not in `Bearer <token>` format |
| 401 | Invalid token signature |
| 401 with `expired_token: true` | Token has expired |
| 401 | Malformed token payload |

---

## CORS headers

`setHeaders()` (called at the top of every protected endpoint) emits the CORS and content-type
headers:

```
Access-Control-Allow-Origin: <your app homepage>
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: GET, POST, PATCH, DELETE
Access-Control-Max-Age: 3600
Content-Type: application/json; charset=UTF-8
```

Note that the allowed origin is your app's configured homepage, **not** a wildcard (`*`) — a wildcard
is incompatible with `Access-Control-Allow-Credentials: true`.

---

## API documentation (Swagger UI)

When `API_DOCS_ENABLED=true`, Dorguzen serves interactive API documentation generated from the
`#[OA\*]` attributes on your controllers (via `zircote/swagger-php`):

```
GET /api/v1/docs        → Swagger UI HTML page
GET /api/v1/docs/spec   → raw OpenAPI 3.0 JSON spec
```

Endpoints without `#[OA\*]` annotations still appear automatically as "Auto-discovered" stubs, so the
docs always reflect your full route list. Annotate a method to flesh out its entry, for example:

```php
use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/products',
    summary: 'Create a product',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 201, description: 'Product created'),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
public function store(): void { /* ... */ }
```

Set `API_DOCS_ENABLED=false` (or leave it unset) in production to disable both routes.
