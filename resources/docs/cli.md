# CLI Tool

Dorguzen ships with a command-line tool called `dgz`. It handles database migrations, seeders, code generation, the development server, queue workers, log inspection, and more. Every command follows the same pattern:

```bash
php dgz <command> [options]
```

`dgz` is a project-local tool — it always boots from the directory you run it in, reading that project's .env, config, and database connection. This is deliberate: it means each Dorguzen project you build is fully self-contained, and running `php dgz` in project A never touches project B.

DO NOT install dgz as a global symlink pointing to a specific project. If you do, all projects will share one project's bootstrap, config, and database — which will cause confusing failures across projects.

---

## Running commands

Always run `php dgz` from your project root (the directory containing the `dgz` file and your `.env`):

```bash
cd /path/to/my-project
php dgz migrate
php dgz db:seed
php dgz serve
```

You must be in the project root. Running from a subdirectory will fail because `dgz` resolves all paths relative to its own location.

---

## Optional shell alias (recommended)

Typing `php dgz` for every command gets repetitive. You can add a shell alias so that bare `dgz` still delegates to the local `php dgz` — without any global symlink or cross-project risk:

For zsh (the default shell on macOS):

```bash
echo 'alias dgz="php dgz"' >> ~/.zshrc
source ~/.zshrc
```

For bash:

```bash
echo 'alias dgz="php dgz"' >> ~/.bashrc
source ~/.bashrc
```

After adding the alias you can use the short form from any project root:

```bash
dgz migrate
dgz db:seed
dgz serve
```

Because `dgz` expands to `php dgz` (not to an absolute path), it always runs the `dgz` file in your current directory. Switch to a different project and the alias automatically targets that project instead — no configuration needed.

Note: if you use a version manager like asdf, mise, or phpenv that shims the `php` binary, the alias works with whichever PHP version that project selects.

---

## Listing all available commands

To see every command dgz supports, run:

```bash
php dgz list
```

To get help on a specific command:

```bash
php dgz help migrate
php dgz help db:seed
```

---

## Command Reference

The commands below are the ones `dgz` ships with. Run `php dgz list` to see the live list for your installed version, and `php dgz help <command>` for the full option set of any one of them.

### Scaffolding (make:*)

| Command | What it creates |
|---|---|
| `make:controller MyController` | A new controller class |
| `make:api-controller Foo` | A new API controller class |
| `make:model MyModel` | A new model class |
| `make:middleware MyMiddleware` | A new middleware class |
| `make:command MyCommand` | A new CLI command class |
| `make:event MyEvent` | A new event class |
| `make:job MyJob` | A new queue job class |
| `make:factory MyFactory` | A new model factory |
| `make:seeder MySeeder` | A new database seeder |
| `make:migration create_users` | A new migration file |
| `make:test MyTest` | A new test class |
| `make:jetform MyForm` | A new JetForm class |

### Database

| Command | Description |
|---|---|
| `migrate` | Run pending migrations |
| `migrate --file=<name>` | Run a single migration file |
| `migrate --pretend` | Print SQL without executing |
| `migrate:rollback` | Roll back the last batch |
| `migrate:status` | Show which migrations have/haven't run |
| `migrate:refresh` | Roll back all, then re-run all |
| `migrate:fresh` | Drop all tables, then re-run all migrations |
| `migrate:fresh --force` (`-f`) | Skip confirmation |
| `migrate:fresh --pretend` | Print actions without executing |
| `db:seed` | Seed the database with records |
| `db:seed --class=<Seeder>` | Run a specific seeder class |
| `db:seed --all` | Run all seeder classes |
| `db:seed --force` | Skip confirmation |
| `db:seed --pretend` | Print actions without executing |

### Queue

| Command | Description |
|---|---|
| `queue:work` | Start a queue worker |
| `queue:work --once` | Process one job then exit |
| `queue:work --sleep=N` | Seconds to sleep when the queue is empty |
| `queue:work --max-jobs=N` | Exit after N jobs processed |
| `queue:work --timeout=N` | Max seconds per job |
| `queue:jobs` | List pending jobs |
| `queue:stats` | Show queue statistics (pending/failed counts) |
| `queue:failed` | List failed jobs |
| `queue:retry <id>` | Retry a failed job (or `all` for every failed job) |
| `queue:forget <id>` | Permanently delete a failed job |
| `queue:removejob <id>` | Remove a job from the queue |
| `queue:clear` | Clear all pending jobs |

### Cache

| Command | Description |
|---|---|
| `cache:config-clear` | Clear the configuration cache |
| `cache:route-cache` | Cache application routes |
| `cache:route-clear` | Clear the route cache |
| `cache:middleware-cache` | Cache global and route middleware metadata |
| `cache:middleware-clear` | Clear the cached middleware metadata |

### Scheduling

| Command | Description |
|---|---|
| `schedule:run` | Run scheduled tasks due now |

### Inspection

| Command | Description |
|---|---|
| `routes` | List all defined routes |
| `config` | Display all configuration values |
| `log` | Display recent application logs |
| `log:tail` | Stream a log file in real time (like `tail -f`) |
| `log:prune` | Delete log files older than N days |
| `env:check` | Report `.env.example` variables missing from your live `.env` |

### Testing

```bash
php dgz test
```

### Global install (optional, not recommended)

`dgz` also ships `install` and `uninstall` commands. `install` attempts to register `dgz` globally (a `/usr/local/bin/dgz` symlink on macOS/Linux, a `dgz.bat` on Windows):

```bash
php dgz install
php dgz uninstall
```

Be aware this contradicts the project-local design described above: a global symlink points every `dgz` invocation at one project's bootstrap, config, and database. For day-to-day work prefer the shell alias shown earlier (`alias dgz="php dgz"`), which keeps each project self-contained.

---

## Local Development Server (php dgz serve)

Dorguzen ships with a built-in development server powered by PHP's own `-S` flag. It lets you run your application locally without installing Apache, Nginx, MAMP, or any other web server software. One command and you are live.

### Starting the server

Default — starts on http://localhost:8000:

```bash
php dgz serve
```

Custom port:

```bash
php dgz serve --port=9000
```

Expose on your local network (so other devices on the same Wi-Fi can reach it):

```bash
php dgz serve --host=0.0.0.0 --port=9000
```

Once it starts you will see:

```
Dorguzen development server started.
  Listening on:  http://localhost:9000
  Document root: /path/to/your/project
  Press Ctrl+C to stop.
```

Open that URL in your browser and your application loads normally — routing, controllers, views, sessions, everything works exactly as it does under MAMP or Apache. Press Ctrl+C in the terminal to shut it down.

### What it is good for

The built-in server is ideal for:

- Quick local development without configuring a full web server stack
- Working on a machine where MAMP/XAMPP is not installed
- Sharing a prototype with a colleague on the same network (`--host=0.0.0.0`)
- Automated testing pipelines that spin up a real HTTP server temporarily

It is zero-config: no virtual hosts to set up, no DocumentRoot to point, no .conf files to edit. The Dorguzen serve command also sets `upload_max_filesize`, `post_max_size`, and `memory_limit` to generous values automatically, because .htaccess directives are ignored by PHP's built-in server.

### NOT for production — and why

The built-in server must never be used in a live, publicly accessible environment. This is not a Dorguzen limitation — it is a fundamental constraint of PHP's built-in server itself, and PHP's own documentation states this explicitly.

Here is why production web servers like Apache (used by MAMP) and Nginx can handle real traffic, but PHP's built-in server cannot:

1. **Single-threaded, single-process**
   PHP's built-in server handles exactly one request at a time. While it is processing request A, every other incoming request waits in a queue. On a real website with even a handful of simultaneous visitors — or a page that loads a dozen assets (CSS, JS, images) in parallel — requests pile up and the server grinds to a halt.

   Apache and Nginx spawn multiple worker processes or threads and handle hundreds of concurrent connections simultaneously. MAMP uses Apache under the hood, which is why it can serve your application smoothly to a real audience.

2. **No keep-alive or connection pooling**
   Modern browsers open several parallel connections to load a page faster. The built-in server does not support HTTP keep-alive properly, so each asset gets its own slow connection cycle.

3. **No static file optimisation**
   Apache and Nginx serve static files (images, CSS, JS) directly from disk at OS speed, bypassing PHP entirely. The built-in server routes every request — including static assets — through PHP, which is far slower and wastes memory.

4. **No TLS / HTTPS**
   The built-in server speaks plain HTTP only. Production sites require HTTPS. Apache and Nginx handle TLS termination natively (or via a reverse proxy like Certbot/Let's Encrypt).

5. **No process supervision**
   If the built-in server crashes, it stays crashed. Production web servers integrate with systemd, supervisord, or similar process managers that restart them automatically.

In short: MAMP, LAMP, XAMPP, and Nginx are engineered for reliability, concurrency, and security at scale. PHP's built-in server is engineered for a developer to preview their work quickly on their own machine. Use each tool for what it was designed for.

### Database connections

The development server just serves HTTP — it has no database bundled inside it. If your application needs a database (for example MySQL), you need to have MySQL running separately. Two straightforward options:

Option A — Install MySQL locally via Homebrew (macOS):

```bash
brew install mysql
brew services start mysql
```

Then set your .env credentials as usual (`DB_HOST=127.0.0.1`, `DB_USERNAME=root`, etc.).

Option B — Run MySQL in Docker (works on macOS, Linux, Windows):

```bash
docker run --name dgz-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=camerooncom \
    -p 3306:3306 -d mysql:8
```

Then point your .env at 127.0.0.1:3306 with the credentials you passed above.

If you already have MAMP running, you can simply start MAMP (which runs MySQL on port 8889 by default), keep it running in the background, and use `php dgz serve` as the web server instead of MAMP's Apache. This gives you the best of both: MAMP's MySQL without needing MAMP's Apache.
