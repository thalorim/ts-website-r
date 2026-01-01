#!/bin/bash

# TeamSpeak Website Query Fix - Automated Application Script
# This script helps you apply the fix more easily

set -e

echo "================================================"
echo "TeamSpeak Website - Query Spam Fix Tool"
echo "================================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "src/js/status.js" ]; then
    echo -e "${RED}ERROR: This script must be run from the ts-website root directory${NC}"
    echo "Current directory: $(pwd)"
    exit 1
fi

echo -e "${GREEN}✓ Found ts-website installation${NC}"
echo ""

# Check what's already fixed
echo "Checking current status..."
echo ""

# Check JavaScript
JS_INTERVAL=$(grep -o "}, [0-9]* \* 1000)" src/js/status.js | grep -o "[0-9]*" || echo "unknown")
if [ "$JS_INTERVAL" = "30" ]; then
    echo -e "${GREEN}✓ JavaScript polling: FIXED (30 seconds)${NC}"
    JS_FIXED=true
elif [ "$JS_INTERVAL" = "10" ]; then
    echo -e "${YELLOW}⚠ JavaScript polling: NEEDS FIX (currently 10 seconds)${NC}"
    JS_FIXED=false
else
    echo -e "${YELLOW}⚠ JavaScript polling: UNKNOWN (could not detect)${NC}"
    JS_FIXED=false
fi

echo ""
echo "================================================"
echo "Database Configuration"
echo "================================================"
echo ""

# Ask about database
echo "To check/update database cache settings, we need your database credentials."
echo ""
read -p "Do you want to check/update database settings now? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    read -p "Database host (default: localhost): " DB_HOST
    DB_HOST=${DB_HOST:-localhost}
    
    read -p "Database name: " DB_NAME
    read -p "Database username: " DB_USER
    read -sp "Database password: " DB_PASS
    echo ""
    
    read -p "Table prefix (leave empty if none, e.g., 'tswebsite_'): " DB_PREFIX
    
    echo ""
    echo "Checking current database settings..."
    
    # Create temp SQL file
    TMP_CHECK="/tmp/ts_check_$$.sql"
    cat > "$TMP_CHECK" <<EOF
SELECT 
    identifier, 
    value as 'seconds',
    CASE 
        WHEN CAST(value AS UNSIGNED) < 20 THEN 'TOO LOW'
        WHEN CAST(value AS UNSIGNED) < 60 THEN 'OK'
        ELSE 'GOOD'
    END as 'status'
FROM ${DB_PREFIX}config 
WHERE identifier IN ('cache_serverinfo', 'cache_clientlist', 'cache_channelist')
ORDER BY identifier;
EOF

    # Run check
    if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$TMP_CHECK" 2>/dev/null; then
        echo ""
        
        # Check if values need updating
        NEEDS_FIX=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM ${DB_PREFIX}config WHERE identifier IN ('cache_serverinfo', 'cache_clientlist') AND CAST(value AS UNSIGNED) < 30;" 2>/dev/null || echo "0")
        
        if [ "$NEEDS_FIX" -gt 0 ]; then
            echo -e "${YELLOW}⚠ Database cache settings need updating${NC}"
            echo ""
            read -p "Apply recommended fixes to database? (y/n): " -n 1 -r
            echo ""
            
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                # Create fix SQL
                TMP_FIX="/tmp/ts_fix_$$.sql"
                cat > "$TMP_FIX" <<EOF
UPDATE ${DB_PREFIX}config SET value = '30' WHERE identifier = 'cache_serverinfo';
UPDATE ${DB_PREFIX}config SET value = '30' WHERE identifier = 'cache_clientlist';
EOF
                
                if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$TMP_FIX" 2>/dev/null; then
                    echo -e "${GREEN}✓ Database settings updated successfully!${NC}"
                    
                    # Show new values
                    echo ""
                    echo "New settings:"
                    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$TMP_CHECK" 2>/dev/null
                else
                    echo -e "${RED}✗ Failed to update database settings${NC}"
                fi
                
                rm -f "$TMP_FIX"
            fi
        else
            echo -e "${GREEN}✓ Database cache settings look good!${NC}"
        fi
    else
        echo -e "${RED}✗ Failed to connect to database${NC}"
        echo "Please check your credentials and try again."
    fi
    
    rm -f "$TMP_CHECK"
fi

echo ""
echo "================================================"
echo "Summary"
echo "================================================"
echo ""

if [ "$JS_FIXED" = true ]; then
    echo -e "${GREEN}✓ JavaScript: Fixed${NC}"
else
    echo -e "${YELLOW}⚠ JavaScript: The file has been updated in this repository${NC}"
    echo "  Make sure to deploy src/js/status.js to your web server!"
fi

echo ""
echo "Next steps:"
echo "1. Deploy updated files to your web server"
echo "2. Clear PHP opcache (if applicable): service php-fpm reload"
echo "3. Clear browser cache and test"
echo "4. Visit api/debug-cache-stats.php to verify"
echo "5. Monitor TeamSpeak logs for improvement"
echo ""
echo "For more information, see:"
echo "  - README-QUERY-FIX.md (quick start)"
echo "  - INSTALLATION_GUIDE.md (detailed steps)"
echo "  - QUERY_ANALYSIS.md (technical details)"
echo ""
echo -e "${GREEN}Done!${NC}"
