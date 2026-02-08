# BooKing - AI Agent Guide

## Overview

PHP + TypeScript app for venue mapping with MariaDB-backed authentication, sessions, logs, and admin user management. All POST forms are protected with CSRF tokens.

## Stack

- PHP 7.4+
- MariaDB/MySQL
- Leaflet + Mapbox
- TypeScript for the map UI

## Key Paths

```
├── index.php                      # App entry point
├── app/pages/admin/user_management.php # Admin-only user management
├── app/pages/venues/              # Venue management views
├── app/pages/profile/             # Profile password reset
├── app/routes/waypoints/index.php # GPX output from DB venues
├── app/pages/auth/                # Login/logout
├── app/routes/auth/check.php      # Auth check (session validation)
├── config/                        # Configuration only
│   └── config.php                 # DB credentials & app settings
├── app/src-php/                   # Shared PHP helpers
│   ├── auth/                      # Auth + session helpers
│   │   ├── admin_check.php
│   │   ├── cookie_helpers.php
│   │   ├── csrf.php
│   │   ├── rate_limit.php
│   │   └── team_admin_check.php
│   ├── communication/             # Email + mailbox helpers
│   ├── core/                      # Core app helpers
│   │   ├── database.php           # DB functions (connection, sessions, logging)
│   │   ├── defaults.php
│   │   ├── form_helpers.php       # Form validation helpers
│   │   ├── layout.php             # Page layout rendering
│   │   ├── search_helpers.php     # Web search API helpers
│   │   ├── security_headers.php   # HTTP security headers (CSP, HSTS, etc.)
│   │   ├── settings.php           # Settings management
│   │   └── theme.php              # Theme selection (legacy)
│   └── venues/                    # Venue-specific helpers
├── app/public/                    # Static assets
│   ├── js/map.js                  # Map client
│   └── assets/                    # Icons
├── app/scripts/cleanup.php        # Log/session/rate limit retention
└── scripts/deploy_ftp.sh          # Deployment
```

## Directories

- `app/` - Application codebase (PHP pages/routes, assets, scripts, and TypeScript sources).
- `app/pages/` - Server-rendered pages (auth, admin, venues, profile, team, communication).
- `app/partials/` - Shared PHP view fragments for layouts and page sections.
- `app/public/` - Public static assets (CSS, JS, icons, downloads).
- `app/public/assets/` - Image and icon assets used by the UI.
- `app/public/css/` - Compiled and vendor CSS files.
- `app/public/js/` - Compiled JavaScript output from TypeScript.
- `app/routes/` - HTTP endpoints and API handlers.
- `app/routes/auth/` - Authentication/session validation routes.
- `app/routes/communication/` - Email conversation and mailbox routes.
- `app/routes/email/` - Email send, delete, and attachment routes.
- `app/routes/venues/` - Venue-related API endpoints.
- `app/routes/waypoints/` - GPX waypoint output endpoint.
- `app/scripts/` - CLI/cron scripts (cleanup, mailbox fetch).
- `app/src/` - TypeScript source files for the client UI.
- `app/src-php/` - Shared PHP helper libraries.
- `app/src-php/auth/` - Auth/session helpers (CSRF, cookies, admin checks).
- `app/src-php/communication/` - Email and mailbox helpers.
- `app/src-php/core/` - Core utilities (DB, layout, security headers, settings).
- `app/src-php/venues/` - Venue repository and actions.
- `config/` - Runtime configuration (config.php).
- `dev_helpers/` - Development helper scripts and notes.
- `scripts/` - Project-level scripts (deployment, diagnostics).
- `sql/` - Database schema and migrations.
- `tests/` - Test scripts (CLI checks and utilities).
- `.pi/` - Pi agent workspace metadata.
- `.pi/todos/` - Pi agent todo storage.
- `node_modules/` - Node dependency install output.

## Database

The database schema is in `sql/schema.sql` and includes the following tables:

- `venues`: Main table storing venue details (name, address, coordinates, capacity, etc.)
- `users`: User accounts (username, password hash, role)
- `sessions`: Active user sessions for authentication
- `logs`: Application logs (user actions, errors, timestamps)
- `settings`: Application configuration settings
- `teams`: User teams for grouping
- `team_members`: Many-to-many mapping of users to teams
- `rate_limits`: Rate limiting tracking for brute force protection

## Security

- **Cookie Security**: Session cookies use `__Host-` prefix (HTTPS) with Secure, HttpOnly, and SameSite=Strict flags (see `docs/COOKIE_SECURITY.md`)
- **Security Headers**: Automatic HTTP security headers (CSP, X-Frame-Options, etc.) via `.htaccess` and `app/src-php/core/security_headers.php` (see `docs/SECURITY_HEADERS.md`)
- **CSRF Protection**: All POST forms protected with CSRF tokens (see `app/src-php/auth/csrf.php`)
- **Rate Limiting**: Login attempts limited to prevent brute force (see `app/src-php/auth/rate_limit.php`)
- **Sessions**: Database-backed with 1-hour expiration, secure cookie handling via `app/src-php/auth/cookie_helpers.php`
- **Passwords**: Bcrypt hashing, admin-enforced resets available

## Notes

- `config/` directory is for configuration files ONLY (config.php)
- Don't write any inline CSS or JS in PHP files. CSS is provided via Bulma CDN, and JS gets compiled from TypeScript in `app/src/` to `app/public/js/`.
- All PHP helper functions belong in `app/src-php/` directory
- **Security headers** automatically loaded via `app/src-php/core/layout.php` on every page
- **Cookies** must be set via `app/src-php/auth/cookie_helpers.php` functions (setSessionCookie, clearSessionCookie)
- Sidebar consists only of icons, no labels
- Logs written via `logAction()` in `app/src-php/core/database.php` (do NOT log sensitive data like cookies)
- **NEVER** edit JS files! Edit TypeScript sources (not compiled JS) when JS logic changes; rebuild the JS output as needed. TypeScript sources live in `app/src/`.
- Do NOT create a new markdown file to document each change or summarize your work unless specifically requested by the user.
- Do NOT automatically create GIT commits. But when user asks for it, commit only the changes you made.

## Deploy

```bash
./scripts/deploy_ftp.sh
```
