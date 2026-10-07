# North Payment Gateway for Bagisto

### 1. Introduction:

North Payment Gateway integrates **North's Embedded Checkout (Form)** with Bagisto as a payment method.

The North payment form is displayed inside a modal on the Bagisto checkout page, allowing customers to complete their payment without leaving the checkout page.

The integration supports both **Sandbox** and **Production** environments.

**North Embedded Checkout Documentation:**

https://developer.north.com/products/online/embedded-checkout/form-integration-guide

---

### 2. Requirements:

 **Bagisto**: v2.4.11

---

### 3. Installation:

 Unzip the **North Payment Gateway** extension and merge the `packages` folder into the Bagisto project root directory.

 Go to the **composer.json** file and add the following entry under `autoload.psr-4`:

```json
"Webkul\\North\\": "packages/Webkul/North/src"
```

 Go to **bootstrap/providers.php** and add:

```php
Webkul\North\Providers\NorthServiceProvider::class,
```

 Run the following commands:

```bash
composer dump-autoload
php artisan optimize:clear
```

The North Payment Gateway is now installed.

---

### 4. Admin Configuration:

Go to:

**Configure → Sales → Payment Methods → North**

Configure the following fields:

| Field                      | Description                                              |
| -------------------------- | -------------------------------------------------------- |
| **Status**                 | Enable the North payment method.                         |
| **Title**                  | Payment method title displayed at checkout.              |
| **Description**            | Payment method description displayed at checkout.        |
| **Checkout ID**            | North Embedded Checkout ID.                              |
| **Profile ID**             | North Embedded Checkout Profile ID.                      |
| **API Key**                | North Production Private API Key.                        |
| **Sandbox**                | Enable while testing with the North Sandbox environment. |
| **API Test Key**           | North Sandbox Private API Key.                           |
| **Webhook Signing Secret** | Secret generated when registering the webhook.           |

---

### 5. Webhook Setup:

Register the following URL in the North dashboard:

```text
https://<your-domain>/north/webhook
```

North will provide a **Webhook Signing Secret** after the webhook is registered.

Add this secret in:

**Configure → Sales → Payment Methods → North → Webhook Signing Secret**

The webhook signature is verified using HMAC-SHA256 before processing the webhook request.

---


For Sandbox testing, enable the **Sandbox** option and use:

```text
Card Number: 4111 1111 1111 1111
Expiry: Any future date
CVV: Any 3-digit value
ZIP: Any valid ZIP
```
