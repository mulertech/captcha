# Release notes for captcha

## v1.0.0 - 2026-03-27

v1.0.0 - 2026-03-28

What's new

Self-hosted image-based math captcha bundle for Symfony forms. No external service or API key required — everything runs on your server using PHP's built-in GD extension.

Features

- Generates random arithmetic operations (addition and subtraction, always positive result)
- Renders the question as a low-quality JPEG image with noise pixels and random lines to resist basic OCR
- Stores the expected answer server-side in the session — never exposed to the client
- Token-based system: each form render gets a unique token, preventing session collisions across tabs
- Token TTL (10 minutes) and session limit (5 active tokens) for security
- CaptchaType Symfony form field (compound: hidden token + answer input)
- ValidCaptcha constraint with distinct messages for wrong answer vs. expired token
- /captcha/refresh JSON endpoint for transparent client-side refresh on re-render after failed submission
- Bundled Twig form theme with JavaScript refresh via addEventListener (CSP-compatible with nonce support)
- Auto-registered form theme and routes — no manual configuration required
- Optional CSP nonce integration: auto-detects CspNonceGenerator if available, or accepts manual csp_nonce option
- error_bubbling disabled by default so validation errors stay on the captcha field
- Internationalization (French and English) via Symfony Translation

Requirements

- PHP 8.4+
- ext-gd
- Symfony 6.4, 7.x or 8.x
