# Third-party Services

Dorguzen ships with two example module integrations to show you how to connect external services. Both follow the same pattern: credentials live exclusively in `.env`, the module controller reads them with `env()`, and the module can be toggled on or off via the `MODULES_*_STATUS` flags.

---

## Twilio SMS

Dorguzen ships with an SMS module that integrates with Twilio to send text messages. The module lives at `modules/sms/` and its controller at `modules/sms/controllers/SmsController.php`.

### Setup

1. Install the Twilio SDK:

   ```bash
   composer require twilio/sdk
   ```

2. Sign up or log in at https://www.twilio.com/
   Then go to https://console.twilio.com/ to find your credentials.

3. Add the following to your `.env` file:

   ```
   TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   TWILIO_FROM_NUMBER=+13656542054
   TWILIO_TO_NUMBER=+14378496403
   ```

   `TWILIO_SID` and `TWILIO_AUTH_TOKEN` are on the main console dashboard.
   Get them from: https://console.twilio.com/us1/account/keys-credentials/api-keys
   Remember to choose the right environment (test or live).

4. Enable the module in `.env`:

   ```
   MODULES_SMS_STATUS=on
   ```

### How it works

The `SmsController` reads all four values from `env()` and passes them to the Twilio SDK `Client`. The `notify()` method (endpoint: `/sms/notify`) is a working demo that sends a test SMS. In a real application, you would replace the hardcoded message body and recipient number with dynamic values from your application logic (e.g. user input, order data, etc.).

### Geographic permissions

For messages to be sent to a given country, you must enable that geographic region in the Twilio console under "SMS Geographic Permissions".

### Trial accounts

On a Twilio trial account you can only send messages to phone numbers you have explicitly verified in the "Verified Caller IDs" section of the console. Once you upgrade to a live account, this restriction is lifted and you can send to any number within your permitted geographic regions.

### From number

`TWILIO_FROM_NUMBER` must be a Twilio-purchased phone number from your console (https://console.twilio.com — Phone Numbers > Manage > Active Numbers).

---

## Stripe Payments

Dorguzen ships with a payments module that integrates with Stripe to handle online payments. The module lives at `modules/payments/` and its controller at `modules/payments/controllers/PaymentsController.php`.

### Setup

1. Install the Stripe PHP SDK:

   ```bash
   composer require stripe/stripe-php
   ```

2. Sign up or log in at https://stripe.com/
   Go to https://dashboard.stripe.com/apikeys to find your API keys.

3. Add the following to your `.env` file:

   ```
   STRIPE_SECRET_KEY=sk_test_<YOUR_TEST_SECRET_KEY>
   STRIPE_PUBLISHABLE_KEY=pk_test_<YOUR_TEST_PUBLISHABLE_KEY>
   ```

   Use the test keys locally. Swap to the live secret key in production.
   API calls must be made with the SECRET key — the publishable key alone is not sufficient.

4. Enable the module in `.env`:

   ```
   MODULES_PAYMENTS_STATUS=on
   ```

### How it works

The `PaymentsController` reads `STRIPE_SECRET_KEY` via `env()` and passes it to `\Stripe\Stripe::setApiKey()` in the constructor, so all subsequent Stripe API calls in that request are authenticated.

The module ships with two payment methods as examples:

- **`pay()`** — Stripe Checkout Session method (recommended for most use cases). Good for single or multiple products. Redirects the customer to Stripe's hosted checkout page and returns them to your success or cancel URL when done. See: https://docs.stripe.com/payments/checkout/how-checkout-works

- **`pay2()`** — Direct charge method using a Stripe token. Good for a single fixed amount. Expects a `stripe_token` in the POST body (generated client-side by Stripe.js). Note: this method requires a valid SSL certificate — it will not work on localhost unless you have SSL configured, because Stripe.js will not generate a valid token over plain HTTP.

### Confirming payments

After a payment, log in to the Stripe dashboard and go to:
Dashboard > Payments > switch to the relevant mode (Test / Live) to see all transactions.

### Success and cancel URLs

The `pay()` method builds its redirect URLs dynamically from the application's base URL (via `$this->config->getHomePage()`), so they automatically point to the right host in both local and production environments. You do not need to configure these separately.

### Price IDs

The example in `pay()` references a Price ID (e.g. `price_1OpXKWFRQteXl4yngb9PyxJj`). You can find or create Price IDs in the Stripe dashboard:
Dashboard > Product catalog > select a product > copy the API ID from the price row.

---

Return to [Introduction]({{base}}docs/introduction) or use the sidebar to navigate.
