# Venue Database - AI Agent Guide

## Overview
PHP + TypeScript app for venue mapping with MariaDB-backed authentication, sessions, logs, and admin user management.

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
├── routes/waypoints/index.php # GPX output from DB venues
├── pages/auth/               # Login/logout
├── routes/auth/check.php     # Auth check (session validation)
├── config/                   # DB config + helpers
│   ├── config.php
│   ├── database.php
│   └── admin_check.php
├── src-php/                  # Shared PHP helpers
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
- Sidebar icons: map, venues, user management (admin), logout.
- User management: create/delete users, role changes, password reset.
- Logs written via `logAction()` in `config/database.php`.
- Always commit only the changes you made.

## Deploy
```bash
./scripts/deploy_ftp.sh
```
