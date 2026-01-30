# Technical Documentation for AI Agents

## Project Overview

**Venue Database** - A PHP/TypeScript web application for managing venue locations with authentication, search, and interactive mapping.

**Stack:** PHP 7.4+, TypeScript, Leaflet.js, Mapbox, Apache/Nginx

## Architecture

### Directory Structure

```
frontend/
├── index.php                    # Main entry point (protected)
├── .htaccess                    # Apache security rules
├── setup.sh                     # Automated setup script
├── tsconfig.json                # TypeScript configuration
├── package.json                 # Project metadata & scripts
│
├── api/                         # API Endpoints
│   └── get_waypoints.php        # Serves GPX file (auth required)
│
├── auth/                        # Authentication System
│   ├── auth_check.php           # Session validator
│   ├── login.php                # Login form & handler
│   └── logout.php               # Session destroyer
│
├── config/                      # Configuration (Protected)
│   ├── .htaccess                # Deny all access
│   ├── config.php               # Active config with credentials
│   └── config.example.php       # Template for distribution
│
├── public/                      # Public Assets
│   ├── assets/                  # Static files
│   │   └── marker.svg
│   ├── map.ts                   # TypeScript source
│   ├── map.js                   # Compiled JavaScript
│   └── waypoints.gpx            # Venue data (protected)

```

## Deployment

After any change run the deployment script:

```bash
./scripts/deploy_ftp.sh
```