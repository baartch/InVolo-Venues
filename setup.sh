#!/bin/bash

echo "🎵 Venue Database Frontend Setup"
echo "================================"
echo ""

# Check if config.php exists
if [ -f "config/config.php" ]; then
    echo "✓ config/config.php already exists"
else
    echo "Creating config/config.php from template..."
    cp config/config.example.php config/config.php
    echo "⚠️  Please edit config/config.php and change the default password!"
fi

echo ""

# Install npm dependencies
if [ -d "node_modules" ]; then
    echo "✓ node_modules already exists"
else
    echo "Installing npm dependencies..."
    npm install
fi

echo ""

# Build TypeScript
echo "Building TypeScript..."
npm run build

echo ""
echo "================================"
echo "✓ Setup complete!"
echo ""
echo "Next steps:"
echo "1. Edit config/config.php and update DB connection credentials"
echo "2. Ensure your web server has PHP enabled"
echo "3. Access auth/login.php in your browser"
echo ""
echo "Default credentials:"
echo "  Username: admin"
echo "  Password: venues2026"
echo ""
echo "Test locally with:"
echo "  php -S localhost:8000"
echo "  Then open: http://localhost:8000/auth/login.php"
echo ""
echo "Documentation:"
echo "  README.md - User guide and quick start"
echo "  AGENTS.md - Technical documentation for developers"
echo ""
