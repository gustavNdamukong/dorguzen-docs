# CLI

Dorguzen includes a CLI tool (`dgz`) for scaffolding, database management, caching, queue management, and running the dev server. All commands run via the `dgz` entry point in your project root.

```bash
php dgz <command> [options]
```

If you've installed the CLI globally (`php dgz install`), you can also run `dgz <command>` from anywhere.

---

## Setup

### Make `dgz` globally available

```bash
php dgz install
```

On macOS/Linux this symlinks `dgz` to `/usr/local/bin/dgz`. On Windows it creates `dgz.bat` in the project root.

### Dev server

```bash
php dgz serve                  # starts at localhost:8000
php dgz serve --host=0.0.0.0 --port=8080
```

---

## Command Reference

### Scaffolding (make:*)

| Command | What it creates |
|---|---|
| `make:controller MyController` | `src/controllers/MyControllerController.php` |
| `make:api-controller Foo --api-version=v1` | `src/api/V1/Controllers/FooController.php` |
| `make:model MyModel` | `src/models/MyModel.php` |
| `make:middleware MyMiddleware` | `src/middleware/MyMiddleware.php` |
| `make:command MyCommand` | `src/CLI/Commands/MyCommand.php` |
| `make:event MyEvent` | `src/events/MyEvent.php` |
| `make:job MyJob` | `src/Jobs/MyJobJob.php` |
| `make:factory MyFactory` | Generates a model factory with seeding helpers |
| `make:seeder MySeeder` | `src/database/seeders/MySeeder.php` |
| `make:test MyTest` | `tests/feature/MyTest.php` |
| `make:jetform MyForm` | Generates a JetForm class |

### Database

| Command | Description |
|---|---|
| `migrate` | Run pending migrations |
| `migrate --file=001_create_users` | Run a single migration file |
| `migrate --pretend` | Print SQL without executing |
| `migrate:rollback` | Roll back the last batch |
| `migrate:status` | Show which migrations have/haven't run |
| `migrate:refresh` | Roll back all, then re-run all |
| `migrate:fresh` | Drop all tables, then re-run all (asks for confirmation) |
| `migrate:fresh --force` | Skip confirmation |
| `db:seed` | Run the main `DatabaseSeeder` |
| `db:seed --class=MySeeder` | Run a specific seeder class |
| `db:seed --all` | Run all seeder classes |
| `db:seed --pretend` | Print actions without executing |

### Queue

| Command | Description |
|---|---|
| `queue:work` | Start the queue worker daemon |
| `queue:work --once` | Process one job then exit |
| `queue:work --sleep=N` | Seconds to sleep when queue is empty |
| `queue:work --max-jobs=N` | Exit after N jobs processed |
| `queue:work --timeout=N` | Max seconds per job (default 60) |
| `queue:jobs` | List pending jobs |
| `queue:stats` | Show pending and failed job counts |
| `queue:failed` | List failed jobs |
| `queue:retry <id>` | Retry a failed job by ID |
| `queue:retry all` | Retry all failed jobs |
| `queue:forget <id>` | Permanently delete a failed job |
| `queue:clear` | Clear all pending jobs |

### Cache

| Command | Description |
|---|---|
| `cache:config-clear` | Delete `bootstrap/cache/config.php` (rebuilds on next request) |
| `cache:route-cache` | Serialize routes to `storage/cache/routes.php` |
| `cache:route-clear` | Delete the route cache |
| `middleware:cache` | Cache the middleware pipeline |
| `middleware:clear` | Clear the middleware cache |

### Scheduling

| Command | Description |
|---|---|
| `schedule:run` | Run all tasks due at the current minute |

### Inspection

| Command | Description |
|---|---|
| `routes` | List all registered routes |
| `config` | Dump config values |
| `logs` | View the application log |
| `log:tail` | Tail the log file live |
| `log:prune` | Delete old log entries |
| `env:check` | Report any `.env.example` variables missing from live `.env` |

### Testing

```bash
php dgz test                         # run all tests
php dgz test --filter SomeTest       # filter by class or method name
php dgz test tests/feature/http/     # run a specific directory
php dgz test --stop-on-failure
php dgz test --coverage-text
```

---

## Writing Custom Commands

### Generate the scaffold

```bash
php dgz make:command SendDailyReport
```

Creates `src/CLI/Commands/SendDailyReport.php`:

```php
namespace Dorguzen\CLI\Commands;

use Dorguzen\Core\CLI\command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendDailyReport extends AbstractCommand
{
    protected static $defaultName        = 'app:send-daily-report';
    protected static $defaultDescription = 'Describe what this command does';

    public function __construct($container)
    {
        parent::__construct($container);
    }

    protected function configure(): void
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription);
    }

    protected function handle(): int
    {
        $this->output->writeln('Running...');
        return self::SUCCESS;
    }
}
```

### Auto-registration

Any class in `src/CLI/Commands/` that extends `AbstractCommand` is auto-discovered and registered. No manual wiring needed.

### Adding arguments and options

```php
protected function configure(): void
{
    $this
        ->setName('app:send-daily-report')
        ->addArgument('email', InputArgument::REQUIRED, 'Recipient email')
        ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without sending');
}

protected function handle(): int
{
    $email  = $this->input->getArgument('email');
    $dryRun = $this->input->getOption('dry-run');

    if ($dryRun) {
        $this->output->writeln("Dry run — would send to {$email}");
        return self::SUCCESS;
    }

    // real logic here

    return self::SUCCESS;
}
```

### Accessing services

The DI container is available as `$this->container`:

```php
protected function handle(): int
{
    $mailer = $this->container->get(DGZ_Messenger::class);
    // ...
    return self::SUCCESS;
}
```

---

## Scheduling Custom Commands

Register commands on a schedule in `src/CLI/console/Schedule.php`:

```php
$schedule->command('app:send-daily-report')->dailyAt('07:00');
```

See the [Task Scheduler](/docs/scheduler) page for all frequency options.
