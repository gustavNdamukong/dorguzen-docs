# Task Scheduler

The Dorguzen scheduler lets you run jobs, commands, and events on a cron-like schedule. You define all tasks in one PHP file. A single OS cron entry fires `php dgz schedule:run` every minute, and Dorguzen handles the rest.

---

## Setup: OS Cron Entry

Add one cron entry to your server:

```
* * * * * php /path/to/your/app/dgz schedule:run >> /dev/null 2>&1
```

This is the only cron entry you ever need.

---

## Defining Scheduled Tasks

All tasks are defined in `src/CLI/console/Schedule.php`:

```php
use Dorguzen\Core\Console\Scheduling\Schedule;

return function (Schedule $schedule): void {

    // Run a job every minute
    $schedule->job(\Dorguzen\Jobs\SendPendingEmailsJob::class)
             ->everyMinute()
             ->withoutOverlapping();

    // Run a CLI command daily at 7am
    $schedule->command('log:prune')
             ->dailyAt('07:00');

    // Fire an event every hour
    $schedule->event(\Dorguzen\Events\HourlyDigestEvent::class)
             ->hourly();
};
```

---

## Task Types

### `$schedule->job()`

Dispatches a job class through the queue system. Respects `QUEUE_DRIVER`.

```php
$schedule->job(\Dorguzen\Jobs\GenerateReportJob::class)->daily();
```

### `$schedule->command()`

Runs a registered DGZ CLI command:

```php
$schedule->command('log:prune')->daily();
```

### `$schedule->event()`

Fires an application event, triggering all registered listeners:

```php
$schedule->event(\Dorguzen\Events\NightlyCleanupEvent::class)->daily();
```

---

## Available Frequencies

| Method | When |
|---|---|
| `->everyMinute()` | Every minute |
| `->hourly()` | Top of every hour |
| `->daily()` | Midnight every day |
| `->dailyAt('08:30')` | At a specific time |
| `->weekly()` | Midnight every Sunday |
| `->monthly()` | Midnight on the 1st |
| `->cron('*/5 * * * *')` | Any valid cron expression |

`->cron()` supports: `*` (any), `5` (exact), `1,3,5` (list), `1-5` (range), `*/5` (step).

---

## Overlap Prevention

By default, concurrent runs of the same task are allowed. To prevent this:

```php
$schedule->job(\Dorguzen\Jobs\SendPendingEmailsJob::class)
         ->everyMinute()
         ->withoutOverlapping();
```

Overlap prevention uses a DB lock table (`dgz_scheduled_task_locks`). A lock is acquired before execution and released after. If the previous run is still in progress, the new run is skipped silently.

Locks expire automatically after 60 seconds. If a process crashes without releasing the lock, it auto-expires on the next run.

---

## Running Manually

```bash
php dgz schedule:run
```

Useful during development — executes all tasks due at the current minute without waiting for the OS cron.

---

## File Locations

| What | Where |
|---|---|
| Task definitions | `src/CLI/console/Schedule.php` |
| `Schedule` class | `core/Console/Scheduling/Schedule.php` |
| `SchedulerLock` | `core/Console/Scheduling/SchedulerLock.php` |
| `CronEvaluator` | `core/Console/Scheduling/CronEvaluator.php` |
| DB lock table | `dgz_scheduled_task_locks` |
