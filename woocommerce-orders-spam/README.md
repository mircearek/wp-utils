# Woo Security

Stops WooCommerce card-testing (failed orders) and comment/review spam **before** WooCommerce creates an order.

A fingerprint rate limiter is not enough on its own. Card-testing bots rotate fingerprints and POST straight to `wc-ajax=checkout`. Most Turnstile plugins only show a widget on the page, so those requests never solve a captcha.

## Install

1. Upload `anti-card-testing-for-woocommerce.zip` via **Plugins → Add New → Upload Plugin**, or copy this folder into `wp-content/plugins/`.
2. Activate **Woo Security**.
3. Open **WooCommerce → Woo Security**. All options and copy/paste Cloudflare rules are on that page.

If you still have the original snippet in `functions.php` / Code Snippets, remove it.

## What to turn on

Keep these on (they are the default):

- Fingerprint rate limiter (3 attempts / 2 minutes)
- Honeypot
- Require JavaScript
- Minimum checkout time (3 seconds)
- Protect comments and product reviews

Then add Turnstile keys from [Cloudflare Turnstile](https://dash.cloudflare.com/turnstile) and enable:

- Protect classic checkout
- Protect comments and reviews

**Disable checkout and comment protection in your other Turnstile plugin.** Two widgets on one form will break verification because Turnstile tokens are one-time use.

If you do not need comments or product reviews, enable **Close all comments and reviews**.

During a live flood, enable **Emergency global limit**.

## Why the other Turnstile plugin failed

Classic WooCommerce checkout replaces `#order_review` on every `updated_checkout` AJAX call. That destroys the captcha widget. Bots also skip the page and POST to:

- `/?wc-ajax=checkout`
- `/wp-comments-post.php`
- XML-RPC `wp.newComment`

This plugin re-renders Turnstile after `updated_checkout`, puts the token inside `form.checkout`, and **rejects the request on the server** if the token is missing or invalid.

## Also do this on the store

These are outside the plugin and matter as much as the code:

1. **Payment gateway:** require 3-D Secure / SCA for all card payments (Stripe Radar, “3D Secure is recommended or required”). Card testing dies when the bank has to approve.
2. **Cloudflare:** Bot Fight Mode, and a WAF rule on `/checkout` and `wc-ajax=checkout` if the flood continues.
3. **Do not cache the checkout page.**
4. Prefer classic checkout (`[woocommerce_checkout]`). Checkout Blocks talk to the Store API; this plugin rate-limits that endpoint, but Turnstile on Blocks is not included.
5. Trash existing failed spam orders in bulk (WooCommerce → Orders, filter Failed).

## Logs

**WooCommerce → Status → Logs**, source `anti-card-testing`. Reasons include `honeypot`, `js_proof`, `no_checkout_session`, `too_fast`, `turnstile`, `global_flood`, `comment_rate_limit`.
