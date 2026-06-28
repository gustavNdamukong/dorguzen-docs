# Swagger / OpenAPI

## Interactive API Documentation (Swagger UI / OpenAPI)

### Overview

Dorguzen ships with built-in, interactive API documentation powered by the OpenAPI standard and Swagger UI. When enabled, visiting the docs URL in any browser gives you a live, clickable page that lists every API endpoint, shows what each one expects as input, what it returns, and lets you fire real test requests directly from the browser — no separate tool needed.

This is the same experience you get with Django REST Framework's `/docs`, or Laravel's l5-swagger package — but Dorguzen goes one step further with what is called hybrid auto-discovery, explained below.

The docs URL is:

```
/api/v1/docs        → the interactive Swagger UI page
/api/v1/docs/spec   → the raw OpenAPI JSON spec (used by the UI internally)
```

---

### How Dorguzen generates the docs — Hybrid Auto-Discovery

Dorguzen builds the API documentation in two passes:

**PASS 1 — Annotation scan**

Dorguzen uses the zircote/swagger-php library to scan every file in `src/api/v1/controllers/` and collect any PHP 8 OpenAPI attributes (`#[OA\*]`) that the developer has written on their controller methods. These produce rich, fully documented entries in the Swagger UI — with descriptions, request body schemas, response examples, and security requirements all shown clearly.

**PASS 2 — Router inspection (auto-discovery)**

After the annotation scan, Dorguzen walks through every route registered in `routes/api.php` and checks whether each route already has an annotated entry from Pass 1. Any route that does NOT have an annotation yet is automatically added to the spec as a stub entry, tagged "Auto-discovered".

The result: every API route is always visible in the Swagger UI from the moment you add it to `routes/api.php` — even if you have not written a single annotation yet. You never have to worry about a route being invisible in the docs. As you add `#[OA\*]` annotations over time, the stubs get replaced with rich documentation.

This is what makes Dorguzen's approach better than e.g. Laravel's l5-swagger: with Laravel, if you haven't annotated an endpoint, it simply does not appear. With Dorguzen, all routes appear automatically; annotations are the polish, not the prerequisite.

---

### Does Dorguzen ship with the required packages?

Yes. The zircote/swagger-php package is included in Dorguzen's `composer.json` and will be installed automatically when you run:

```
composer install
```

There is nothing extra to install. Just ensure you have set `API_DOCS_ENABLED=true` in your `.env` file and the docs page will be live.

One important note: zircote/swagger-php depends on symfony/finder. Because different versions of symfony/finder have different PHP version requirements, Dorguzen pins it to a version compatible with PHP >= 8.2. If you ever run into a platform conflict after a composer update, re-pin with:

```
composer require "symfony/finder:^7.3" --update-with-dependencies
```

---

### Should API_DOCS_ENABLED be true or false in production?

This is a design decision that depends on what kind of API you are building:

**For a PUBLIC API (one meant to be consumed by third-party developers):**

Set `API_DOCS_ENABLED=true` in production. Exposing your docs publicly is not only fine, it is the right thing to do. Stripe, Twilio, GitHub, and virtually every developer-facing API in the world publishes its docs publicly. The spec reveals no secrets — it only describes what your API accepts and returns, which is exactly what a consumer needs to know.

**For a PRIVATE or INTERNAL API (only consumed by your own front-end or mobile app):**

Set `API_DOCS_ENABLED=false` in production. There is no reason to expose the docs to the public, and hiding the spec reduces the surface area that a bad actor could use to map out your endpoints.

The default in `.env.example` is `API_DOCS_ENABLED=true`, which is appropriate for local development and staging. Set it as suits your deployment in production.

---

### Using the Swagger UI to test routes

When you visit `/api/v1/docs` you will see the Swagger UI page. Here is how to use it:

1. Expand any endpoint by clicking on it. You will see its method (GET/POST etc.), path, description, and the expected request body or parameters.

2. Click "Try it out" to enable the input fields for that endpoint.

3. For endpoints that do NOT require authentication (e.g. `/api/v1/auth/login` and `/api/v1/auth/register`), fill in the JSON body and click Execute. The real HTTP request is made and the response shown immediately below.

4. For endpoints that require a Bearer token (a closed padlock icon appears on them):
   a. First call `POST /api/v1/auth/login` to obtain a fresh access_token.
   b. Copy the access_token value from the response (just the token string, not "Bearer ...").
   c. Click the green "Authorize" button at the top right of the page.
   d. In the bearerAuth field, paste the token and click Authorize, then Close.
   e. All subsequent "Try it out" requests will now include the `Authorization: Bearer` header automatically. The padlock icons on protected endpoints will appear closed/locked.

The Swagger UI is configured with `persistAuthorization: true`, which means your token is remembered across page reloads (stored in the browser's local storage for that tab). You do not have to re-enter it every time.

---

### Understanding access tokens and refresh tokens

When a user logs in (`POST /api/v1/auth/login`), Dorguzen issues two tokens:

**access_token**

A short-lived JWT (JSON Web Token). This is sent with every protected API request in the Authorization header like so:

```
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
```

The server does not store this token anywhere. It simply decodes it on each request using the JWT secret key. If the token is valid and not expired, the request proceeds. This is what makes JWT-based APIs stateless and fast — no database lookup needed for each request.

The access_token expires quickly (typically in a few hours). This short lifespan limits the damage if a token is stolen — it becomes useless after expiry.

**refresh_token**

A longer-lived token. It is stored server-side in the `dgz_refresh_tokens` table (one row per user — never more). Its purpose is to let the client obtain a new access_token when the old one expires, without asking the user to log in again.

The intended flow is:

- Client makes a request with the access_token.
- Server responds with 401 "Access token is expired".
- Client silently calls a token-refresh endpoint, submitting the refresh_token.
- Server validates the refresh_token, issues a fresh access_token (and optionally a new refresh_token), and the client continues seamlessly.
- The user never sees a login prompt unless the refresh_token itself has also expired.

The refresh_token is NOT sent with every request — only when the access_token has expired.

**What happens when a user logs in again?**

Each call to `POST /api/v1/auth/login` generates a brand-new access_token and refresh_token pair. Dorguzen checks whether a refresh_token row already exists for that user in the database. If one exists, it is UPDATED with the new token; if not, a new row is inserted. The result is that the `dgz_refresh_tokens` table always holds at most one row per user — the most recently issued refresh_token. Any previous refresh_token is therefore invalidated at login.

The access_token has no server-side record to override — the client simply discards the old one and uses the new one.

**Tokens issued on registration**

When a new user registers (`POST /api/v1/auth/register`), Dorguzen also issues an access_token and refresh_token immediately. The rationale is convenience: the user just proved who they are by submitting their credentials, so there is no need to force a separate login call. Many APIs (Spotify, Twitter/X, etc.) follow this same pattern.

The trade-off is that the token is issued before the user has verified their email. Whether that matters depends on what your protected endpoints do. If sensitive actions require email verification, enforce that check inside the endpoint logic.

---

## Writing OpenAPI annotations (`#[OA\*]`)

Dorguzen uses PHP 8 native attributes (the `#[...]` syntax) to write OpenAPI documentation directly in the controller source code, co-located with the method they document. The library that reads these attributes and produces the OpenAPI JSON spec is zircote/swagger-php.

You do NOT have to write any annotations to get your routes visible in the docs — auto-discovery handles that. Annotations are for when you want to go beyond the auto-generated stub and give consumers a clear contract: what fields the request body expects, what each response code means, and what the response body looks like.

The namespace to import at the top of your controller file is:

```php
use OpenApi\Attributes as OA;
```

Below is a breakdown of every annotation building block you will use, with examples.

---

### 1. Global / file-level attributes

These go on a class (not a method) and are written exactly once in your entire codebase. Dorguzen places them on DocsController.

`#[OA\Info(...)]` — the top-level metadata block for the whole spec

```php
#[OA\Info(
    version: '1.0.0',
    title: 'My App API',
    description: 'A brief description of what this API does.'
)]
```

`#[OA\SecurityScheme(...)]` — defines an authentication scheme (e.g. Bearer JWT)

```php
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',   // the name you reference in endpoint annotations
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Paste your access_token here. Obtain one via POST /api/v1/auth/login.'
)]
```

You do not need to touch these unless you are changing the app name or adding a second auth scheme. They live in `src/api/v1/controllers/DocsController.php`.

---

### 2. HTTP method attributes — one per endpoint method

Each endpoint method in your API controller gets one of these:

```php
#[OA\Get(...)]      for GET requests
#[OA\Post(...)]     for POST requests
#[OA\Put(...)]      for PUT requests
#[OA\Patch(...)]    for PATCH requests
#[OA\Delete(...)]   for DELETE requests
```

Common parameters shared by all of them:

```
path         The URI exactly as registered in routes/api.php
             e.g. '/api/v1/user/favourites'

operationId  A unique camelCase identifier across the whole spec — no spaces or slashes.
             e.g. 'getUserFavourites'

summary      A short one-line description shown as the endpoint title in the UI.

description  A longer explanation (optional). Supports markdown.

tags         An array of strings that groups endpoints into collapsible sections in the UI.
             e.g. ['Favourites'] or ['Authentication']

security     Required for protected endpoints. References the security scheme name you
             defined in #[OA\SecurityScheme]. Leave this out for public endpoints.
             e.g. security: [['bearerAuth' => []]]
```

Full example — a protected GET endpoint:

```php
#[OA\Get(
    path: '/api/v1/user/favourites',
    operationId: 'getUserFavourites',
    summary: "Get the authenticated user's favourited ads",
    description: 'Returns all ads the authenticated user has saved to their favourites list.',
    tags: ['Favourites'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Favourites retrieved successfully'),
        new OA\Response(response: 401, description: 'Unauthorised — missing or expired token'),
    ]
)]
public function index(): void { ... }
```

---

### 3. Request body — OA\RequestBody and OA\JsonContent

Used on POST/PUT/PATCH endpoints that accept a JSON body.

```php
requestBody: new OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ['username', 'password'],   // list the required field names
        properties: [
            new OA\Property(property: 'username', type: 'string',  example: 'johndoe'),
            new OA\Property(property: 'password', type: 'string',  format: 'password', example: 'Secret123!'),
        ]
    )
)
```

`OA\Property` parameters:

```
property    The JSON field name
type        'string', 'integer', 'boolean', 'array', 'object', 'number'
format      Optional hint: 'email', 'password', 'date', 'uri', 'int64', etc.
example     A sample value shown in the UI and in "Try it out" pre-filled fields
```

---

### 4. Responses — OA\Response

Every endpoint annotation must include at least one response. Each uses:

```php
new OA\Response(
    response: 200,                  // the HTTP status code as an integer
    description: 'Success',         // plain-text description of this response
    content: new OA\JsonContent(    // optional — describe the response body shape
        properties: [
            new OA\Property(property: 'code',   type: 'integer', example: 200),
            new OA\Property(property: 'status', type: 'boolean', example: true),
            new OA\Property(property: 'data',   type: 'array',   items: new OA\Items(type: 'object')),
        ]
    )
)
```

The content block is optional. For error responses a plain description is usually enough:

```php
new OA\Response(response: 401, description: 'Unauthorised — invalid or expired token'),
new OA\Response(response: 422, description: 'Validation failed — see message for details'),
new OA\Response(response: 500, description: 'Server error'),
```

---

### 5. Path parameters — OA\Parameter

When your route contains a `{placeholder}` like `'/api/v1/ads/{id}'`, document it like this:

```php
parameters: [
    new OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'The ID of the ad',
        schema: new OA\Schema(type: 'integer', example: 42)
    )
]
```

The `in:` field can be `'path'`, `'query'`, `'header'`, or `'cookie'`.

---

### Full annotated example — a POST endpoint with request body and responses

```php
use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/auth/login',
    operationId: 'authLogin',
    summary: 'Log in an existing user',
    description: 'Authenticates the user by username and password, returning JWT tokens.',
    tags: ['Authentication'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username', 'password'],
            properties: [
                new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Secret123!'),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Login successful',
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'code',         type: 'integer', example: 200),
                new OA\Property(property: 'status',       type: 'boolean', example: true),
                new OA\Property(property: 'message',      type: 'string',  example: 'Login successful'),
                new OA\Property(property: 'access_token', type: 'string'),
            ])
        ),
        new OA\Response(response: 401, description: 'Invalid credentials'),
        new OA\Response(response: 422, description: 'Missing username or password'),
    ]
)]
public function login(): void
{
    // controller logic here
}
```

---

### Summary of `#[OA\*]` building blocks

```
Attribute               Purpose
─────────────────────── ───────────────────────────────────────────────────────
OA\Info                 Top-level spec metadata (title, version, description)
OA\SecurityScheme       Defines an auth scheme (Bearer JWT, API key, etc.)
OA\Get / Post / etc.    Documents one endpoint method
OA\RequestBody          Describes the request body for POST/PUT/PATCH
OA\JsonContent          The JSON shape of a request or response body
OA\Property             One field within a JsonContent schema
OA\Items                The schema of array items inside an OA\Property of type array
OA\Response             One possible HTTP response for an endpoint
OA\Parameter            A path, query, header, or cookie parameter
```

---

Return to [Introduction](/dorguzen-docs/docs/introduction) or use the sidebar to navigate.
