# Performance

Dorguzen provides config caching, route caching, and middleware caching to eliminate file-parse and filesystem overhead on each request. In production, enable all three.

---

## Config Cache

The framework serializes all config values into a single PHP file on first load. Subsequent requests read the pre-built array directly — no `.env` parsing, no `configs/*.php` includes.

**Cache location:** `bootstrap/cache/config.php`

### Clear and rebuild

```bash
php dgz cache:config-clear
```

This deletes `bootstrap/cache/config.php` and `bootstrap/cache/config.php.meta`. The cache rebuilds automatically on the next request.

**Always clear the config cache after:**
- Changing any `.env` value
- Editing any file in `configs/`

---

## Route Cache

Routes can be pre-serialized so the router skips re-parsing `routes/web.php` and `routes/api.php` on every request.

```bash
php dgz cache:route-cache    # build cache to storage/cache/routes.php
php dgz cache:route-clear    # delete the cache
```

**Always clear the route cache after:**
- Adding or changing any route in `routes/web.php` or `routes/api.php`

If you forget and get a "No method to handle this request" error for a route you've just added, clearing the route cache will fix it.

---

## Middleware Cache

```bash
php dgz middleware:cache    # pre-build middleware pipeline
php dgz middleware:clear    # clear it
```

Clear this after adding, removing, or reordering middleware.

---

## Production Cache Workflow

Run this sequence after every deployment:

```bash
composer install --no-dev --optimize-autoloader

php dgz cache:config-clear   # clear stale config
php dgz cache:route-cache    # pre-build routes
php dgz middleware:cache      # pre-build middleware pipeline
```

The config cache regenerates on the first request. Routes and middleware are served from cache immediately.

---

## Composer Autoloader Optimization

In production, generate an optimized class map so Composer doesn't scan directories at runtime:

```bash
composer install --no-dev --optimize-autoloader
```

Or separately:

```bash
composer dump-autoload --optimize --no-dev
```

---

## PHP OPcache

Enable OPcache on your server to cache compiled PHP bytecode. Recommended `php.ini` settings:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0        ; 0 = never revalidate in production
opcache.save_comments=1
```

Set `opcache.revalidate_freq=0` in production and clear OPcache after each deploy. On shared hosting this is usually done via the control panel.

---

## Database

### Use the read-only instance for reads

The framework maintains a write instance and a read instance for the DB. Always use the read instance for SELECT queries:

```php
// Inside a service, inject the read model instance
public function __construct(private readonly Users $users) {}

// NOT: new Users() — this creates a new write instance
```

See the [Models & ORM](/docs/models-orm) page for the write-vs-read instance rule.

### Avoid N+1 queries

Load related data in a single query where possible. See [Models & ORM](/docs/models-orm) for ORM join patterns.

### Queue slow operations

Anything that takes more than ~100ms (sending email, generating thumbnails, calling external APIs) should be dispatched to the queue rather than run inline in the request. See the [Queue System](/docs/queues) page.

---

## What's Not Cached

| Feature | Status |
|---|---|
| Config | Cached (`bootstrap/cache/config.php`) |
| Routes | Cached (`storage/cache/routes.php`) |
| Middleware | Cached |
| Views | Not cached — PHP is fast enough; use OPcache |
| DB query results | Not cached — implement application-level caching where needed |
