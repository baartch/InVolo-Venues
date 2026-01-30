# 🎵 Venue Database

A secure web application for booking agents to manage and visualize venue locations on an interactive map.

## 🚀 Quick Start

### Setup (3 Steps)

```bash
# 1. Run setup script
./setup.sh

# 2. Configure credentials
nano config/config.php
# Configure the database connection

# 3. Start server
php -S localhost:8000
```

Open: **http://localhost:8000/auth/login.php**

Set up user accounts in the `users` table (with `password_hash` values). Ensure at least one admin user exists.

Example (generate password hash in PHP):
```php
<?php echo password_hash('your-password', PASSWORD_DEFAULT); ?>
```

## ✨ Features

### 🗺️ Interactive Map
- Powered by Leaflet and Mapbox
- Custom venue markers
- Click markers to see venue details
- Smooth zoom and pan

### 🔍 Smart Search
- **Ctrl+K** - Quick access to search field
- Real-time filtering as you type
- Keyboard navigation with arrow keys
- Press Enter to select a venue

### ⌨️ Keyboard Shortcuts
| Key | Action |
|-----|--------|
| `Ctrl+K` or `Cmd+K` | Focus search field |
| `↓` Arrow Down | Next search result |
| `↑` Arrow Up | Previous search result |
| `Enter` | Go to selected venue |
| `Escape` | Close search results |

### 🔒 Security
- Database-backed authentication
- Sessions stored in MariaDB
- Automatic logout after 1 hour
- Secure session cookies (HttpOnly, SameSite)
- Logs recorded in the `logs` table

## 📋 Requirements

- **PHP** 7.4 or higher
- **Web Server**: Apache or Nginx (Apache recommended)
- **Node.js & npm** (for development only)
- **MariaDB/MySQL**

## 🔧 Configuration

### Database Connection

Edit `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'involo_venues');
define('DB_USER', 'dbuservenues');
define('DB_PASSWORD', 'your_secure_password');
```

### Session Timeout

Default is 1 hour. To change:

```php
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
```

### Base Path

The application auto-detects its installation path. Works in:
- Root directory: `https://example.com/`
- Subdirectory: `https://example.com/venues/`
- Nested: `https://example.com/apps/venues/`

## 🌐 Deployment

### Apache (Recommended)

1. **Copy files to web directory:**
   ```bash
   cp -r frontend/* /var/www/html/venues/
   ```

2. **Set permissions:**
   ```bash
   chmod 644 /var/www/html/venues/*.php
   chmod 600 /var/www/html/venues/config/config.php
   ```

3. **Enable .htaccess in Apache config:**
   ```apache
   <Directory /var/www/html/venues>
       AllowOverride All
       Require all granted
   </Directory>
   ```

4. **Restart Apache:**
   ```bash
   sudo systemctl restart apache2
   ```

### Nginx

Add to your server block:

```nginx
location ~ \.gpx$ {
    deny all;
}

location ^~ /venues/config/ {
    deny all;
}

location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
}
```

## 🆘 Troubleshooting

### Can't Login?
1. Verify the user exists in the `users` table
2. Clear browser cookies
3. Check database connectivity

### .htaccess Errors?
If you see "not allowed here" errors:
1. Enable `AllowOverride All` in Apache config
2. Restart Apache: `sudo systemctl restart apache2`

### Search Results Misaligned?
1. Clear browser cache (Ctrl+F5)
2. Check browser zoom is at 100%

### Venues Not Loading?
1. Verify you're logged in
2. Check browser console for errors
3. Ensure the `venues` table has latitude/longitude data

### Redirects Not Working?
If redirects go to wrong URLs (e.g., `/auth/login.php` instead of `/venues/auth/login.php`):
- The BASE_PATH should auto-detect correctly
- Check `config/config.php` has the BASE_PATH code
- Manually set if needed: `define('BASE_PATH', '/venues');`

## 🛠️ Development

### Build TypeScript

```bash
# One-time build
npm run build

# Watch mode (auto-rebuild on changes)
npm run watch
```

### Maintenance

Add a cron job to remove old logs and sessions (older than 180 days):

```bash
# Run nightly at 2:30 (keep 180 days)
30 2 * * * /usr/bin/php /path/to/venues/scripts/cleanup.php 180
```

### Theme

The UI uses a central theme file. Update `public/themes/forest.css` to adjust the forest palette.

### Navigation

The main UI includes a sidebar with map, admin-only user management, and logout icons.

### File Structure

```
frontend/
├── index.php              # Main app page
├── auth/                  # Authentication system
│   ├── login.php          # Login page
│   ├── logout.php         # Logout handler
│   └── auth_check.php     # Session validator
├── api/                   # API endpoints
│   └── get_waypoints.php  # Protected venues endpoint
├── config/                # Configuration (protected)
│   ├── config.php         # Credentials & settings
│   └── database.php       # Database helpers
├── public/                # Public assets
│   ├── map.ts             # TypeScript source
│   └── map.js             # Compiled JavaScript
└── docs/                  # Documentation (archived)
```

## 📊 Adding Venues

Venues are stored in the `venues` table in MariaDB. Ensure each venue has `latitude` and `longitude` values.

## 🔐 Security Checklist

Before going live:

- [ ] Created admin user in the `users` table
- [ ] Set `config/config.php` to chmod 600
- [ ] Enabled HTTPS with SSL certificate
- [ ] Verified `.htaccess` protects config files
- [ ] Tested login/logout functionality
- [ ] Verified session timeout works
- [ ] Verified logs are written to the `logs` table
- [ ] Checked that direct access to `/config/config.php` returns 403

## 📱 Browser Support

Tested and works in:
- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## 🎓 Tips & Tricks

### Quick Search Workflow
```
Ctrl+K → Type venue name → ↓/↑ to select → Enter
```

### Finding Venues by City
Just type the city name in the search box.

### Clearing Search
Press `Escape` or clear the search field.

## 📞 Support

For technical documentation and development details, see `AGENTS.md`.

## 📄 License

[Your License Here]

---

**Made with ❤️ for booking agents and singer-songwriters**
