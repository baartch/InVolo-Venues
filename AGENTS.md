# BooKing - AI Agent Guide

## Overview

PHP + TypeScript app for venue mapping with MariaDB-backed authentication, sessions, logs, and admin user management.

## Stack

- PHP 8.0+
- HTMX
- MariaDB/MySQL
- Leaflet + Mapbox
- TypeScript

## Key Paths

```
├── app/                     # Application codebase (MVC, assets, scripts)
│   ├── controllers/         # HTTP controllers (admin, auth, communication, email, map, etc.)
│   ├── models/              # PHP domain/model helpers (auth, communication, core, venues)
│   ├── views/               # Server-rendered views (admin, auth, communication, map, etc.)
│   ├── partials/            # Shared PHP view fragments
│   │   └── tables/          # Table partials
│   └── public/              # Public static assets
│       ├── assets/          # Icons and imagery
│       ├── css/             # Compiled and vendor CSS
│       ├── js/              # Compiled JavaScript output
│       └── vendor/          # Third-party vendor assets
├── config/                  # Runtime configuration (config.php)
├── dev_helpers/             # Development helper scripts and notes
├── scripts/                 # Project-level scripts (deployment, diagnostics)
├── sql/                     # Database schema and migrations
├── tests/                   # Test scripts (CLI checks and utilities)
├── .pi/                     # Pi agent workspace metadata
│   └── todos/               # Pi agent todo storage
├── node_modules/            # Node dependency install output
└── index.php                # App entry point
```

## Database

The database schema is in `sql/schema.sql`.

## Typescript/Javascript

Typescript path (input): `src/`  
Javascript path (output): `app/public/js/`

**NEVER** edit JS files! Edit TypeScript sources (not compiled JS) when JS logic changes; rebuild the JS output as needed. TypeScript sources live in `src/`.

Build TS with bun.

```bash
bun run build
```

## Security

- **Cookie Security**: Session cookies use `__Host-` prefix (HTTPS) with Secure, HttpOnly, and SameSite=Strict flags (see `docs/COOKIE_SECURITY.md`)
- **Security Headers**: Automatic HTTP security headers (CSP, X-Frame-Options, etc.) via `.htaccess` and `app/models/core/security_headers.php` (see `docs/SECURITY_HEADERS.md`)
- **CSRF Protection**: All POST forms protected with CSRF tokens (see `app/models/auth/csrf.php`)
- **Rate Limiting**: Login attempts limited to prevent brute force (see `app/models/auth/rate_limit.php`)
- **Sessions**: Database-backed with 1-hour expiration, secure cookie handling via `app/models/auth/cookie_helpers.php`
- **Passwords**: Bcrypt hashing, admin-enforced resets available

## HTMX PHP helper

- class: `app/models/core/htmx_class.php`
- Include `htmx_class.php` and use `HTMX::isRequest()` to branch HTMX vs full-page responses.
- Use request helpers like `HTMX::getTrigger()`, `getTarget()`, `getCurrentUrl()`, and request method helpers (`isGet()`, `isPost()`, etc.) to read HTMX metadata.
- Use response helpers like `HTMX::trigger()`/`triggerMultiple()`, `pushUrl()`/`replaceUrl()`, `redirect()`, `refresh()`, `reswap()`, `retarget()`, `location()`, `reselect()`, `stopPolling()`, or `noContent()` to set the appropriate `HX-*` headers.

## Notes

- `config/` directory is for configuration files ONLY (config.php)
- Don't write any inline CSS or JS in PHP files. CSS is provided via Bulma CDN, and JS gets compiled from TypeScript.
- **Security headers** automatically loaded via `app/models/core/layout.php` on every page
- **Cookies** must be set via `app/models/auth/cookie_helpers.php` functions (setSessionCookie, clearSessionCookie)
- Sidebar consists only of icons, no labels
- Logs written via `logAction()` in `app/models/core/database.php` (do NOT log sensitive data like cookies)
- List item highlighting (client-side): add `data-list-selectable` on the list container, `data-list-item` on clickable entries, and optional `data-list-active-class` to override the default `is-active` class (handled in `src/list-panel.ts`).
- Do NOT create a new markdown file to document each change or summarize your work unless specifically requested by the user.
- **DO NOT COMMIT** unless the user tells you to. Commit only changes you made.

### Tables

Build tables with the partials in `app/partials/tables`.

<!-- SPECKIT START -->

Current implementation plan: `specs/001-team-shows/plan.md`

## Active Technologies

- PHP 8.0+, TypeScript (existing frontend pipeline) + HTMX request/response helpers, Bulma UI patterns, existing table/detail partials (001-team-shows)
- MariaDB/MySQL (schema updates in `sql/schema.sql`) (001-team-shows)
- PHP 8.0+, TypeScript (existing frontend pipeline) + HTMX helpers, Bulma patterns, shared table/detail partials, existing link editor flow (001-team-shows)
- MariaDB/MySQL (`sql/schema.sql`) (001-team-shows)

## Recent Changes

- 001-team-shows: Added PHP 8.0+, TypeScript (existing frontend pipeline) + HTMX request/response helpers, Bulma UI patterns, existing table/detail partials

<!-- SPECKIT END -->
