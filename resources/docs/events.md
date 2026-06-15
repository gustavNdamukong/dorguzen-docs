# Events and Listeners

The Dorguzen events system decouples application behaviour. When something happens, you fire an event. Listeners react to it — synchronously or in the background via the queue system.

---

## Core Concepts

- **Event** — a plain PHP data container (DTO) that describes what happened. Lives in `src/events/`.
- **Listener** — a class with a `handle()` method that reacts to an event. Lives in `src/listeners/`.
- **Queued listener** — implements `ShouldQueue`. Pushed onto the queue, processed by the worker in the background.
- **Synchronous listener** — runs immediately, inline with the request.

---

## Built-in Events

Pre-configured in `configs/events.php`:

| Event | Fired when |
|---|---|
| `UserRegistered` | A new user successfully registers |
| `UserLoggedIn` | A user logs in |
| `UserLoggedOut` | A user logs out |
| `UserSubscribed` | A user subscribes to the newsletter |
| `ContactFormSubmitted` | A contact form is submitted |

---

## Creating a Custom Event

### Step 1 — Generate scaffolding

```bash
php dgz make:event ProductPurchased
php dgz make:listener NotifyAdminOfPurchase
php dgz make:listener SendPurchaseReceiptEmail
```

### Step 2 — Define the event class

Events are pure data containers — no methods beyond the constructor:

```php
namespace Dorguzen\Events;

class ProductPurchasedEvent
{
    public function __construct(
        public int    $userId,
        public string $userEmail,
        public int    $productId,
        public float  $amount,
    ) {}
}
```

### Step 3 — Write a synchronous listener

```php
namespace Dorguzen\Listeners;

use Dorguzen\Events\ProductPurchasedEvent;

class NotifyAdminOfPurchase
{
    public function handle(ProductPurchasedEvent $event): void
    {
        // runs immediately in the request
    }
}
```

### Step 4 — Write a queued listener

```php
namespace Dorguzen\Listeners;

use Dorguzen\Core\Events\ShouldQueue;
use Dorguzen\Events\ProductPurchasedEvent;

class SendPurchaseReceiptEmail implements ShouldQueue
{
    public function handle(ProductPurchasedEvent $event): void
    {
        // runs in background via queue worker
    }
}
```

### Step 5 — Register in `configs/events.php`

```php
return [
    \Dorguzen\Events\ProductPurchasedEvent::class => [
        \Dorguzen\Listeners\NotifyAdminOfPurchase::class,     // synchronous
        \Dorguzen\Listeners\SendPurchaseReceiptEmail::class,  // queued
    ],

    // ... existing built-in events
];
```

### Step 6 — Fire the event

```php
event(new ProductPurchasedEvent(
    userId:    (int)   $user->users_id,
    userEmail: (string) $user->users_email,
    productId: (int)   $product['product_id'],
    amount:    (float) $order['total_amount'],
));
```

---

## Dispatch Flow

```
event(new ProductPurchasedEvent(...))
    ↓
EventService → EventDispatcher → ListenerResolver
    ↓
For each listener:
  NOT ShouldQueue → listener->handle($event) called immediately
  ShouldQueue     → wrapped in QueuedListener → QueueManager::push()
                        ↓
                        QUEUE_DRIVER=sync → handle() runs now (same process)
                        QUEUE_DRIVER=db   → stored in dgz_jobs → worker picks up later
```

---

## File Locations

| What | Where |
|---|---|
| Event classes | `src/events/` |
| Listener classes | `src/listeners/` |
| Event-to-listener map | `configs/events.php` |
| `ShouldQueue` interface | `core/events/ShouldQueue.php` |
| `EventDispatcher` | `core/events/EventDispatcher.php` |

---

## Notes

- Implementing `ShouldQueue` does not hard-wire async. With `QUEUE_DRIVER=sync`, queued listeners still run immediately in-process.
- Listeners run in the order they are listed in `configs/events.php`.
- Do not put controller-layer concerns (`DGZ_Notifier`, HTML strings) inside listener classes.
