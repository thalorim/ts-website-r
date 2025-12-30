#!/bin/bash

###############################################################################
# TeamSpeak Status Display Feature - Installation Script
###############################################################################
#
# This script helps you install the Status Display feature for TS-website
#
# Usage: bash install-status-display.sh
#
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   TeamSpeak Status Display - Installation Script     ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Function to print colored messages
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Check if running from correct directory
if [ ! -f "src/private/php/load.php" ]; then
    print_error "Error: This script must be run from the TS-website root directory"
    exit 1
fi

print_success "Found TS-website installation"
echo ""

# Step 1: Verify required files
print_info "Step 1: Verifying required files..."
echo ""

FILES=(
    "src/private/php/Utils/StatusDisplayManager.php"
    "src/private/php/status-display-bot.php"
    "src/admin/status-display.php"
    "src/api/status-display-update.php"
    "src/installer/dbinstall_status_display.sql"
)

ALL_FILES_EXIST=true

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        print_success "Found: $file"
    else
        print_error "Missing: $file"
        ALL_FILES_EXIST=false
    fi
done

if [ "$ALL_FILES_EXIST" = false ]; then
    print_error "Some required files are missing. Please ensure all files are extracted correctly."
    exit 1
fi

echo ""
print_success "All required files are present"
echo ""

# Step 2: Make bot script executable
print_info "Step 2: Setting file permissions..."
echo ""

chmod +x src/private/php/status-display-bot.php
print_success "Made bot script executable"
echo ""

# Step 3: Database installation
print_info "Step 3: Database installation"
echo ""
print_warning "You need to install the database table manually or via this script"
echo ""

read -p "Do you want to install the database table now? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    print_info "Please provide your database credentials:"
    echo ""
    
    read -p "Database host (default: localhost): " DB_HOST
    DB_HOST=${DB_HOST:-localhost}
    
    read -p "Database name: " DB_NAME
    
    read -p "Database user: " DB_USER
    
    read -sp "Database password: " DB_PASS
    echo ""
    
    read -p "Database table prefix (press enter if none): " DB_PREFIX
    
    echo ""
    print_info "Installing database table..."
    
    # Replace DBPREFIX in SQL file
    SQL_FILE="src/installer/dbinstall_status_display.sql"
    SQL_CONTENT=$(cat "$SQL_FILE")
    
    if [ -n "$DB_PREFIX" ]; then
        SQL_CONTENT="${SQL_CONTENT//DBPREFIX/$DB_PREFIX}"
    else
        SQL_CONTENT="${SQL_CONTENT//DBPREFIX/}"
    fi
    
    # Execute SQL
    if echo "$SQL_CONTENT" | mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"; then
        print_success "Database table created successfully"
    else
        print_error "Failed to create database table"
        print_warning "You may need to run the SQL manually"
        exit 1
    fi
else
    echo ""
    print_warning "Skipping database installation"
    print_info "You can install the database table manually by running:"
    echo "  mysql -u USERNAME -p DATABASE_NAME < src/installer/dbinstall_status_display.sql"
    echo ""
fi

echo ""

# Step 4: Bot execution options
print_success "Installation complete!"
echo ""
print_info "Next Steps:"
echo ""
echo "1. Configure status displays via admin panel:"
echo "   ${BLUE}http://your-website.com/admin/status-display.php${NC}"
echo ""
echo "2. Start the monitoring bot using one of these methods:"
echo ""
echo "   ${GREEN}Option A:${NC} Run once (for testing)"
echo "   ${YELLOW}php src/private/php/status-display-bot.php --once${NC}"
echo ""
echo "   ${GREEN}Option B:${NC} Run as daemon"
echo "   ${YELLOW}php src/private/php/status-display-bot.php --daemon --interval=30${NC}"
echo ""
echo "   ${GREEN}Option C:${NC} Run in background"
echo "   ${YELLOW}nohup php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &${NC}"
echo ""
echo "   ${GREEN}Option D:${NC} Create systemd service (recommended for production)"
echo "   See INSTALL_STATUS_DISPLAY.md for systemd service configuration"
echo ""
echo "3. Read the documentation:"
echo "   - ${BLUE}STATUS_DISPLAY_FEATURE.md${NC} - Complete feature documentation"
echo "   - ${BLUE}INSTALL_STATUS_DISPLAY.md${NC} - Detailed installation guide"
echo ""

read -p "Would you like to test the installation now? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    print_info "Running bot test (--once mode)..."
    echo ""
    php src/private/php/status-display-bot.php --once
    echo ""
    print_success "Test completed!"
    echo ""
    print_warning "Note: If you see 'No configurations found', you need to add configurations via the admin panel first"
fi

echo ""
print_success "Setup complete! Enjoy your new status display feature! 🎉"
echo ""
