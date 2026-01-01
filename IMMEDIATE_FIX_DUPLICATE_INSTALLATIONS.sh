#!/bin/bash

# TeamSpeak Website - Duplicate Installation Fix Script
# Found: Two installations causing query spam

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  TS-Website Duplicate Installation Fix${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""
echo -e "${YELLOW}Found TWO installations:${NC}"
echo "  1. /var/www/web.reape.rs/"
echo "  2. /var/www/private/"
echo ""
echo -e "${RED}Both are connecting to TeamSpeak, causing query spam!${NC}"
echo ""

# Safety check
if [ "$EUID" -ne 0 ]; then 
   echo -e "${RED}Please run as root (sudo)${NC}"
   exit 1
fi

echo "=========================================="
echo "Step 1: Backup Everything"
echo "=========================================="
echo ""

BACKUP_FILE="/root/ts-website-backup-$(date +%Y%m%d-%H%M%S).tar.gz"
echo "Creating backup: $BACKUP_FILE"
echo ""

if tar -czf "$BACKUP_FILE" /var/www/web.reape.rs/ /var/www/private/ 2>/dev/null; then
    echo -e "${GREEN}✓ Backup created successfully${NC}"
    ls -lh "$BACKUP_FILE"
else
    echo -e "${YELLOW}⚠ Backup failed or directories don't exist${NC}"
fi

echo ""
echo "=========================================="
echo "Step 2: Identify Active Installation"
echo "=========================================="
echo ""

echo "Checking web server configuration..."
echo ""

# Check Nginx
if command -v nginx &> /dev/null; then
    echo "Nginx configuration:"
    nginx -T 2>/dev/null | grep -E "root|web.reape.rs" | head -10 || echo "  (no matches)"
    echo ""
fi

# Check Apache
if command -v apache2 &> /dev/null; then
    echo "Apache configuration:"
    apache2ctl -S 2>/dev/null | grep -E "DocumentRoot|web.reape.rs" || echo "  (no matches)"
    echo ""
fi

echo ""
echo "=========================================="
echo "Step 3: Disable Duplicate Installation"
echo "=========================================="
echo ""

read -p "Which installation do you want to KEEP? (1 or 2): " choice

if [ "$choice" == "1" ]; then
    KEEP="/var/www/web.reape.rs/"
    REMOVE="/var/www/private/"
elif [ "$choice" == "2" ]; then
    KEEP="/var/www/private/"
    REMOVE="/var/www/web.reape.rs/"
else
    echo -e "${RED}Invalid choice. Exiting.${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}Will KEEP: $KEEP${NC}"
echo -e "${RED}Will DISABLE: $REMOVE${NC}"
echo ""

read -p "Proceed? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Aborted. No changes made."
    exit 0
fi

echo ""
echo "Disabling $REMOVE..."

if [ -d "$REMOVE" ]; then
    mv "$REMOVE" "${REMOVE}.DISABLED-$(date +%Y%m%d-%H%M%S)"
    echo -e "${GREEN}✓ Disabled successfully${NC}"
else
    echo -e "${YELLOW}⚠ Directory not found: $REMOVE${NC}"
fi

echo ""
echo "=========================================="
echo "Step 4: Restart Services"
echo "=========================================="
echo ""

# Restart PHP-FPM
for service in php-fpm php8.2-fpm php8.1-fpm php8.0-fpm php7.4-fpm php7.3-fpm; do
    if systemctl is-active --quiet $service 2>/dev/null; then
        echo "Restarting $service..."
        systemctl restart $service
        echo -e "${GREEN}✓ $service restarted${NC}"
    fi
done

# Restart web server
if systemctl is-active --quiet nginx 2>/dev/null; then
    echo "Restarting nginx..."
    systemctl restart nginx
    echo -e "${GREEN}✓ nginx restarted${NC}"
fi

if systemctl is-active --quiet apache2 2>/dev/null; then
    echo "Restarting apache2..."
    systemctl restart apache2
    echo -e "${GREEN}✓ apache2 restarted${NC}"
fi

echo ""
echo "=========================================="
echo "Step 5: Verify Fix"
echo "=========================================="
echo ""

echo "Checking for remaining installations..."
FOUND=$(find /var/www -name "TeamSpeakUtils.php" 2>/dev/null | grep -v DISABLED)
COUNT=$(echo "$FOUND" | grep -c "TeamSpeakUtils.php" || true)

if [ "$COUNT" -eq 1 ]; then
    echo -e "${GREEN}✓ Only ONE installation found (correct)${NC}"
    echo "$FOUND"
else
    echo -e "${YELLOW}⚠ Found $COUNT installations${NC}"
    echo "$FOUND"
fi

echo ""
echo "=========================================="
echo "Next Steps"
echo "=========================================="
echo ""
echo "1. Test your website:"
echo "   curl http://web.reape.rs/"
echo ""
echo "2. Check TeamSpeak query clients:"
echo "   (On TS server) clientlist -uid -ip"
echo "   Should see only 1-2 query clients now"
echo ""
echo "3. Monitor logs for 15 minutes:"
echo "   tail -f /path/to/teamspeak/logs/ts3server_*.log"
echo "   Should see far fewer connections"
echo ""
echo "4. If everything works after 24 hours, delete disabled installation:"
echo "   rm -rf ${REMOVE}.DISABLED-*"
echo ""
echo -e "${GREEN}Fix applied! Monitor for improvements.${NC}"
echo ""
echo "Backup saved at: $BACKUP_FILE"
echo ""
