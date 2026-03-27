# MulerTech Captcha Bundle

___
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mulertech/captcha.svg?style=flat-square)](https://packagist.org/packages/mulertech/captcha)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/captcha/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mulertech/captcha/actions/workflows/tests.yml)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/captcha/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/mulertech/captcha/actions/workflows/phpstan.yml)
[![GitHub Security Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/captcha/security.yml?branch=main&label=security&style=flat-square)](https://github.com/mulertech/captcha/actions/workflows/security.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/mulertech/captcha.svg?style=flat-square)](https://packagist.org/packages/mulertech/captcha)
[![Test Coverage](https://raw.githubusercontent.com/mulertech/captcha/badge/badge-coverage.svg)](https://packagist.org/packages/mulertech/captcha)
___

A self-hosted, image-based math captcha Symfony bundle. No external service required. Generates a
random arithmetic operation rendered as a low-quality JPEG with noise, validated server-side via
session. Protects contact and quote forms against bots without any third-party dependency.

## Requirements

- PHP 8.4+
- ext-gd
- Symfony 6.4, 7.x or 8.x

## Installation

```bash
composer require mulertech/captcha
```

Routes and form theme are **automatically registered** — no additional configuration required.

The bundle registers two endpoints:

| Route | Path | Description |
|---|---|---|
| `mulertech_captcha_image` | `GET /captcha/image?token=xxx` | Returns the JPEG captcha image |
| `mulertech_captcha_refresh` | `GET /captcha/refresh` | Returns `{token, imageUrl}` JSON for JS refresh |

## Usage

### 1. Add `CaptchaType` to your form

```php
use MulerTech\CaptchaBundle\Form\CaptchaType;

$builder->add('captcha', CaptchaType::class);
```

The field is automatically `mapped: false` and includes the `ValidCaptcha` constraint.

### 2. Render in your Twig template

Render the field **before** the submit button. The widget displays the captcha image, a refresh
button (JavaScript, no page reload), and the answer input:

```twig
{{ form_row(form.captcha) }}
<button type="submit">Envoyer</button>
{{ form_end(form) }}
```

### 3. Process the form in your controller

No special handling required. `$form->isValid()` returns `false` if the captcha answer is wrong
or expired, with a localized error message on the `captcha` field.

```php
$form->handleRequest($request);
if ($form->isSubmitted() && $form->isValid()) {
    // captcha passed — process the form
}
```

### Error messages

| Situation | Message |
|---|---|
| Wrong answer | `Le code de vérification est incorrect.` |
| Token expired / missing | `Le code de vérification a expiré, veuillez recommencer.` |

## CSP nonce support

The bundle's inline `<script>` tag supports Content Security Policy nonces.

### Automatic (with `mulertech/csp-bundle`)

If [`mulertech/csp-bundle`](https://packagist.org/packages/mulertech/csp-bundle) is installed, the
nonce is injected automatically — no additional configuration needed.

### Manual

Pass the nonce explicitly via the `csp_nonce` option:

```php
$builder->add('captcha', CaptchaType::class, [
    'csp_nonce' => $this->cspNonceGenerator->getNonce('main'),
]);
```

The nonce is added to the `<script>` tag rendered by the form theme:

```html
<script nonce="abc123">...</script>
```

## Security considerations

- **Token TTL**: captcha tokens expire after 10 minutes.
- **Session limit**: a maximum of 5 active tokens per session prevents session flooding.
- **Answer space**: math operations produce answers in the range 1–30. Apply **rate limiting** at
  the application level (e.g., Symfony's RateLimiter) to prevent brute-force attempts.

## Testing

```bash
./vendor/bin/mtdocker test-ai
```
