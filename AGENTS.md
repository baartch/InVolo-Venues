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
├── config/                   # DB config + helpers
│   ├── config.php
│   ├── database.php
│   └── admin_check.php
├── src-php/                  # Shared PHP helpers
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
├── scripts/cleanup.php       # Log/session retention
└── scripts/deploy_ftp.sh      # Deployment
```

## Database

Uses tables: `venues`, `users`, `sessions`, `logs`, `settings` (see `DB.md`).

## Notes

- Sidebar consists only of icons, no labels
- Logs written via `logAction()` in `config/database.php`.
- Edit TypeScript sources (not compiled JS) when changing map logic; rebuild the JS output as needed. TypeScript sources live in `src/`.
- Do NOT create a new markdown file to document each change or summarize your work unless specifically requested by the user.
- Always commit only the changes you made.

## Deploy

```bash
./scripts/deploy_ftp.sh
```
