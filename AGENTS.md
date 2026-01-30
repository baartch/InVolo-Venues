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
├── user_management.php       # Admin-only user management
├── api/get_waypoints.php     # GPX output from DB venues
├── auth/                     # Login/logout + auth_check
├── config/                   # DB config + helpers
│   ├── config.php
│   ├── database.php
│   └── admin_check.php
├── public/                   # Static assets
│   ├── styles.css            # Global UI styles
│   ├── themes/forest.css     # Theme palette
│   ├── map.ts/map.js         # Map client
│   └── assets/               # Icons
├── scripts/cleanup.php       # Log/session retention
└── scripts/deploy_ftp.sh      # Deployment
```

## Database
Uses tables: `venues`, `users`, `sessions`, `logs`, `settings` (see `DB.md`).

## Notes
- Sidebar icons: map, user management (admin), logout.
- User management: create/delete users, role changes, password reset.
- Logs written via `logAction()` in `config/database.php`.

## Deploy
```bash
./scripts/deploy_ftp.sh
```
