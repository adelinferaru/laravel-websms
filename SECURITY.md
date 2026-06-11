# Security policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 2.x     | Yes       |
| < 2.0   | No        |

## Reporting a vulnerability

Please do not open a public issue for security problems. Instead, either:

- use [GitHub private vulnerability reporting](https://github.com/adelinferaru/laravel-websms/security/advisories/new), or
- email **adelin.feraru@gmail.com**.

You can expect an initial response within a few days. Please include a description
of the issue, steps to reproduce, and the affected version.

## Scope notes

- This package transmits your WebSMS credentials (username/password for SOAP,
  API key for REST) to `websms.com.cy` over HTTPS. Keep them in environment
  variables — never commit them.
- The SOAP session ID is stored in your application's configured Laravel cache
  store. If your cache store is shared or readable by other tenants, scope it
  accordingly (`WEBSMS_SESSION_STORE`).
- Issues in the WebSMS.com.cy gateway itself should be reported to the vendor
  (sales@websms.com.cy), not to this repository.
