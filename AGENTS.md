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
├── app/                     # Application codebase (PHP pages/routes, assets, scripts, TS)
│   ├── pages/               # Server-rendered pages (auth, admin, venues, profile, team)
│   ├── partials/            # Shared PHP view fragments
│   ├── public/              # Public static assets
│   │   ├── assets/          # Icons and imagery
│   │   ├── css/             # Compiled and vendor CSS
│   │   └── js/              # Compiled JavaScript output
│   ├── routes/              # HTTP endpoints and API handlers
│   │   ├── auth/            # Authentication/session validation routes
│   │   ├── communication/   # Email conversation routes
│   │   ├── email/           # Email send/delete/attachment routes
│   │   ├── venues/          # Venue-related API endpoints
│   │   └── waypoints/       # GPX waypoint output endpoint
│   ├── scripts/             # CLI/cron scripts (cleanup, mailbox fetch)
│   ├── src/                 # TypeScript source files
│   └── src-php/             # Shared PHP helper libraries
│       ├── auth/            # Auth/session helpers
│       ├── communication/   # Email and mailbox helpers
│       ├── core/            # Core utilities (DB, layout, security headers, settings)
│       └── venues/          # Venue repository and actions
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

The database schema is in `sql/schema.sql` and includes the following tables:

- `venues`: Main table storing venue details (name, address, coordinates, capacity, etc.)
- `users`: User accounts (username, password hash, role)
- `sessions`: Active user sessions for authentication
- `logs`: Application logs (user actions, errors, timestamps)
- `settings`: Application configuration settings
- `teams`: User teams for grouping
- `team_members`: Many-to-many mapping of users to teams
- `mailboxes`: Team/user mailboxes with IMAP/SMTP credentials
- `email_conversations`: Conversation threads grouped by mailbox and participants
- `email_messages`: Email records for inbox, drafts, sent, and trash
- `email_attachments`: Stored email attachment metadata and file paths
- `email_templates`: Saved email templates per team
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
