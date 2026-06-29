# Slack Notifications

Dorguzen has built-in support for sending Slack messages via Incoming Webhooks — no Slack SDK or third-party package required. All communication is a single HTTPS POST request handled by the framework class `DGZ_Slack`.

---

## What are Slack Incoming Webhooks?

An Incoming Webhook is a URL that Slack generates for your workspace. Any application that can make an HTTP POST request can post a message to that URL and it will appear instantly in a Slack channel. This is the official, simplest way for external systems to send messages to Slack — used by everything from GitHub and Jira to custom enterprise tooling.

You do not need to create a Slack bot, manage OAuth tokens, or install anything on the server beyond what Dorguzen already provides.

---

## 1. Create a Slack Incoming Webhook

1. Go to https://api.slack.com/apps and sign in.
2. Click "Create New App" → "From scratch". Give it a name (e.g. "MyApp Alerts") and select your workspace.
3. In the left sidebar click "Incoming Webhooks" and toggle it ON.
4. Click "Add New Webhook to Workspace", choose the channel you want messages to go to, and click Allow.
5. Copy the Webhook URL — it looks like:

```
https://hooks.slack.com/services/T.../B.../xxxxxxxxxxxx
```

---

## 2. Configuration

Add these keys to your `.env`:

```
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../xxx
SLACK_DEFAULT_CHANNEL=#general    # optional — overrides webhook default
SLACK_USERNAME=MyApp              # optional — bot display name
SLACK_ICON_EMOJI=:bell:           # optional — icon shown next to name
```

Only `SLACK_WEBHOOK_URL` is required. The others are optional refinements.

Channel priority (highest to lowest):

1. Channel passed directly to `DGZ_Slack::send()` as second argument
2. `SLACK_DEFAULT_CHANNEL` in `.env`
3. The channel configured on the webhook itself in the Slack UI

---

## 3. Sending a message

Import and call the static method from anywhere in your application:

```php
use Dorguzen\Core\DGZ_Slack;

// Simplest possible call — posts to your configured default channel
DGZ_Slack::send('New user registered: john_doe');

// Override the channel for this specific message
DGZ_Slack::send('Payment failed for order #1234', '#payments');

// Rich message with an attachment (colour-coded sidebar)
DGZ_Slack::send('Deploy complete', '#deployments', [
    'attachments' => [[
        'color' => 'good',          // good=green, warning=yellow, danger=red
        'title' => 'Version 2.1.0',
        'text'  => 'Deployed to production at 14:32 UTC.',
    ]]
]);
```

The third argument `$extra` is merged directly into the Slack payload, so any field the Slack API supports (blocks, thread_ts, mrkdwn, etc.) can be passed here.

`DGZ_Slack::send()` returns `true` on success and `false` on failure. Failures are automatically logged via `DGZ_Logger` so you never lose the error silently.

If `SLACK_WEBHOOK_URL` is not set, the call returns `false` immediately and logs a warning — it will not throw an exception or crash your application.

---

## 4. Three ways to trigger a Slack notification

You have three clean options depending on your use case:

---

### Option A: Call DGZ_Slack::send() directly

Use this for one-off notifications where you have the context right there in a controller or service method:

```php
// In a controller or service
DGZ_Slack::send("New gold member upgrade: {$username}");
```

This is synchronous — the Slack API call happens inline before the web response is returned. Fine for admin actions; for high-traffic user-facing routes, prefer Option B or C.

---

### Option B: Dispatch a queued Job

Use this when you want the Slack call to be non-blocking — the web request returns instantly and a background worker sends the message:

```php
// src/jobs/NotifySlack.php
class NotifySlack
{
    public function __construct(
        public string  $message,
        public ?string $channel = null,
    ) {}

    public function handle(): void
    {
        DGZ_Slack::send($this->message, $this->channel);
    }
}

// Dispatched from anywhere — fire and forget
dispatch(new NotifySlack('Order #1234 placed', '#orders'));
```

With `QUEUE_DRIVER=db` or `rabbitmq` the message is queued instantly and sent by a worker in the background. The user never waits for the Slack API.

---

### Option C: A Listener on an existing Event

Use this when a Slack notification is a natural reaction to something that already fires an event. No new dispatch call needed anywhere — the event already fires and the listener reacts:

```php
// src/listeners/NotifySlackOnRegistration.php
class NotifySlackOnRegistration
{
    public function handle(UserRegistered $event): void
    {
        DGZ_Slack::send(
            "New user registered: {$event->username} ({$event->email})",
            '#signups'
        );
    }
}

// configs/events.php — just add the listener to the existing array
UserRegistered::class => [
    SendWelcomeEmail::class,
    LogUserRegistration::class,
    NotifySlackOnRegistration::class,   // ← add this line
],
```

The controller dispatching the `UserRegistered` event does not change at all. It does not know Slack exists. This is the cleanest, most decoupled approach.

To make it non-blocking, implement `ShouldQueue` on the listener:

```php
class NotifySlackOnRegistration implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        DGZ_Slack::send("New user: {$event->username}", '#signups');
    }
}
```

---

## 5. Common use cases

Slack notifications are most valuable for internal operational awareness — things your team needs to know about in real time:

| Use case | Channel | Example message |
| --- | --- | --- |
| New user registration | #signups | "New user: john_doe (john@...)" |
| Payment received | #payments | "Order #1234 — $99.99 received" |
| Payment failed | #payments | "⚠ Payment failed: order #1234" |
| New contact form submission | #support | "Contact from Jane Smith" |
| Error alert (500 / exception) | #alerts | "Fatal error in ProductController" |
| Background job failure | #alerts | "Job SendInvoiceJob failed (3/3)" |
| Gold/premium membership upgrade | #upgrades | "john_doe upgraded to Gold" |
| New shop created | #shops | "New shop: CamStyle opened" |
| Scheduled task ran / failed | #ops | "Backup job completed at 03:00" |
| Deployment complete | #deployments | "v2.1.0 deployed to production" |

The pattern is the same for all of them: one listener, one `DGZ_Slack::send()` call.

---

## 6. Error handling and resilience

`DGZ_Slack` is designed to be a silent helper — it will never crash your application if Slack is unreachable:

- If `SLACK_WEBHOOK_URL` is missing → logs a warning, returns `false`.
- If the Slack API returns a non-200 response → logs the error with full context (HTTP code, response body), returns `false`.
- If the cURL request times out (5 second limit) → logged, returns `false`.

If you want to react to a failure, check the return value:

```php
if (! DGZ_Slack::send($message, '#alerts')) {
    // Slack is down — fall back to email or just log it
    DGZ_Logger::error('Slack notification failed', ['message' => $message]);
}
```

---

## 7. Advanced payloads — Slack Block Kit

Slack supports rich, interactive message layouts via Block Kit. Pass the `blocks` key in the `$extra` argument to use them:

```php
DGZ_Slack::send('', '#alerts', [
    'blocks' => [
        [
            'type' => 'header',
            'text' => ['type' => 'plain_text', 'text' => 'New Order Received'],
        ],
        [
            'type'   => 'section',
            'fields' => [
                ['type' => 'mrkdwn', 'text' => '*Order:* #1234'],
                ['type' => 'mrkdwn', 'text' => '*Amount:* $99.99'],
                ['type' => 'mrkdwn', 'text' => '*Customer:* john_doe'],
            ],
        ],
    ],
]);
```

The `text` parameter becomes the fallback for notifications that cannot render blocks (e.g. mobile push previews). Pass an empty string if you are using blocks exclusively.

Use the Slack Block Kit Builder at https://app.slack.com/block-kit-builder to design and preview block layouts visually before coding them.

---

Return to [Introduction]({{base}}docs/introduction) or use the sidebar to navigate.
