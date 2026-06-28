# Security

- This is about a list of ways in which your computer can be secured against malicious users and attackers

- This will involve best practices on all areas of computer programming, from tips on handling user input authentication, database access, form input processing, API security, securing network communications and more.

- This will be the favourite area of a cybersecurity enthusiast or professional.

- Securing your application requires taking a close look at all areas where users have access to the application. These are areas where they come in contact with your application and can therefore communicate with it. These are the key areas where security measures should be concentrated. As a guide, here is a list of these crucial points of contact:

  - Web forms and SQL insertions
  - SSL implementation
  - APIs. JSON Web Tokens (JWTs) can help here.
  - URL parameters
  - open ports of your server (unseen). Network firewalls can help here.
  - Cookies management. Being aware of its limitations.
  - User session management. Finding ways to prevent user session fixation. Multi-factor authentication (MFA) can help here.

---

## Built-in protections worth knowing

Most of Dorguzen's concrete security mechanisms are documented alongside the feature they belong to — CSRF tokens on the [Forms](/docs/forms) and [Input & Output](/docs/input-output) pages, password storage and parameterized queries on the [Models & ORM](/docs/models-orm) page, password-strength rules (`DGZ_CheckPassword`) and input sanitisation (`DGZ_Validate::fix_string()`) on the [Validation](/docs/validation) page, and JWT API auth on the [REST API](/docs/rest-api) page. Two further protections ship by default and are not covered elsewhere:

- **Automatic HTTPS enforcement** — the global `enforceHttps()` helper (invoked from `bootstrap/helpers_runtime.php`) redirects insecure traffic to `https://` with a `301` and sends a `Strict-Transport-Security` (HSTS) header. It only acts in the `live` environment and skips CLI. It detects HTTPS behind proxies via the `X-Forwarded-Proto`, `X-Forwarded-Ssl`, and Cloudflare `CF-Visitor` headers, and an optional trusted-proxy IP list controls whether those forwarded headers are honoured.

- **Honeypot spam protection** — public form handlers (such as registration in `AuthController`) include a hidden `captcha_hidden` field that genuine users never fill in. When it arrives non-empty the request is treated as a bot, logged, and silently redirected away.
