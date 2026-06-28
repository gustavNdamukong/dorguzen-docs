# Events

This tells you how your programming language helps you respond to events. This brings to mind design patterns like the Subscriber pattern (pub-sub), the dispatching of jobs based on the triggering of certain events and the consumption of such, within your application.

Note here that the Command topic often has a great role to play here.

---

## Events handling in Dorguzen

### Event vs Job — The Conceptual Difference

This is the most important distinction.

🔹 **What is an Event?**

An Event represents something that has happened in your application.

Examples:

- `UserRegistered`
- `OrderPlaced`
- `PasswordResetRequested`

An event answers the question: "What just happened?"
It does not describe work to be done. It simply describes an occurrence.

🔹 **What is a Job?**

A Job represents work that needs to be processed.

For example:

- Send an email
- Resize an image
- Generate a report
- Process a payment

A job answers the question: "What needs to be executed?"

---

### When Does an Event Become a Job?

An event never becomes a job by itself. Instead, an event is dispatched.
Its listeners are resolved, and if a listener implements `ShouldQueue`, that listener is wrapped into a `QueuedJob`.

The `QueuedJob` is then pushed into a queue. So the flow of the transformation goes like this:

```
Event
  ↓
Listener
  ↓ (if implements ShouldQueue)
QueuedJob
```

Only the listener, not the event itself, becomes a queued job.
That distinction is very important.

---

## Creating a Custom Event (ProductPurchased Example)

Let's build a completely custom event from scratch. We will use `ProductPurchased` as the example — a domain event that fires whenever a user completes a purchase.

(Note: Dorguzen also ships with core events such as `UserRegistered`, `UserLoggedIn`, etc. that fire automatically. This section shows how to create your own on top of those.)

You can scaffold the files with the CLI:

```bash
php dgz make:event  ProductPurchased
php dgz make:listener NotifyAdminOfPurchase
php dgz make:listener SendPurchaseReceiptEmail
```

Then fill them in:

### Step 1 — Create the Event

File: `src/events/ProductPurchased.php`

```php
namespace Dorguzen\Events;

class ProductPurchased
{
    public function __construct(
        public int    $userId,
        public string $userEmail,
        public int    $productId,
        public string $productName,
        public float  $amount,
    ) {}
}
```

Simple. It is just a data container — no methods, just the facts about what happened.

### Step 2 — Create Listeners

**Listener A — synchronous** (runs immediately, before the response is sent)

File: `src/listeners/NotifyAdminOfPurchase.php`

```php
namespace Dorguzen\Listeners;

use Dorguzen\Events\ProductPurchased;
use Dorguzen\Core\DGZ_Logger;

class NotifyAdminOfPurchase
{
    public function handle(ProductPurchased $event): void
    {
        DGZ_Logger::info('Purchase completed', [
            'user_id'      => $event->userId,
            'product_id'   => $event->productId,
            'product_name' => $event->productName,
            'amount'       => $event->amount,
        ]);
        // could also send a Slack notification here via DGZ_Slack::send()
    }
}
```

This runs immediately and completes before the HTTP response is returned to the user.

**Listener B — queued** (runs in the background via the queue worker)

File: `src/listeners/SendPurchaseReceiptEmail.php`

```php
namespace Dorguzen\Listeners;

use Dorguzen\Core\Events\ShouldQueue;
use Dorguzen\Events\ProductPurchased;
use Dorguzen\Core\DGZ_Messenger;

class SendPurchaseReceiptEmail implements ShouldQueue
{
    public function handle(ProductPurchased $event): void
    {
        $messenger = new DGZ_Messenger();
        $messenger->sendEmail(
            $event->userEmail,
            "Your purchase of {$event->productName}",
            "Thank you! Your payment of {$event->amount} was received."
        );
    }
}
```

Because it implements `ShouldQueue`, this listener is not called inline. Instead, `EventDispatcher` hands it to `QueueManager`, which stores the job and returns immediately. The queue worker processes it in the background.

### Step 3 — Register in `configs/events.php`

```php
return [
    \Dorguzen\Events\ProductPurchased::class => [
        \Dorguzen\Listeners\NotifyAdminOfPurchase::class,    // runs now
        \Dorguzen\Listeners\SendPurchaseReceiptEmail::class,  // runs in queue
    ],
];
```

You can add as many listeners as you like. They fire in the order listed. Synchronous listeners run first, then queued ones are handed off.

### Step 4 — Dispatch the Event

Inside your controller, after a successful purchase:

```php
event(new ProductPurchased(
    userId:      (int) $user->users_id,
    userEmail:   $user->users_email,
    productId:   (int) $product['product_id'],
    productName: $product['product_name'],
    amount:      (float) $order['total_amount'],
));
```

---

## What Happens Internally

Let's trace the exact flow.

### Step A — `event()` Helper

The helper:

```php
use Dorguzen\Core\Events\EventService;

/**
 * It is recommended to use event() to dispatch Events, as Events
 * are meant to pass through the EventService class pipeline.
 *
 * Events are not candidates for the queueing system, though an Event may be
 * marked for queueing by making its listener implement the ShouldQueue interface
 * (core/events/ShouldQueue.php), in which case, event() will detect that internally
 * and hand the Event over to the queue system to be dispatched as a job.
 *
 * Example usage:
 *  event(new TestEvent('hello'));
 */
if (!function_exists('event')) {
    function event(object $event): void
    {
        container(EventService::class)->dispatch($event);
    }
}
```

It sends the event into the system.

### Step B — EventDispatcher

The `EventService` (in `core/events/EventService.php`) class sends the event into the system by passing the raised event to the `dispatch()` method of the `EventDispatcher` class.
This `EventDispatcher->dispatch()` resolves all registered listeners of that event from config, loops through them and checks

if a `$listener` is an `instanceof ShouldQueue`, and places it in a queue if it does, otherwise it runs the listener immediately by calling its `handle()` method which all listener classes must have.

### Step C — Immediate Listener

```php
$listener->handle($event);
```

### Step D — Queued listener

If the `$listener` is an instance (implements) `ShouldQueue`, then the listener will be placed in a queue instead of being run:

```php
$job = new QueuedJob($listenerClass, $event);
$queueManager->push($job);
```

Now we are officially in Job territory.

The event has triggered a job.

Once more, in summary; `EventService` is the Dorguzen entry point for events. The flow goes like this:

- `EventService` dispatches a service to `EventDispatcher`,
- `EventDispatcher` uses `ListenerResolver` to resolve listeners from the configuration (the central glue),
- `EventDispatcher`, after resolving a listener class, checks if the listener implements `ShouldQueue`.
  If the listener does not implement `ShouldQueue`, it runs that listener directly by calling the `handle()` method of the listener to handle the event.
  If the listener implements `ShouldQueue`, then it knows that the listener needs to become a queued job, so it packages the event listener into a `QueuedListener`. See this as a way of standardising the format of the listener as a queued object, for easy consumption later. It then passes this `QueuedListener` to the `push()` method of `QueueManager`, which uses the currently active queue type (based on the `queue_driver` setting) to queue the event.
  This is how an event reaches the queue system, and the `EventDispatcher` class is the bridge. So, again, only events whose listeners implement `ShouldQueue` will hit the queue system. Jobs, naturally, unlike events, only use the queue system.

---

## Built-in Dorguzen Core Events

### Event classes as data containers — how hydration works

If you open any event class (e.g. `src/events/UserRegistered.php`) you will notice it has no methods. It is a plain PHP class whose only job is to hold data. Think of it as a sealed envelope — it carries information about what just happened, and every listener that receives it can read that information.

The envelope is filled (hydrated) at the exact moment the event is dispatched. In Dorguzen, that happens inside a controller like this:

```php
$activationUrl = $this->config->getHomePage() . 'auth/verifyEmail?em=' . $activationCode;
event(new UserRegistered((int) $saved, $username, $email, $firstname, $activationCode, $activationUrl));
```

Breaking that line down:

- `new UserRegistered(...)` — Creates the event object and passes real values into its constructor. PHP's constructor property promotion assigns them directly to the public properties (`$userId`, `$username`, `$email`, `$firstname`, `$activationCode`, `$activationUrl`). This is the hydration step — the object is now fully populated.

- `event(...)` — Passes the hydrated object into the `EventService` pipeline. `EventService` resolves all listeners registered for this event in `configs/events.php` and calls `handle()` on each one, passing the same event object in.

By the time `handle()` runs in any listener, the event is already loaded:

```php
public function handle(UserRegistered $event): void
{
    // $event->userId, $event->username, $event->email,
    // $event->firstname are all available and populated.
    DGZ_Logger::info('User registered', [
        'user_id'  => $event->userId,
        'username' => $event->username,
    ]);
}
```

The full flow from dispatch to listener:

```
new UserRegistered($id, $username, $email, $firstname)
        ↓  hydrated here — constructor properties set
    event()  helper
        ↓
    EventService → EventDispatcher
        ↓  resolves listeners from configs/events.php
    LogUserRegistration::handle($event)   ← same fully-loaded object
    SendWelcomeEmail::handle($event)       ← same fully-loaded object
```

Every listener in the chain receives the same object. None of them need to do a database lookup to find the user's name or email — it is already sitting in the event properties, placed there at dispatch time.

This is why event constructors are deliberate about what data they carry: too little and listeners have to query the database themselves (slow, couples listeners to models); too much and the event becomes a dumping ground. The goal is to carry exactly the data that listeners are most likely to need.

---

### Why built-in events matter

A framework that ships with no events forces every developer to wire up the same lifecycle hooks from scratch on every project. Dorguzen ships with a set of core events that fire automatically at the most common and important moments in any web application — user registration, login, logout, newsletter subscription, and contact form submission.

This means that from day one, a Dorguzen developer can react to any of these moments simply by registering a listener in `configs/events.php`. No changes to controllers, no boilerplate — just add the listener and it runs automatically.

---

### The five core events

All core events live in `src/events/` and are dispatched automatically by the framework. All carry only the data that is directly relevant to the occurrence — no database lookups needed inside listeners.

#### UserRegistered

`src/events/UserRegistered.php`

Fired after a new user account is successfully created, whether via the web registration form (`AuthController::doRegis()`) or the API (`AuthApiController::register()`).

Payload:

```
$event->userId          int     The new user's database ID
$event->username        string  Their chosen username
$event->email           string  Their email address
$event->firstname       string  Their first name
$event->activationCode  string  The MD5 hash stored in users_eactivationcode
                                (empty string if your app does not use email
                                verification)
$event->activationUrl   string  The full verification URL, e.g.
                                https://yourapp.com/auth/verifyEmail?em=<code>
                                Pre-built by the controller so listeners do not
                                need to know how URLs are constructed.
                                (empty string if your app does not use email
                                verification)
```

The controller builds `$activationUrl` before dispatching:

```php
$activationUrl = $this->config->getHomePage() . 'auth/verifyEmail?em=' . $activationCode;
event(new UserRegistered((int) $saved, $username, $email, $firstname, $activationCode, $activationUrl));
```

The built-in `SendWelcomeEmail` listener reads `$event->activationUrl` and sends the activation email. This keeps the email-sending logic out of the controller entirely — `doRegis()` fires the event and moves on.

Example uses: send an account activation email (→ `SendWelcomeEmail`), log the registration (→ `LogUserRegistration`), notify a CRM.

#### UserLoggedIn

`src/events/UserLoggedIn.php`

Fired after a user successfully authenticates, whether via the web login form (`AuthController::doLogin()`) or the API (`AuthApiController::login()`).

Payload:

```
$event->userId      int     The user's database ID
$event->username    string  Their username
$event->userType    string  Their account type (member, admin, etc.)
```

Example uses: log login activity, detect logins from new locations, update a "last_login" timestamp.

#### UserLoggedOut

`src/events/UserLoggedOut.php`

Fired just before the user's session is destroyed in `AuthController::logout()`. It is dispatched before `session_destroy()` so that the user's identity is still available to listeners.

Payload:

```
$event->userId      int     The user's database ID
$event->username    string  Their username
```

Example uses: log the logout for an audit trail, record session duration, invalidate server-side caches tied to that user.

#### UserSubscribed

`src/events/UserSubscribed.php`

Fired after a visitor successfully subscribes to the newsletter via `FeedbackController::subscribe()`.

Payload:

```
$event->name        string  The subscriber's name
$event->email       string  Their email address
```

Example uses: send a subscription confirmation email, sync to a mailing list provider (Mailchimp, etc.), log the subscription for analytics.

Note on the shipped `SendSubscriptionConfirmation` listener:
The listener that ships with this event has an empty `handle()` body by design. Whether to send an instant confirmation email on sign-up is an application-level decision — some apps do; others defer it (e.g. batch-send via a cron job after admin review). If your app wants instant confirmation, add the sending logic directly inside `SendSubscriptionConfirmation::handle()`.

#### ContactFormSubmitted

`src/events/ContactFormSubmitted.php`

Fired after a visitor successfully submits the contact form via `FeedbackController::processContact()`.

Note: the admin notification email is already sent synchronously by the controller. This event is for any additional reactions on top of that — the controller handles the admin side, the event handles everything else.

Payload:

```
$event->name        string  The visitor's name
$event->email       string  Their email address
$event->phone       string  Their phone number (may be empty)
$event->message     string  The message body
```

Example uses: send a receipt/confirmation email to the visitor, log the submission to an analytics or CRM system, trigger a follow-up workflow.

---

### Built-in listeners shipped with each event

Each core event ships with one or more pre-wired listeners in `src/listeners/`. These work out of the box and serve as concrete examples to build on:

| Event | Listener(s) |
|---|---|
| UserRegistered | SendWelcomeEmail, LogUserRegistration |
| UserLoggedIn | LogUserLogin |
| UserLoggedOut | LogUserLogout |
| UserSubscribed | SendSubscriptionConfirmation |
| ContactFormSubmitted | SendContactConfirmation |

All mappings live in `configs/events.php`. That file is the single source of truth for which listeners respond to which events.

---

### Adding your own listener to a core event

To react to a core event, you do not touch any controller. You only need two things:

**Step 1 — Create your listener in `src/listeners/`**

```php
// src/listeners/SyncNewUserToCrm.php
namespace Dorguzen\Listeners;

use Dorguzen\Events\UserRegistered;

class SyncNewUserToCrm
{
    public function handle(UserRegistered $event): void
    {
        // push $event->email and $event->username to your CRM API
    }
}
```

**Step 2 — Register it in `configs/events.php`**

```php
\Dorguzen\Events\UserRegistered::class => [
    \Dorguzen\Listeners\SendWelcomeEmail::class,
    \Dorguzen\Listeners\LogUserRegistration::class,
    \Dorguzen\Listeners\SyncNewUserToCrm::class,   // ← add here
],
```

That's it. The next time a user registers, all three listeners fire in the order listed.

---

### Logging inside listeners — use DGZ_Logger

When a listener needs to write to the log, always use `DGZ_Logger` rather than accessing the `Logs` model directly. `DGZ_Logger` is the correct tool because it:

- Respects the `APP_LOG_DRIVER` setting in `.env` (file, db, or both)
- Applies the correct log format (`APP_LOG_FORMAT`)
- Supports named channels for routing specific events to dedicated logs

Basic usage (logs to whatever driver `.env` specifies):

```php
use Dorguzen\Core\DGZ_Logger;

DGZ_Logger::info('User registered', [
    'user_id'  => $event->userId,
    'username' => $event->username,
]);
```

Routing to a dedicated channel (e.g. a separate auth log):

```php
DGZ_Logger::channel('auth')->info('User logged in', [
    'user_id'  => $event->userId,
    'username' => $event->username,
]);
```

Available severity levels: `debug`, `info`, `warning`, `error`.

Using the `Logs` model directly (`container(Logs::class)->log(...)`) bypasses all of this and always writes to the database regardless of your `.env` settings — avoid it inside listeners.

---

### Making a listener asynchronous (ShouldQueue)

By default all listeners run synchronously — they complete before the HTTP response is sent. For slow operations like sending emails or calling external APIs, implement `ShouldQueue` to push the listener onto the queue instead:

```php
use Dorguzen\Core\Events\ShouldQueue;

class SendWelcomeEmail implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        // this now runs in the background via the queue worker
    }
}
```

The queue worker must be running for queued listeners to execute:

```bash
php dgz queue:work
```

See the Queues & Jobs section of these docs for full details on queue drivers, workers, and monitoring.
