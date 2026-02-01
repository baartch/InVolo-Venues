# Venue Database - AI Agent Guide

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

Uses tables: `venues`, `users`, `sessions`, `logs`, `settings`, `rate_limits` (see `DB.md`).

## Notes

- **config/** directory is for configuration files ONLY (config.php)
- All PHP helper functions belong in **src-php/** directory
- Sidebar consists only of icons, no labels
- Logs written via `logAction()` in `src-php/database.php`
- Edit TypeScript sources (not compiled JS) when changing map logic; rebuild the JS output as needed. TypeScript sources live in `src/`.
- Do NOT create a new markdown file to document each change or summarize your work unless specifically requested by the user.
- Do NOT automatically create GIT commits. But when user asks for it, commit only the changes you made.

## Deploy

```bash
./scripts/deploy_ftp.sh
```
