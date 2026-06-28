# Task Scheduler

The DGZ Task Scheduler is Dorguzen's time-based automation engine.

It allows developers to define scheduled:

- Commands
- Jobs
- Events

Using expressive methods such as:

```php
$schedule->command('app:test')->everyMinute();
$schedule->job(MyJob::class)->dailyAt('09:30');
$schedule->event(MyEvent::class)->withoutOverlapping();
```

The scheduler is:

- Cron-driven
- Database-safe
- Overlap-aware
- Framework-integrated
- Fully decoupled from business logic

---

## 🧠 Architectural Philosophy

DGZ follows a strict separation of concerns.

| Component | Responsibility |
|---|---|
| Schedule | Developer task definitions |
| ScheduleLoader | Loads user schedule file |
| ScheduleRunCommand | Orchestrates scheduler run |
| Scheduler | Executes due tasks |
| ScheduledTask | Represents one scheduled task |
| SchedulerLock | Prevents overlapping execution |
| scheduled_task_locks | Database-level locking |

The scheduler:

- Does not contain business logic
- Does not perform long-running loops
- Does not depend on Redis
- Does not require daemon mode

It simply answers:

> "Is this task due now?"
> "If yes, execute it safely."

---

## ⚙️ How It Works Internally

### 1️⃣ Developer Defines Tasks

File:

```
src/CLI/Console/Schedule.php
```

Example:

```php
return function (Schedule $schedule): void {

    $schedule->command('app:test')
            ->everyMinute();

    $schedule->job(\App\Jobs\CleanupJob::class)
            ->dailyAt('09:30');

    $schedule->event(\App\Events\ReportEvent::class)
            ->everyMinute()
            ->withoutOverlapping();
};
```

This file does not execute anything.
It only defines tasks.

### 2️⃣ Cron Triggers the Scheduler

In production:

```bash
* * * * * php /path/to/project/dgz schedule:run
```

This runs once per minute.

DGZ does not daemonize the scheduler.
Cron is the trigger.

### 3️⃣ schedule:run Command

The command:

```bash
php dgz schedule:run
```

It:

- Loads the developer schedule file
- Creates a Schedule object
- Iterates through tasks
- Checks if each task isDue()
- Hands due tasks to Scheduler

### 4️⃣ The Scheduler (Execution Brain)

File:

```
core/Console/Scheduling/Scheduler.php
```

Responsibilities:

- Enforce overlap prevention
- Dispatch commands
- Dispatch jobs
- Fire events
- Release locks safely

It uses:

```php
match ($task->getType()) {
    'command' => $this->runCommand($task),
    'job'     => $this->runJob($task),
    'event'   => $this->runEvent($task),
};
```

It wraps execution in:

```php
try {
  ...
} finally {
  $this->lock->release(...)
}
```

This guarantees:

- No orphaned locks
- No stuck tasks
- Safe failure handling

---

## 🗂 ScheduledTask

Each task internally stores:

- Type (command, job, event)
- Target (string or class)
- Cron expression
- Overlap setting

Example internal representation:

```
command → app:test
cron    → * * * * *
```

It contains:

```php
withoutOverlapping()
preventsOverlapping()
lockKey()
isDue(DateTime $now)
```

---

## ⏱ Cron Expressions

DGZ uses standard 5-field cron format:

```
* * * * *
│ │ │ │ │
│ │ │ │ └── Day of week (0-6) eg Monday
│ │ │ └──── Month (1-12)
│ │ └────── Day of month (1-31) eg 25
│ └──────── Hour (0-23)
└────────── Minute (0-59)
```

Examples:

| Expression | Meaning |
|---|---|
| `* * * * *` | Every minute |
| `0 * * * *` | Every hour |
| `0 0 * * *` | Daily at midnight |
| `0 0 1 * *` | Monthly on 1st |
| `30 9 * * *` | Daily at 09:30 |

Helper methods like:

```php
everyMinute()
daily()
dailyAt('09:30')
weekly()
```

generate cron expressions automatically.

### Supported field operators

Each of the five cron fields is matched by `CronEvaluator`
(`core/console/scheduling/CronEvaluator.php`) and supports:

| Operator | Example | Meaning |
|---|---|---|
| `*` | `*` | Any value |
| exact | `5` | Exactly that value |
| list | `1,3,5` | Any value in the list |
| range | `1-5` | Any value within the range (inclusive) |
| step | `*/5` | Every Nth value (only with a `*` base) |

---

## 🔒 Overlap Prevention

### Why It Exists

Without overlap prevention:

- Slow tasks could be scheduled again
- Duplicate executions could occur
- Data corruption risks increase

DGZ solves this with:

```php
->withoutOverlapping()
```

### Database Locking Strategy

Table:

```sql
CREATE TABLE scheduled_task_locks (
    task_key VARCHAR(255) PRIMARY KEY,
    locked_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL
);
```

### How It Works

- Scheduler attempts to INSERT
- If insert succeeds → lock acquired
- If duplicate key error → skip execution
- Lock released in finally block

No Redis required.
No race conditions.
Pure DB atomicity.

---

## 🚀 What Can Be Scheduled?

### Commands

```php
$schedule->command('queue:work')->everyMinute();
```

Internally executed using Symfony Console:

```php
$app->getConsole()->run(
    new ArrayInput(['command' => 'queue:work']),
    new NullOutput()
);
```

### Jobs

```php
$schedule->job(MyJob::class)->daily();
```

Dispatches to DGZ queue system:

```php
dispatch(new MyJob());
```

### Events

```php
$schedule->event(MyEvent::class)->everyMinute();
```

Triggers event system:

```php
event(new MyEvent());
```

Listeners may:

- Run immediately
- Or be queued

---

## 🔄 Expected Workflow

- Cron runs scheduler every minute
- Scheduler checks due tasks
- Commands run immediately
- Events fire immediately
- Jobs get queued
- Queue worker consumes jobs

Typical production setup:

```bash
* * * * * php /path/dgz schedule:run
```

Queue worker (running continuously):

```bash
dgz queue:work
```

---

## Why Use a Scheduler?

Without it:

- You must manually dispatch tasks
- No time-based automation
- No central orchestration

With it:

- Time-driven automation
- Centralized scheduling
- Overlap protection
- Clean architecture

The scheduler answers:

WHEN should something run?

The queue answers:

HOW should it run?

The job answers:

WHAT should run?

---

## 🛡 Reliability Guarantees (v1)

- Safe DB locks
- No daemon complexity
- Cron-driven simplicity
- Try/finally lock release
- Cross-platform CLI
- Clean separation of concerns

### DGZ Scheduler Status

As declared:

✅ DGZ Task Scheduler is complete for Version 1

Future (optional enhancements):

- Execution history table
- Failure tracking
- Stale lock pruning
- Long-running scheduler mode
- Dashboard insights

None are required for v1 stability.

---

## Example Full Setup

### 1️⃣ Define tasks

```php
$schedule->command('emails:send')->everyMinute();
$schedule->job(CleanupJob::class)->dailyAt('02:00');
$schedule->event(ReportGenerated::class)->weekly();
```

### 2️⃣ Add cron

```bash
* * * * * php /path/to/project/dgz schedule:run
```

### 3️⃣ Run queue worker

```bash
dgz queue:work
```

Done.

---

## Other examples

```php
$schedule = new \Dorguzen\Core\Console\Scheduling\Schedule();

(require base_path('src/CLI/console/Schedule.php'))($schedule);

dump('Scheduled tasks', $schedule->getTasks());

foreach ($schedule->getTasks() as $task) {
    dump($task->getType().' | '.$task->getTarget().' | '.$task->getExpression()?->value());
}
```

---

## 🎯 Final Summary

The DGZ Task Scheduler:

- Is cron-driven
- Is database-safe
- Prevents overlap
- Supports commands, jobs, events
- Integrates seamlessly with the queue system
- Requires zero external services

Here is the architectural structure and list of some key classes involved:

`src/CLI/console/Schedule.php` containing the Schedule class.

The Schedule class contains the user-defined scheduled tasks.

Tasks that can be defined are commands, jobs, and events.
This makes it so powerful because it consolidates the power of
Dorguzen's other sub-systems:

- The Events system
- The Queue system
- Console commands.

all of which can be scheduled to automatically run at specific dates and times decided by you.

The commands, jobs and events have to be wrapped into concrete classes to be run by the scheduler.
These concrete classes are three in number, which we can refer to as the 'schedule type' classes:

- ScheduledCommand
- ScheduledJob
- ScheduledEvent

ScheduledTask is a class that defines the contract to be followed by the schedule type (concrete) classes.
ScheduleRunner is a class that runs the schedule type classes.
CronExpression defined in CronExpression.php defines the timings (date/times) for tasks to be run.

---

## Running the Scheduler in Different Environments

Emails are sent by the scheduler, not by the web request. You must ensure the
scheduler runs regularly in every environment.

**LOCAL DEVELOPMENT (MAMP / local PHP)**

Run the scheduler manually in a terminal whenever you want to send queued
emails:

```bash
php dgz schedule:run
```

There is no need to set up a cron locally — manual triggering is the
simplest approach during development and testing.

**SHARED HOSTING**

Most shared hosts provide a "Cron Jobs" section in cPanel or a similar
control panel. Add a cron job that runs every minute:

```bash
* * * * * cd /home/youruser/public_html/yourapp && php dgz schedule:run
```

Check your host's documentation for the correct PHP binary path — it is
often something like /usr/local/bin/php or /opt/alt/php82/usr/bin/php.

**VPS / DEDICATED SERVER**

Edit the server's crontab:

```bash
crontab -e
```

Add:

```bash
* * * * * cd /var/www/yourapp && php dgz schedule:run >> /dev/null 2>&1
```

Optionally log output for debugging:

```bash
* * * * * cd /var/www/yourapp && php dgz schedule:run >> /var/log/dgz-scheduler.log 2>&1
```

**DOCKER / CONTAINERS**

Either add the cron to a Dockerfile, or run the scheduler in a sidecar
container with a simple shell loop:

```bash
while true; do php dgz schedule:run; sleep 60; done
```

IMPORTANT — each environment manages its own cron independently. Setting up
a cron on your local Mac has NO effect on your production server, and vice
versa. Configure the cron on the server where the app is deployed.

---

## Troubleshooting the Scheduler

Symptom: Scheduler runs ("Schedule run process complete") but emails stay
as 'pending' and nothing is sent.

**Cause 1 — Stale lock in dgz_scheduled_task_locks**

If a previous scheduler run crashed before it could clean up, a lock row
may have been left in the dgz_scheduled_task_locks table. While the lock
is present, every subsequent run silently skips the job.

Fix (Dorguzen 1.x and later): The lock system automatically deletes expired
locks before trying to acquire a new one. Locks expire after 60 seconds, so
simply wait one minute and run the scheduler again.

Manual fix (if you need to unblock immediately):

```sql
DELETE FROM dgz_scheduled_task_locks;
```

**Cause 2 — dgz_scheduled_task_locks table does not exist**

The scheduler silently skips all ->withoutOverlapping() jobs if the lock
table is missing. Create it — see the Infrastructure Table section for the
CREATE TABLE statement.

**Cause 3 — Wrong SMTP credentials**

Check your .env file:

```
MAIL_HOST=sandbox.smtp.mailtrap.io   (Mailtrap for dev)
MAIL_PORT=587
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
```

If credentials are wrong the scheduler will mark the row as 'failed' and
log the error. Check bootstrap/logs/php_errors.log or the server error log.

**Cause 4 — Template file missing**

If newsletters.newsletter_template references a file that does not exist
(e.g. 'welcome_mail' instead of 'newsletter-welcome'), the scheduler will
throw an error. Reset status = 'pending' in pending_emails after creating
or correcting the template file, then run the scheduler again.

**Cause 5 — Subscriber marked as inactive**

A subscriber with subscriber_active = 0 (Unsubscribed) will always be
skipped. Even if their ID is in the pending_emails queue, the service
checks the live subscriber record at send time and skips inactive ones.

---

## The Scheduler — How It Works

The scheduler is the engine that processes the pending_emails queue. It is
NOT a background daemon — it is a one-shot CLI command:

```bash
php dgz schedule:run
```

Each time it runs it:

1. Loads src/CLI/console/Schedule.php and reads all registered tasks
2. For each task, checks whether its frequency filter is satisfied
   (e.g. ->everyMinute() means "run if at least 60 seconds have elapsed
   since the last run" — it does NOT mean "loop forever every minute")
3. Acquires a DB lock (dgz_scheduled_task_locks table) via ->withoutOverlapping()
   to prevent two concurrent runs of the same job
4. Dispatches the job — for QUEUE_DRIVER=sync this calls handle() immediately
5. Releases the lock
6. Exits

The SendPendingEmailsJob is registered in src/CLI/console/Schedule.php:

```php
$schedule->job(\Dorguzen\Jobs\SendPendingEmailsJob::class)
         ->everyMinute()
         ->withoutOverlapping();
```

Each run of handle() processes up to 50 pending rows and marks each one
as 'sent' or 'failed'. If a send fails, the row stays as 'failed' and the
tries counter is incremented — it will NOT be retried automatically unless
you reset the status to 'pending' manually.

---

## Running the Scheduler

**Option A — Manual run (development / testing)**

```bash
cd /path/to/your/app
php dgz schedule:run
```

Run this after queuing emails to process them immediately. Useful during
development when you want to trigger sends on demand.

**Option B — Server cron job (recommended for production)**

Set up a cron job on your server that calls the scheduler every minute.
This is the standard approach used by most PHP frameworks (Laravel, Symfony,
etc.):

```bash
# crontab -e
* * * * * cd /path/to/your/app && php dgz schedule:run >> /dev/null 2>&1
```

Breaking this down:

```
* * * * *       — fire every minute
cd /path/...    — change to the app directory (required — the CLI needs
                  to find bootstrap/app.php relative to the working dir)
php dgz schedule:run  — the one-shot scheduler command
>> /dev/null 2>&1     — discard output (or redirect to a log file
                         e.g. >> /var/log/dgz-scheduler.log 2>&1)
```

The scheduler's ->everyMinute() filter then controls which registered jobs
actually run on any given invocation. Cron provides the heartbeat; the
scheduler provides the frequency logic.

To use a log file instead of discarding output:

```bash
* * * * * cd /path/to/app && php dgz schedule:run >> /var/log/dgz-scheduler.log 2>&1
```

**Option C — Supervisor / process manager (alternative to cron)**

If your server has Supervisor installed, you can keep a persistent worker
process running instead of cron:

```
[program:dgz-scheduler]
command=bash -c "while true; do php dgz schedule:run; sleep 60; done"
directory=/path/to/your/app
autostart=true
autorestart=true
stderr_logfile=/var/log/dgz-scheduler.err.log
stdout_logfile=/var/log/dgz-scheduler.out.log
```

This shell loop runs the scheduler, sleeps 60 seconds, then runs it again —
effectively the same behaviour as a per-minute cron job, but managed by
Supervisor which handles auto-restart if the process dies.

Which option to use:

```
Development        → Option A (manual, on demand)
Shared hosting     → Option B (cron — most hosts provide crontab access)
VPS / dedicated    → Option B or C (both work; Supervisor gives better
                     visibility and auto-restart)
Docker / containers → Option C or a dedicated sidecar container running
                      the cron
```

---

## The Infrastructure Table: dgz_scheduled_task_locks

The ->withoutOverlapping() call on a scheduled job uses a database table to
prevent two concurrent runs of the same job:

```
dgz_scheduled_task_locks
    task_key    VARCHAR  — the job class name (unique key)
    locked_at   DATETIME — when the lock was acquired
    expires_at  DATETIME — when the lock should be considered stale
```

This table must exist in your database. It is NOT created by a migration file
— it is an infrastructure table that should be created when setting up the
database for any Dorguzen application. Create it with:

```sql
CREATE TABLE dgz_scheduled_task_locks (
    task_key   VARCHAR(191) NOT NULL,
    locked_at  DATETIME     NOT NULL,
    expires_at DATETIME     NOT NULL,
    PRIMARY KEY (task_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

If this table is missing, ->withoutOverlapping() silently treats every job as
"already running" and skips it — the scheduler will appear to run but nothing
will be processed. This is a known gotcha: always verify this table exists
when setting up a new environment.

---

## Powering It with the Dorguzen Scheduler

Dorguzen includes an internal task scheduler that removes the need for
a cPanel cron job or a shell-level crontab entry for most scheduled work.

Architecture:

```
Schedule           — a fluent registry of tasks (command, job, or event)
                     defined in src/CLI/console/Schedule.php.
ScheduleLoader     — loads that file and returns the populated Schedule
                     object to the runner.
ScheduleRunCommand — the CLI command (schedule:run) that iterates tasks,
                     checks whether each is due, and calls Scheduler::run().
Scheduler          — dispatches the task to the correct subsystem
                     (CLI, queue, or event bus) and enforces overlap locking.
```

Registering SendPendingEmailsJob:

In src/CLI/console/Schedule.php return a closure that accepts a Schedule
instance and registers the job:

```php
use Dorguzen\Core\Console\Scheduling\Schedule;

return function (Schedule $schedule): void {
    $schedule->job(\Dorguzen\Jobs\SendPendingEmailsJob::class)
             ->everyMinute()
             ->withoutOverlapping();
};
```

The ->withoutOverlapping() call acquires a database lock when the task
starts. If the previous run has not finished (e.g. a large batch is
still sending), the new run is skipped silently, preventing duplicate
sends.

Starting the scheduler:

```bash
php dgz schedule:run
```

IMPORTANT — this is a one-shot command. Each invocation:

1. Checks which registered tasks are due right now.
2. Runs any that are due.
3. Exits.

It does NOT stay running in the background or loop by itself. The
->everyMinute() / ->hourly() / ->dailyAt() calls are filters — they
tell the scheduler "only run this task when the command is invoked AND
the required interval has elapsed since the last run." They do not make
the command self-perpetuating.

To achieve continuous, automatic execution you need something external
to invoke php dgz schedule:run repeatedly (see the production
recommendation below).

Available frequency helpers on a registered task:

```
->everyMinute()          runs every minute (* * * * *)
->hourly()               runs at the top of every hour
->daily()                runs at midnight every day
->dailyAt('08:00')       runs at 08:00 every day
->weekly()               runs at midnight on Sunday
->monthly()              runs at midnight on the 1st of each month
->cron('*/5 * * * *')    raw cron expression for any other cadence
```

Production recommendation:

Use Supervisor (or an equivalent process manager) to keep a loop alive
that fires php dgz schedule:run every minute:

```
[program:dgz-scheduler]
command=bash -c "while true; do php /path/to/app/dgz schedule:run; sleep 60; done"
autostart=true
autorestart=true
```

On shared hosting without Supervisor, a single crontab entry that runs
the command every minute achieves the same result:

```bash
* * * * * php /path/to/app/dgz schedule:run >> /dev/null 2>&1
```

Either way, only one entry point is needed — the Schedule.php file
controls everything else from inside the codebase.

---

## Why Not Sync Queue / RabbitMQ / Standard Cron?

**Sync queue (QUEUE_DRIVER=sync):**

Jobs run inline before the HTTP response is returned. For a list of
500 subscribers this means the admin waits 30–90 seconds for the page
to load, often hitting PHP's max_execution_time limit. Sync is fine for
development but unsuitable for any list with more than a handful of
recipients.

**RabbitMQ:**

RabbitMQ is the right tool for very high-throughput messaging at scale.
However it requires a separate broker process, additional system
dependencies (php-amqplib), and familiarity with exchange/queue
topology. For a newsletter feature on a self-hosted PHP application
this is significant infrastructure overhead.

**Standard cron (server-level crontab or cPanel scheduler):**

Cron works, but it requires SSH or hosting-panel access to configure,
is not part of the codebase, and is easy to lose track of across
deployments. New team members have no visibility into what is scheduled
unless they check the server.

**Dorguzen Scheduler:**

The scheduler runs inside the application process, is registered in
plain PHP (src/CLI/console/Schedule.php), is version-controlled
alongside the rest of the code, requires no external services, and
works identically on a local Mac, a shared hosting account, and a
cloud VPS. The pending_emails table provides full observability into
what was sent, when, and whether it failed.
