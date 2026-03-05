# AI Health Laravel SDK Setup Guide

Welcome to the **AI Health Laravel SDK**. This SDK integrates seamlessly into your Laravel application to provide powerful error detection, background health analysis, Web/Real User Monitoring (RUM), and Conversion/CTA tracking. 

This guide covers the all-in-one setup.

---

## 1. Installation

Install the SDK via Composer (make sure your repositories list in `composer.json` points to the SDK if it's private):

```bash
composer require aihealth/laravel-sdk
```

---

## 2. Configuration & Initialization

Publish the configuration file:

```bash
php artisan vendor:publish --tag=aihealth-config
```

In your project's `.env` file, add your DSN (Data Source Name) provided by the Health Monitor dashboard. 

```env
# Backend API Key (DSN) - Contains your Public Key, Host URL, and Project ID
# Format: http://{public_key}@{host}/{project_id}
AIHEALTH_DSN="http://your_public_key@127.0.0.1:8000/1"

# Frontend Private Tracking Key (Required for RUM & CTA Analytics)
AIHEALTH_PRIVATE_TRACKING_KEY="rum_your_private_key_here"

# Enable/Disable modules
AIHEALTH_SEND_EXCEPTIONS=true
AIHEALTH_SEND_LOGS=true
AIHEALTH_SEND_TRANSACTIONS=true
```

---

## 3. Module 1: Error & Log Detection

By simply setting the `AIHEALTH_DSN`, the SDK auto-discovers and intercepts exceptions and log messages (using Laravel's built-in handlers). You don't need to change your `bootstrap/app.php` or `Handler.php`! 

The `AIHealthServiceProvider` automatically binds the `ErrorHandler` and `LogHandler` to Laravel's logging channels.

*Tip: To test if errors are being sent, you can trigger a manual exception in a route:*
```php
Route::get('/debug-sentry', function () {
    throw new Exception('My first SDK error!');
});
```

---

## 4. Module 2: Background Health Analysis

The Health Analysis module monitors CPU load, Memory limits, and Database connectivity. 

The SDK automatically registers a background heartbeat that runs every five minutes. The only requirement is that your server must have Laravel's base CRON scheduler running.

Make sure your server is running the standard Laravel cron job (normally set up once per server):
`* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1`

---

## 5. Module 3: Web Analysis (RUM) & CTA Tracking

Real User Monitoring tracks page load speeds, core web vitals, user sessions, and interactions (such as Call-To-Action clicks).

To enable this, add the following Blade directive to your main layout file (`resources/views/layouts/app.blade.php`), right before the closing `</head>` tag:

```blade
    <!-- AIHealth RUM & Interaction Tracker -->
    @aihealth
</head>
```

*(Note: Ensure `AIHEALTH_PRIVATE_TRACKING_KEY` and `AIHEALTH_RUM_ENDPOINT` are correctly filled in your `config/aihealth.php` or `.env` file, though the SDK auto-fetches them from the monitor if configured correctly via DSN).*

### Automatic CTA Tracking

The RUM script automatically finds clickable elements and tracks user flows. To explicitly tag critical buttons (like "Checkout" or "Sign Up") for enhanced conversion tracking, add the `data-aihealth-cta` attribute to your HTML elements:

```html
<button data-aihealth-cta="primary-checkout-btn" class="btn-checkout">
    Complete Purchase
</button>

<a href="/pricing" data-aihealth-cta="view-pricing-link">
    View Pricing
</a>
```

## 6. Module 4: Route Synchronization (Web Crawler)

To enable the Health Monitor to automatically crawl your pages and discover conversion/CTA flows, you need to synchronize your application's routes to the dashboard. 

Run the following command once, or whenever you add new frontend-facing routes:

```bash
php artisan aihealth:sync-routes
```

## Summary

With these simple steps, your Laravel application is fully instrumented:
1. **Composer Require & .env API Keys** (Activates the SDK, Error Tracking, Logs, Transactions, and Background Health Monitor)
2. **`@aihealth` Blade Directive** (Activates Web/RUM analysis and Frontend CTA interactions)
3. **Command Execution** (`aihealth:sync-routes` to initialize Web Analysis crawling)
