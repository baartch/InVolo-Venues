# Technical Documentation for AI Agents

## Project Overview

**Venue Crawler Frontend** - A PHP/TypeScript web application for managing venue locations with authentication, search, and interactive mapping.

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
│
└── docs/                        # Archived Documentation
    └── [legacy docs]
```

## Authentication Flow

### Session-Based Authentication

```
User Request → index.php
    ↓
require auth_check.php
    ↓
Check $_SESSION['logged_in']
    ├─ true  → Continue to app
    └─ false → Redirect to login.php
        ↓
    POST credentials
        ↓
    Validate against config.php
        ├─ valid   → Set session → Redirect to index.php
        └─ invalid → Show error
```

### Session Configuration

**Location:** `config/config.php`

```php
define('SESSION_NAME', 'venue_crawler_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// Security flags
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', 1); // HTTPS only
```

### Session Variables

```php
$_SESSION['logged_in']     = true;           // Boolean
$_SESSION['username']      = 'admin';        // String
$_SESSION['LAST_ACTIVITY'] = time();         // Unix timestamp
```

### Base Path Auto-Detection

The application auto-detects its installation directory:

```php
// In config/config.php
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$pathParts = explode('/', trim($scriptPath, '/'));
$lastPart = end($pathParts);

// Remove auth, api, or config from path
if (in_array($lastPart, ['auth', 'api', 'config'])) {
    array_pop($pathParts);
}

define('BASE_PATH', '/' . implode('/', $pathParts));
```

**Examples:**
- Root: `BASE_PATH = ''`
- Subdirectory: `BASE_PATH = '/venues'`
- Nested: `BASE_PATH = '/apps/venues'`

All redirects use: `header('Location: ' . BASE_PATH . '/path');`

## File Protection

### .htaccess Security

**Main `.htaccess`:**
```apache
# Block GPX files
<FilesMatch "\.(gpx)$">
    Require all denied
</FilesMatch>

# Block config files
<FilesMatch "^(config|config\.example)\.php$">
    Require all denied
</FilesMatch>
```

**`config/.htaccess`:**
```apache
Require all denied
```

### Access Control Matrix

| Path | Direct Access | Via PHP (Not Auth) | Via PHP (Auth) |
|------|---------------|-------------------|----------------|
| `public/waypoints.gpx` | ❌ 403 | ❌ Redirect | ✅ Via API |
| `config/config.php` | ❌ 403 | ⚠️ Include only | ⚠️ Include only |
| `index.php` | ❌ Redirect | ❌ Redirect | ✅ Display |
| `api/get_waypoints.php` | ❌ Redirect | ❌ Redirect | ✅ Serve GPX |
| `auth/login.php` | ✅ Display | ✅ Display | ➡️ Redirect to index |

## Frontend Architecture

### TypeScript Structure

**File:** `public/map.ts`

```typescript
// Type definitions
interface Waypoint {
  name: string;
  url: string;
  lat: string;
  lon: string;
  marker: any;
  popup: any;
}

// Global state
let mymap: any;
let mapCenter: number[] = [];
let allWaypoints: Waypoint[] = [];

// Main functions
async function parseWaypoints(): Promise<void>
function initializeSearch(): void
async function initializeMap(): Promise<void>
```

### Data Flow

```
Browser loads index.php
    ↓
Loads public/map.js
    ↓
initializeMap()
    ↓
parseWaypoints()
    ↓
fetch('api/get_waypoints.php')
    ↓
Server checks auth → Returns GPX
    ↓
Parse XML → Create markers → Display on map
    ↓
initializeSearch() → Setup keyboard shortcuts
```

### Search Functionality

**Features:**
- Real-time filtering
- Keyboard navigation (↑/↓/Enter/Escape)
- Ctrl+K global shortcut to focus
- Visual selection highlighting

**Implementation:**
```typescript
// Global shortcut
document.addEventListener('keydown', (e: KeyboardEvent) => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    searchInput.focus();
    searchInput.select();
  }
});

// Arrow navigation
searchInput.addEventListener('keydown', (e: KeyboardEvent) => {
  switch(e.key) {
    case 'ArrowDown': selectItem(selectedIndex + 1); break;
    case 'ArrowUp': selectItem(selectedIndex - 1); break;
    case 'Enter': navigateToSelected(); break;
    case 'Escape': closeResults(); break;
  }
});
```

## Build Process

### TypeScript Compilation

**Configuration:** `tsconfig.json`

```json
{
  "compilerOptions": {
    "target": "ES2020",
    "module": "ES2020",
    "outDir": "./public",
    "rootDir": "./public",
    "strict": true
  },
  "include": ["public/*.ts"]
}
```

**Build commands:**
```bash
bun run build    # One-time build
bun run watch    # Watch mode
```

**Input:** `public/map.ts`  
**Output:** `public/map.js`

## Security Implementation

### Path Handling

All PHP includes use `__DIR__` for reliability:

```php
// From index.php
require_once __DIR__ . '/auth/auth_check.php';

// From auth/auth_check.php  
require_once __DIR__ . '/../config/config.php';

// From api/get_waypoints.php
$gpxFile = __DIR__ . '/../public/waypoints.gpx';
```

### HTML Base Tag

Ensures all relative URLs resolve correctly:

```html
<head>
  <base href="<?php echo BASE_PATH; ?>/">
</head>
```

This makes `fetch('api/get_waypoints.php')` resolve to `/venues/api/get_waypoints.php` when installed in `/venues/`.

### XSS Prevention

```php
// User input sanitization
$error = htmlspecialchars($error);
```

### CSRF Protection

- SameSite cookies prevent cross-origin requests
- No CSRF tokens currently (could be added)

## Database Schema

**Not applicable** - Uses GPX file for data storage.

### GPX Structure

```xml
<?xml version="1.0"?>
<gpx version="1.1">
  <wpt lat="52.520008" lon="13.404954">
    <name>Venue Name</name>
    <url>https://venue-website.com</url>
  </wpt>
</gpx>
```

## API Endpoints

### GET /api/get_waypoints.php

**Authentication:** Required (session)

**Response:** GPX XML file

**Headers:**
```
Content-Type: application/gpx+xml
Cache-Control: private, max-age=3600
```

**Error Codes:**
- `302 Found` - Not authenticated (redirect to login)
- `404 Not Found` - GPX file missing
- `200 OK` - Success

## Common Modifications

### Adding a New Protected Page

1. Create PHP file in root
2. Add auth check at top:
   ```php
   <?php require_once __DIR__ . '/auth/auth_check.php'; ?>
   ```
3. Use `BASE_PATH` for links:
   ```html
   <a href="<?php echo BASE_PATH; ?>/new-page.php">Link</a>
   ```

### Adding a New API Endpoint

1. Create file in `api/` directory
2. Include auth check:
   ```php
   <?php require_once __DIR__ . '/../auth/auth_check.php'; ?>
   ```
3. Process request and return data

### Changing Session Timeout

Edit `config/config.php`:
```php
define('SESSION_LIFETIME', 7200); // 2 hours
```

### Adding New Keyboard Shortcuts

Edit `public/map.ts`:
```typescript
document.addEventListener('keydown', (e: KeyboardEvent) => {
  if (e.key === '/') {
    e.preventDefault();
    searchInput.focus();
  }
});
```

Then rebuild: `bun run build`

### Customizing Map Appearance

Edit `public/map.ts`:
```typescript
L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}', {
  id: 'mapbox/streets-v11',  // Change style here
  accessToken: 'YOUR_TOKEN'
});
```

## Troubleshooting Guide

### .htaccess Not Working

**Symptoms:** GPX files accessible directly, config files accessible

**Causes:**
1. `AllowOverride` not set in Apache config
2. Apache 2.2 vs 2.4 syntax differences
3. .htaccess file missing

**Solutions:**
```apache
# In Apache config (/etc/apache2/sites-available/...)
<Directory /path/to/frontend>
    AllowOverride All
</Directory>
```

Then: `sudo systemctl restart apache2`

### Session Not Persisting

**Symptoms:** Logged out immediately after login

**Causes:**
1. Session directory not writable
2. Cookie settings incompatible
3. Browser blocking cookies

**Debug:**
```php
// Add to config.php
error_log('Session ID: ' . session_id());
error_log('Session data: ' . print_r($_SESSION, true));
```

Check: `tail -f /var/log/apache2/error.log`

### TypeScript Compile Errors

**Symptoms:** `bun run build` fails

**Solutions:**
```bash
# Reinstall dependencies
rm -rf node_modules package-lock.json
bun install

# Check TypeScript version
bun list typescript

# Verify tsconfig.json is valid
cat tsconfig.json | jq .
```

### Wrong Redirect Paths

**Symptoms:** Redirects to `/auth/login.php` instead of `/venues/auth/login.php`

**Cause:** BASE_PATH not detected correctly

**Debug:**
```php
// Add to config.php after BASE_PATH definition
error_log('Detected BASE_PATH: ' . BASE_PATH);
```

**Manual override:**
```php
// In config/config.php
define('BASE_PATH', '/venues'); // Set manually
```

### Search Results Not Aligned

**Symptoms:** Dropdown wider than search input

**Cause:** Missing `box-sizing` or `width: 100%`

**Solution:** Check CSS in `index.php`:
```css
#search-results {
  width: 100%;
  box-sizing: border-box;
}
```

## Performance Considerations

### Optimization Opportunities

1. **GPX Caching:** Cache parsed waypoints in session
2. **Minification:** Minify map.js in production
3. **CDN:** Serve Leaflet from CDN (already done)
4. **Image Optimization:** Compress marker.svg
5. **HTTP/2:** Enable HTTP/2 for multiplexing

### Current Performance

- GPX parsing: ~50-200ms (depending on file size)
- Map rendering: ~100-300ms
- Search filtering: <10ms (real-time)
- Session validation: <5ms

## Testing Checklist

### Manual Testing

- [ ] Login with correct credentials
- [ ] Login with wrong credentials (should fail)
- [ ] Session timeout (wait 1 hour)
- [ ] Logout button works
- [ ] Search functionality (type, arrow keys, enter)
- [ ] Ctrl+K shortcut
- [ ] Map markers display
- [ ] Marker popups work
- [ ] Direct GPX access blocked (403)
- [ ] Direct config access blocked (403)
- [ ] Works in subdirectory
- [ ] Works in root directory

### Browser Testing

Test in:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile Safari (iOS)
- Chrome Mobile (Android)

### Security Testing

```bash
# Test GPX protection
curl -I https://site.com/venues/public/waypoints.gpx
# Should return: 403 Forbidden

# Test config protection
curl -I https://site.com/venues/config/config.php
# Should return: 403 Forbidden

# Test unauthenticated API access
curl https://site.com/venues/api/get_waypoints.php
# Should redirect to login (302)

# Test authenticated access
curl -b cookies.txt https://site.com/venues/api/get_waypoints.php
# Should return GPX data
```

## Deployment Checklist

- [ ] Change default credentials in `config/config.php`
- [ ] Set `config/config.php` to chmod 600
- [ ] Enable HTTPS with SSL certificate
- [ ] Set `session.cookie_secure = 1` (HTTPS only)
- [ ] Configure Apache `AllowOverride All`
- [ ] Verify .htaccess files are present
- [ ] Test all .htaccess rules work
- [ ] Build TypeScript: `bun run build`
- [ ] Remove development files (optional): `rm -rf node_modules`
- [ ] Set proper file permissions (644 for .php, 755 for directories)
- [ ] Test login/logout
- [ ] Test session timeout
- [ ] Test file protection (GPX, config)
- [ ] Monitor error logs for issues

## Environment Variables (Optional)

For better security, use environment variables instead of hardcoded credentials:

```php
// In config/config.php
define('LOGIN_USERNAME', getenv('VENUE_USERNAME') ?: 'admin');
define('LOGIN_PASSWORD', getenv('VENUE_PASSWORD') ?: 'default');
```

**Apache config:**
```apache
SetEnv VENUE_USERNAME "your_username"
SetEnv VENUE_PASSWORD "your_password"
```

**Nginx + PHP-FPM:**
```ini
# In PHP-FPM pool config
env[VENUE_USERNAME] = your_username
env[VENUE_PASSWORD] = your_password
```

## Future Enhancements

### Suggested Improvements

1. **Database Integration**
   - Store venues in MySQL/PostgreSQL
   - User management system
   - Venue CRUD operations

2. **Password Hashing**
   - Use `password_hash()` and `password_verify()`
   - Store hashed passwords instead of plaintext

3. **CSRF Protection**
   - Add tokens to forms
   - Validate on submission

4. **Rate Limiting**
   - Prevent brute force attacks
   - Limit login attempts

5. **Logging**
   - Log failed login attempts
   - Monitor suspicious activity
   - Audit trail for changes

6. **API Expansion**
   - RESTful API for venues
   - JSON responses
   - API key authentication

7. **Testing**
   - Unit tests (PHPUnit)
   - Integration tests
   - E2E tests (Playwright/Cypress)

8. **CI/CD**
   - Automated testing
   - Deployment pipeline
   - Version control integration

## Code Style Guidelines

### PHP
- Use `<?php` opening tags (no short tags)
- Include `require_once` for dependencies
- Use `__DIR__` for paths
- Sanitize user input with `htmlspecialchars()`
- Use `===` for comparisons

### TypeScript
- Use `async/await` for asynchronous code
- Type all function parameters and returns
- Use interfaces for complex types
- Use `const` and `let` (no `var`)
- Use arrow functions for callbacks

### CSS
- Use kebab-case for class names
- Group related properties
- Use modern properties (flexbox, grid)
- Add vendor prefixes where needed
- Mobile-first responsive design

## Migration Notes

### From JavaScript to PHP Auth (Completed)

- Removed client-side password prompt
- Implemented PHP session authentication
- Protected GPX file with server-side checks
- Added login/logout pages

### From Flat to Hierarchical Structure (Completed)

- Organized files into directories (api/, auth/, config/, public/)
- Updated all paths to use `__DIR__`
- Added BASE_PATH auto-detection
- Fixed redirect paths for subdirectory support

### Key Breaking Changes

1. **Login URL changed:** `/login.php` → `/auth/login.php`
2. **GPX access changed:** Direct access → Via API endpoint
3. **Config location changed:** Root → `/config/` directory

---

**Last Updated:** January 2026  
**Version:** 2.0  
**Maintainer:** [Your Team]
