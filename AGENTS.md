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
├── index.php                 # Map view (auth required)
├── pages/admin/user_management.php # Admin-only user management
├── pages/venues/             # Venue management views
├── pages/profile/            # Profile password reset
├── routes/waypoints/index.php # GPX output from DB venues
├── pages/auth/               # Login/logout
├── routes/auth/check.php     # Auth check (session validation)
├── config/                   # Configuration only
│   └── config.php            # DB credentials & app settings
├── src-php/                  # Shared PHP helpers
│   ├── database.php          # DB functions (connection, sessions, logging)
│   ├── admin_check.php       # Admin role authorization check
│   ├── rate_limit.php        # Rate limiting (brute force protection)
│   ├── csrf.php              # CSRF token protection
│   ├── cookie_helpers.php    # Secure cookie management (__Host- prefix, flags)
│   ├── security_headers.php  # HTTP security headers (CSP, HSTS, etc.)
│   ├── form_helpers.php      # Form validation helpers
│   ├── layout.php            # Page layout rendering
│   ├── search_helpers.php    # Web search API helpers
│   ├── settings.php          # Settings management
│   └── theme.php             # Theme selection
├── public/                   # Static assets
│   ├── css/styles.css        # Global UI styles
│   ├── css/themes/forest.css # Theme palette
│   ├── js/map.js             # Map client
│   └── assets/               # Icons
├── scripts/cleanup.php       # Log/session/rate limit retention
└── scripts/deploy_ftp.sh     # Deployment
```

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
- **Security Headers**: Automatic HTTP security headers (CSP, X-Frame-Options, etc.) via `.htaccess` and `src-php/security_headers.php` (see `docs/SECURITY_HEADERS.md`)
- **CSRF Protection**: All POST forms protected with CSRF tokens (see `src-php/csrf.php`)
- **Rate Limiting**: Login attempts limited to prevent brute force (see `src-php/rate_limit.php`)
- **Sessions**: Database-backed with 1-hour expiration, secure cookie handling via `src-php/cookie_helpers.php`
- **Passwords**: Bcrypt hashing, admin-enforced resets available

## Notes

- `config/` directory is for configuration files ONLY (config.php)
- Don't write any inline CSS or JS in PHP files. CSS goes in `public/css/` and JS gets compiled from TypeScript in `src/` to `public/js/`.
- All PHP helper functions belong in `src-php/` directory
- **Security headers** automatically loaded via `src-php/layout.php` on every page
- **Cookies** must be set via `src-php/cookie_helpers.php` functions (setSessionCookie, clearSessionCookie)
- Sidebar consists only of icons, no labels
- Logs written via `logAction()` in `src-php/database.php` (do NOT log sensitive data like cookies)
- **NEVER** edit JS files! Edit TypeScript sources (not compiled JS) when JS logic changes; rebuild the JS output as needed. TypeScript sources live in `src/`.
- Do NOT create a new markdown file to document each change or summarize your work unless specifically requested by the user.
- Do NOT automatically create GIT commits. But when user asks for it, commit only the changes you made.

## Deploy

```bash
./scripts/deploy_ftp.sh
```
