# Queues & Jobs

## The dispatch() Helper (For Jobs)

Beside the `event()` helper, Dorguzen also has `dispatch()`:

```php
dispatch($job);
```

This is different from `event()`.

🔹 `event()` is used for domain events.

It may:

- Run immediately
- Or create queued jobs (if listeners implement `ShouldQueue`)

🔹 `dispatch()` on the other hand is used when you explicitly want to queue a job.

Example:

```php
dispatch(new GenerateMonthlyReport($month));
```

This bypasses the Event layer entirely and goes straight to the:

- QueueManager → which uses the active queue driver set in config (`'sync'` or `'db'`) to know how to queue the job for processing.

Here is an example of what the queue setting in your `.env` (config) file looks like:

```ini
# Queueing. Options: sync, db
# If the driver is db, you need an existing worker loop (CLI command), which uses the jobs table
QUEUE_DRIVER=db
```

### When To Use Each?

| Use Case | Helper |
|---|---|
| Something happened in your domain | `event()` |
| You want to queue specific work | `dispatch()` |

### Important Distinction

Not all events become jobs.
Only listeners implementing `ShouldQueue` do.

But all jobs dispatched with `dispatch()` are jobs immediately.

---

## How Database Queue Works

When using DatabaseQueue driver:

```php
'queue.default' => 'database'
```

### When a Job is Pushed

```php
DatabaseQueue->push($job);
```

It inserts into the `dgz_jobs` table:

```sql
INSERT INTO dgz_jobs (
    queue,
    payload,
    attempts,
    max_attempts,
    available_at,
    created_at
)
VALUES (...)
```

Where the payload value will be typically the listener class serialized e.g.

```php
serialize($job)
```

as well as other meta data about the event.

`attempts` will be like 3 or however maximum number of times you would like your queue worker to attempt to consume the job before permanently marking it as failed.

---

## How Jobs Are Processed (Worker)

Dorguzen has a queue worker daemon which you can fire up to run permanently, waiting to consume jobs. Start it using the following command:

```bash
php dgz queue:work
```

Internally, this command grabs all existing jobs (event listener classes) from the `dgz_jobs` table, one by one, unserializes them, and consumes them by running their `handle()` method which all event listener classes must have.

```php
while (true) {
    $job = $queue->pop();

    if ($job) {
        $job->handle();
    }
}
```

---

## Handling Race Conditions (Very Important)

This is critical for production systems.
Because multiple workers may be running at the same time, the Dorguzen queue worker in order to prevent workers processing the same job more than once; it implements some atomic DB operations that involve locking of the database row to mark that job as already being processed so other queue workers will skip it, and then marking the job as completed when its done by updating a status flag on the table. The field that is used in this locking is the `reserved_at` field on the same `dgz_jobs` table.

Typical safe pattern: Before any queue worker grabs a job to process, it makes sure it only grabs those whose `reserved_at` field has a value of NULL.

```sql
SELECT * FROM jobs
WHERE reserved_at IS NULL
ORDER BY id
LIMIT 1
FOR UPDATE
```

Then when it is done processing each job, it sets the flag by updating that same `reserved_at` field to the current timestamp e.g:

```sql
UPDATE jobs
SET reserved_at = NOW()
WHERE id = ?
```

This ensures:

- ✔ Only one worker reserves a job
- ✔ Other workers skip it
- ✔ No double processing

This is called pessimistic locking.

---

## SyncQueue vs DatabaseQueue

Let us look at the characteristics of the two types of queues Dorguzen uses; SyncQueue and DatabaseQueue.

### SyncQueue

- No DB
- No workers
- Immediate execution
- Useful for development

### DatabaseQueue

- Persistent storage
- Background workers
- Safe concurrent processing
- Retry capability
- Scalable

---

## Full Lifecycle Summary

```
Developer calls event()
        ↓
EventDispatcher
        ↓
Resolve listeners
        ↓
If NOT ShouldQueue:
        → handle immediately

If ShouldQueue:
        ↓
Create QueuedJob
        ↓
QueueManager
        ↓
SyncQueue OR DatabaseQueue
        ↓
(If database)
Stored in jobs table
        ↓
Queue Worker
        ↓
pop()
        ↓
handle()
```

---

## Why This Design Is Powerful

- ✔ Domain logic stays clean
- ✔ Async behavior is opt-in via interface
- ✔ Drivers are swappable
- ✔ Safe concurrent job processing
- ✔ Clear separation of responsibilities

### Final Developer take-away points

If I'm building on Dorguzen:

- I create events to describe domain occurrences
- I attach listeners to react to those occurrences
- I implement `ShouldQueue` when work should be async
- I use `dispatch()` when I want direct job execution
- I rely on Dorguzen to:
  - Serialize jobs
  - Store them
  - Prevent race conditions
  - Process them safely via workers

---

## The Jobs processing lifecycle

**The Dorguzen Job Processing Lifecycle (Deep Explanation)**

Before anything else, ensure your application has these two tables:

- `dgz_jobs`
- `dgz_failed_jobs`

Run migrations if they do not exist. Dorguzen ships with the migration files for these tables, so running your migrations will set them up for you.

These two tables are the foundation of asynchronous processing in Dorguzen.

---

### Events vs Jobs (Re-stated for Clarity)

This distinction is extremely important.

#### Events

Live in: `src/events/`

Represent something that happened. They may or may not be queued. They only become queued if their listener implements `ShouldQueue`.

#### Jobs

Live in: `src/jobs/`

They always use the queue system. They are explicitly dispatched using `dispatch()`.
They never run outside the queue system.

So remember:

All Jobs are queued.
Not all Events are queued.

An Event only enters the queue system if its listener implements:

```php
implement Dorguzen\Core\Events\ShouldQueue;
```

---

### How an Event Reaches the Queue System

Let's repeat this carefully. A developer calls:

```php
event(new UserRegistered($user));
```

EventService passes it to EventDispatcher.
EventDispatcher resolves listeners using ListenerResolver.

For each listener:

- if it does NOT implement `ShouldQueue`:
  - run its `handle()` method immediately

- If it DOES implement `ShouldQueue`:
  - it is wrapped into a QueuedListener
  - and passed to `QueueManager->push()`

This is the exact bridge between Events and the Queue system.

Only here does the Event system hand over control to the Queue.

---

### The Queue System (Deep Internal Explanation)

Now we move fully into the Queue system.

The queue system consists of:

- QueueManager
- QueuedJob
- Drivers:
  - QueueInterface
  - SyncQueue
  - DatabaseQueue

The active driver is controlled by the configuration setting:

```php
'queue_driver'
```

which can either be

```
'sync' or 'db'
```

---

### DatabaseQueue — The Asynchronous Engine

This is where things become serious. DatabaseQueue stores jobs inside:

- the `dgz_jobs` table

Here is the structure of the `dgz_jobs` table:

```
id
queue
payload
attempts
max_attempts
reserved_at
available_at
failed_at
created_at
reserved_at
```

---

### When a Job Is Pushed

That means the job is queued; it is initiated from `Dorguzen\Core\Queues\QueueManager`, which checks for the active queue driver in configs

`'queue_driver'` which can either be SyncQueue or DatabaseQueue, and the signature of the `push()` method in both drivers looks like this:

```php
public function push(object $job, ?int $delaySeconds = null)
```

As mentioned, if the active queue driver is DatabaseQueue, it inserts into the database:

```php
$this->db->insert('dgz_jobs', [
    'queue'        => 'default',
    'payload'      => serialize($job),
    'attempts'     => 0,
    'max_attempts' => 3,
    'available_at' => date(...),
    'created_at'   => date(...),
]);
```

Important details:

- `payload` is serialized.
- `attempts` starts at 0. The `attempts` field represents how many times (tries) workers tried to process the job
- `max_attempts` defaults to 3.
- `available_at` controls when the job becomes eligible for processing.
- `reserved_at` is NULL initially.

---

### The Worker Process

The worker runs in CLI:

```bash
php dgz queue:work
```

Internally, it is a daemon that runs continuously once started:

```php
while (true) {
    $job = $queue->pop();

    if ($job) {
        try {
            $job->handle();
            $queue->acknowledge($job);
        } catch (Throwable $e) {
            $queue->release($job);
        }
    }
}
```

This is the full lifecycle.

`pop()` — is how a driver safely claims a job from the database.
This is the most important part of your entire system.

Let's walk through it carefully.

#### Step 1 — Find Available Job

```sql
SELECT * FROM dgz_jobs
WHERE reserved_at IS NULL
AND available_at <= NOW()
ORDER BY id ASC
LIMIT 1
```

This SQL query means:

- get a job that is not already reserved (`reserved_at IS NULL`). This is the locking mechanism that ensures that two queue workers do not process the same job.
- The job is due for processing, and not delayed (`<= NOW()`)
- get the oldest job first in FIFO style (`ORDER BY id ASC`)

NULL will be returned if none found.

#### Step 2 — Attempt to Reserve It (Race Condition Protection)

Now comes the critical part. After a job is processed, this is done:

```sql
UPDATE dgz_jobs
SET reserved_at = NOW(),
    attempts = attempts + 1
WHERE id = ?
AND reserved_at IS NULL
```

Notice this:

```sql
AND reserved_at IS NULL
```

This is optimistic locking. Why? Because multiple workers may:

Read the same job, and therefore try to reserve it simultaneously. This lock mechanism ensures that only ONE worker will successfully update the row, preventing double execution.
The other queue workers running will skip a row that's already taken by another worker:

- Affecting 0 rows
- Returning null
- Will skip processing of that row

This is your race-condition safety mechanism.

---

### Attempts and Retries Explained

When a job is first inserted:

```
attempts = 0
max_attempts = 3
```

When a worker reserves it, it increments the attempts number by 1:

```
attempts = attempts + 1
```

So on first execution attempt, `attempts` will be equal to 1:

```
attempts = 1
```

---

### What Happens If Job Succeeds?

The worker marks the job as done. It does so by running this line:

```php
$queue->acknowledge($job);
```

Which basically removes the Job from the `dgz_jobs` database table, effectively removing it from the queue.

```sql
DELETE FROM dgz_jobs WHERE id = ?
```

---

### What Happens If Job Fails?

If an exception is thrown:

```php
catch (Throwable $e) {
    $queue->release($job);
}
```

Now we enter retry logic.

---

### release() — Retry Logic

When grabbing jobs to process, the system checks if the number of tries has not exceeded the maximum number of attempts.

```php
if ($job->attempts >= $job->maxAttempts)
```

If the attempts have reached the max:

- the job is moved to the `dgz_failed_jobs` table.
- and then deleted from `dgz_jobs`

This is poison-job protection.

If the attempts have not reached the max, it is marked as taken (`reserved_at`), then processed, and then marked as released again (`reserved_at = NULL`), with its `attempts` value incremented by 1:

```sql
UPDATE dgz_jobs
SET reserved_at = NULL,
    attempts = ?,
    available_at = future_time
WHERE id = ?
```

What happens here?

- `reserved_at` becomes NULL → job becomes available again
- `attempts` stays incremented
- `available_at` is pushed into future (default 5 seconds)

This prevents immediate hammering retries.

---

### fail() — Permanent Failure Handling

When job has exceeded its max attempts and the processing was unsuccessful, it is saved into `dgz_failed_jobs` and removed from `dgz_jobs`:

```php
$this->db->insert('dgz_failed_jobs', [
    'queue' => ...,
    'payload' => serialize(...),
    'exception' => message,
    'exception_trace' => trace,
    'attempts' => ...,
    'failed_at' => ...
]);
```

And:

```sql
DELETE FROM dgz_jobs WHERE id = ?
```

So:

- ✔ Job is removed from active queue
- ✔ Failure is recorded permanently
- ✔ Debugging information preserved

---

### How the 'available_at' field delays execution

This field has two roles:

- Delay execution when first pushed.
- Delay retries.

A job will not be picked up unless:

- `available_at` is less than, or equal to the current timestamp (`available_at <= NOW()`)

This is how Dorguzen implements:

- Delayed jobs
- Retry backoff
- Scheduled execution timing

---

### The 'reserved_at' — Locking Mechanism

This field is critical. As a reminder, here are things to know:

When its value is NULL, it means:

- The Job is free

When its value is the current timestamp, it means:

- The Job is currently being processed

Workers only pick jobs where:

```sql
reserved_at IS NULL
```

This prevents duplicate execution.

---

### Summary of Full Lifecycle

Let's describe the full journey.

#### Step 1 — Job Inserted

```
attempts = 0
reserved_at = NULL
available_at = NOW
```

#### Step 2 — Worker Finds Job

Queue workers check if:

```
reserved_at IS NULL
available_at <= NOW
```

#### Step 3 — Worker Claims matching Job, runs it and updates it like so:

```
reserved_at = NOW
attempts = attempts + 1
```

Only one worker succeeds.

#### Step 4 — Job Runs

If it succeeds:

- it does `acknowledge()` (which basically means it deletes it from the queue)

If it fails:

- it does a `release()` from the queue (which basically means it inserts it into the `dgz_failed_jobs` table and deletes it from the `dgz_jobs` table)

#### Step 5 — Retry or Fail Permanently

If `attempts < max_attempts` - repeat the cycle:

```
reserved_at = NULL
available_at = NOW + delay
```

If `attempts >= max_attempts`:

```sql
INSERT INTO dgz_failed_jobs
DELETE FROM dgz_jobs
```

---

### Poison Job Protection

A poison job is a job that will never pass processing, either because there could be an error in the process. It will therefore wear on your system to keep using resources in trying to process it endlessly.
The maximum attempts limitation protects your system from infinite loops.

Without this:

- A broken job could retry forever
- your queue will be overloaded
- It will cause CPU exhaustion

That is why Dorguzen stops the processing after `max_attempts`. You can adjust this attempt figure.

---

### Summary of the features of the Dorguzen queue system

- It is driver-based
- It supports sync and database
- Protects against race conditions
- Supports retries
- Supports delayed jobs
- Records permanent failures
- Safely deletes successful jobs
- And most importantly; the developer does not need to manage any of this manually.

They simply run:

```php
dispatch(new ProcessReport());
// or
queue(new ProcessReport());
```

Or:

```php
event(new UserRegistered());
```

And Dorguzen handles:

- Serialization
- Locking
- Retrying
- Backoff
- Failure storage
- Concurrency protection etc

---

## Writing Custom Job Classes in Dorguzen

Remember:

Jobs live in `src/jobs/`

Jobs ALWAYS use the queue system.

Jobs are dispatched using the `dispatch()` helper.

Jobs must contain a `handle()` method.

A Job is simply a class whose `handle()` method contains the logic you want to run asynchronously.

### Generating a Job (make:job)

Rather than hand-writing the file, you can scaffold a Job class with the CLI:

```bash
php dgz make:job SendInvoice
```

This generates a ready-to-edit class (the `Job` suffix is appended automatically if you omit it) with a stubbed `handle()` method. Just fill in your logic:

```php
namespace Dorguzen\Jobs;

class SendInvoiceJob
{
    public function handle(): void
    {
        // TODO: Implement Job logic
    }
}
```

### Example 1 — A Job That Runs Immediately

Let's create:

`src/jobs/LogSomethingJob.php`

```php
namespace Dorguzen\Jobs;

class LogSomethingJob
{
    public function handle(): void
    {
        file_put_contents(
            storage_path('logs/test.log'),
            "Job ran at: " . date('Y-m-d H:i:s') . PHP_EOL,
            FILE_APPEND
        );

        echo "LogSomethingJob executed successfully.\n";
    }
}
```

Now dispatch it:

```php
dispatch(new \Dorguzen\Jobs\LogSomethingJob());
```

#### What Happens Internally?

If your `queue_driver` is:

```php
'queue_driver' => 'sync'
```

- → The job runs immediately
- → `handle()` executes instantly
- → No database entry created

If your `queue_driver` is:

```php
'queue_driver' => 'db'
```

- → Job is inserted into `dgz_jobs`
- → Worker must process it

### Example 2 — A Job That Runs in 5 Minutes

```php
namespace Dorguzen\Jobs;

class DelayedEmailJob
{
    public function handle(): void
    {
        echo "Delayed job executed at: " . date('Y-m-d H:i:s') . "\n";
    }
}
```

Dispatch it with delay:

```php
dispatch(new \Dorguzen\Jobs\DelayedEmailJob(), 300);
```

(300 seconds = 5 minutes)

#### What Happens?

`DatabaseQueue->push()` sets:

```php
'available_at' => NOW + 300 seconds
```

The worker will ignore this job until:

```
available_at <= current time
```

Even if worker is running, it will skip it until eligible.

### Example 3 — A Job That Fails (To Test Retries)

```php
namespace Dorguzen\Jobs;

class FailingJob
{
    public function handle(): void
    {
        echo "FailingJob running...\n";

        throw new \Exception("This job failed intentionally.");
    }
}
```

Dispatch:

```php
dispatch(new \Dorguzen\Jobs\FailingJob());
```

#### What Happens When Worker Runs?

Let's assume:

```
max_attempts = 3
```

Attempt 1:

- Worker reserves job
- `attempts` becomes 1
- `handle()` throws exception
- `release()` called
- `reserved_at` set to NULL
- `available_at` set to NOW + 5 seconds

Attempt 2:

- `attempts` becomes 2
- fails again
- delayed again

Attempt 3:

- `attempts` becomes 3
- fails again
- `release()` sees `attempts >= max_attempts`
- `fail()` is triggered

Now:

- ✔ Job inserted into `dgz_failed_jobs`
- ✔ Job removed from `dgz_jobs`
- ✔ Exception message + trace stored

This is poison job protection.

### Running the Queue Worker

To process queued jobs:

```bash
php dgz queue:work
```

This starts a long-running CLI process.

The command also accepts several aliases — `q:work`, `qw`, `queue:consume`, `q:consume` — and the following options:

| Option | Description |
|---|---|
| `--once` | Process a single job, then exit |
| `--sleep=N` | Seconds to sleep when no job is available (default `1`) |
| `--max-jobs=N` | Exit after processing N jobs |
| `--timeout=N` | Max execution time per job, in seconds (default `60`) |

```bash
php dgz queue:work --once
php dgz queue:work --sleep=5
php dgz queue:work --max-jobs=10
php dgz queue:work --timeout=300
```

#### What Happens When You Run queue:work?

Internally, the worker:

- Continuously calls `pop()`

If a job is returned:

- Runs `$job->handle()`
- If success → `acknowledge()`
- If failure → `release()`

If no job:

- Prints: "No jobs in the queue"
- Sleeps briefly
- Loops again

#### What Developers Will See in Testing

If they dispatch:

```php
dispatch(new FailingJob());
```

Then run:

```bash
php dgz queue:work
```

They will see:

```
FailingJob running...
FailingJob running...
FailingJob running...
```

Then it disappears from active queue and appears in:

```
dgz_failed_jobs
```

---

## Queue Workers — Deep Production Explanation

Now we document how workers behave in real environments.

This is important.

### 1️⃣ Queue Workers Are Long-Running Processes

When you run:

```bash
php dgz queue:work
```

It does NOT exit.

It runs continuously.

It is meant to:

- Stay alive
- Process jobs as they arrive
- Keep looping forever

In production, this is usually managed by:

- Supervisor (Linux)
- Systemd
- Docker container
- PM2
- Kubernetes

### 2️⃣ Why It Does Not Drain Resources

Your worker does something important:

When no job is available:

```php
sleep(1);
```

(or similar)

This means:

- CPU usage drops to near zero
- It waits calmly
- It wakes up periodically to check again

It does NOT busy-loop aggressively.

That makes it safe for long-term use.

### 3️⃣ Real-Life Production Setup

In production, you typically:

- Set `queue_driver` to `'db'`
- Start worker in background
- Let it run forever

Example using Supervisor:

```ini
[program:dorguzen-worker]
command=php /path/to/project/dgz queue:work
autostart=true
autorestart=true
```

Now:

- If worker crashes → it restarts automatically
- If server reboots → worker starts again

### 4️⃣ Graceful Termination

To stop the worker manually:

Press:

```
CTRL + C
```

This sends a `SIGINT`. When the `pcntl` extension is available, the worker traps both `SIGTERM` and `SIGINT` and shuts down **gracefully**: instead of dying mid-job, it finishes the job currently in flight, then exits cleanly (`Queue worker stopped gracefully.`). This prevents a job being left half-processed.

In production:

```bash
supervisorctl stop dorguzen-worker
```

Or:

```bash
kill <process_id>
```

### 5️⃣ Multiple Workers

Because of optimistic locking:

```sql
WHERE reserved_at IS NULL
```

You can safely run:

```bash
php dgz queue:work
php dgz queue:work
php dgz queue:work
```

Multiple workers can run simultaneously.

They will not process the same job twice.

This allows horizontal scaling.

### 6️⃣ Why Workers Must Be Long Running

Spawning a new PHP process per job would:

- Be slow
- Be inefficient
- Increase memory churn

Instead:

Dorguzen keeps one process alive and:

- Loads framework once
- Reuses memory
- Loops efficiently

This is professional-grade architecture.

---

## Managing the Queue (CLI Commands)

Beyond `queue:work`, Dorguzen ships a set of CLI commands for inspecting and managing the queue and its failures:

```bash
php dgz queue:jobs           # list jobs currently in the queue
php dgz queue:stats          # show queue statistics (pending / failed counts)
php dgz queue:failed         # list all failed jobs
php dgz queue:retry <id>     # retry a single failed job by id
php dgz queue:retry all      # retry every failed job
php dgz queue:forget <id>    # permanently delete a failed job
php dgz queue:removejob <id> # remove a job from the active queue
php dgz queue:clear          # clear all pending jobs
```

`queue:retry` accepts either a specific failed-job `id` or the literal `all` to requeue every failed job at once. `queue:forget` permanently removes a single record from `dgz_failed_jobs`, while `queue:clear` empties the active `dgz_jobs` queue.

---

## Full Mental Model for Developers

When a developer writes:

```php
dispatch(new SendInvoiceJob($invoice));
```

They should understand:

- Job serialized
- Inserted into `dgz_jobs`
- Worker finds it
- Worker locks it
- Worker executes it
- On success → deleted
- On failure → retried
- On max failure → moved to `dgz_failed_jobs`

All automatically.

---

## Final Note for Documentation

You can confidently state:

Dorguzen provides a production-ready queue system with:

- Delayed jobs
- Retry logic
- Failure logging
- Concurrency safety
- Poison job protection
- Long-running workers
- Horizontal scalability

And the developer only needs to:

```php
dispatch(new MyJob());
```
