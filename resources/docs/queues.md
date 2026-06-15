# Queue System

The Dorguzen queue system lets you push work off the web request into the background. The active driver is controlled by a single `.env` key.

---

## Drivers

| Driver | Storage | When jobs run | Use case |
|---|---|---|---|
| `sync` | None | Immediately, same request | Local development |
| `db` | `dgz_jobs` table | Background, via worker daemon | Production |

Set the driver in `.env`:

```ini
QUEUE_DRIVER=sync
```

---

## Creating a Job

```bash
php dgz make:job SendInvoice
```

Generates `src/Jobs/SendInvoiceJob.php`. Add your logic inside `handle()`:

```php
namespace Dorguzen\Jobs;

class SendInvoiceJob
{
    public function __construct(
        public int    $userId,
        public string $invoiceRef,
    ) {}

    public function handle(): void
    {
        // render PDF and email it
    }
}
```

Jobs are plain PHP classes. The only requirement is a public `handle()` method.

---

## Dispatching a Job

```php
// Global helper
dispatch(new SendInvoiceJob(userId: $user->users_id, invoiceRef: $ref));

// With a delay (seconds)
dispatch(new SendInvoiceJob(...), delaySeconds: 300);
```

---

## Running the Worker

```bash
php dgz queue:work
```

The worker is a long-running daemon that polls `dgz_jobs` and executes pending jobs.

| Option | Description |
|---|---|
| `--once` | Process one job then exit |
| `--sleep=N` | Seconds to sleep when queue is empty (default 1) |
| `--max-jobs=N` | Exit after processing N jobs |
| `--timeout=N` | Max execution time per job in seconds (default 60) |

The worker listens for `SIGTERM` / `SIGINT` and finishes the current job before stopping.

### Supervisor (production)

```ini
[program:dgz-worker]
command=php dgz queue:work
directory=/path/to/your/app
autostart=true
autorestart=true
```

---

## Database Queue Internals

When `QUEUE_DRIVER=db`, jobs are serialized into `dgz_jobs`. The worker uses optimistic locking to prevent two workers processing the same job:

1. Select next available row (`reserved_at IS NULL` and `available_at <= NOW()`).
2. Update `reserved_at = NOW()` — if another worker grabbed it first (0 rows affected), skip.
3. On success: delete the row.
4. On failure: re-release (if `attempts < max_attempts`) or move to `dgz_failed_jobs`.

Default `max_attempts` is 3. Each retry is delayed 5 seconds.

---

## Managing the Queue

```bash
php dgz queue:jobs           # list pending jobs
php dgz queue:stats          # pending and failed job counts
php dgz queue:failed         # list failed jobs
php dgz queue:retry <id>     # retry a failed job
php dgz queue:retry all      # retry all failed jobs
php dgz queue:forget <id>    # permanently delete a failed job
php dgz queue:clear          # clear all pending jobs
```

---

## Database Tables

**`dgz_jobs`** — active/pending jobs

| Column | Type | Description |
|---|---|---|
| `id` | int | Auto-increment PK |
| `queue` | varchar | Queue name (default `'default'`) |
| `payload` | longtext | Serialized job object |
| `attempts` | int | How many times attempted |
| `max_attempts` | int | Max before marking failed |
| `reserved_at` | datetime nullable | Set when worker claims the job |
| `available_at` | datetime | Earliest processing time |
| `created_at` | datetime | When job was pushed |

**`dgz_failed_jobs`** — permanently failed jobs

| Column | Type | Description |
|---|---|---|
| `id` | int | Auto-increment PK |
| `payload` | longtext | Serialized job |
| `exception` | text | Error message |
| `exception_trace` | text | Stack trace |
| `failed_at` | datetime | When it permanently failed |
