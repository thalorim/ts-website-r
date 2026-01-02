<?php
/**
 * News Channel Display Configuration Example
 * 
 * Add these configuration options to your src/private/config.local.php file
 * to enable and configure the news channel display system.
 */

// =============================================================================
// ADMIN ACCESS CONFIGURATION
// =============================================================================

// Set the admin UID who can access the news channel display admin panel
// This should match your admin user's UID
// You can find your UID by checking the profiles table or Auth::getUid()
$config['admin_uid'] = 'Jv/d1+pX7/q343RrIMPTTVpob+U=';

// =============================================================================
// API TOKEN CONFIGURATION (for automated updates)
// =============================================================================

// Token for API endpoint authentication (optional, but recommended for automation)
// Generate a secure random token: openssl rand -hex 32
// This token is required when calling the API endpoint from web requests
// CLI calls don't require a token
$config['news_channel_update_token'] = 'your-secure-random-token-here';

// Example: Good token (32+ characters, random)
// $config['news_channel_update_token'] = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6';

// Example: Bad token (too short, predictable)
// $config['news_channel_update_token'] = 'secret123';  // DON'T USE THIS!

// =============================================================================
// USAGE EXAMPLES
// =============================================================================

/*
 * 1. MANUAL UPDATE VIA ADMIN PANEL
 * ---------------------------------
 * Navigate to: https://your-website.com/admin/news-channel-display.php
 * Click "Update All Channels" button
 *
 *
 * 2. AUTOMATIC UPDATE VIA CRON JOB
 * ---------------------------------
 * Add this to your crontab (crontab -e):
 * 
 * # Update news channels every hour
 * 0 * * * * cd /path/to/your/website && php -r "require 'src/private/php/load.php'; \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();"
 *
 *
 * 3. UPDATE VIA API ENDPOINT (with token)
 * ---------------------------------------
 * Call the API endpoint with your token:
 * curl "https://your-website.com/api/update-news-channels.php?token=your-secure-random-token-here"
 *
 * Or add to crontab:
 * 0 * * * * curl -s "https://your-website.com/api/update-news-channels.php?token=your-secure-random-token-here" > /dev/null
 *
 *
 * 4. UPDATE VIA PHP CODE (after posting news)
 * -------------------------------------------
 * // After adding news to the database
 * $newsStore->addNews($title, $content);
 * 
 * // Immediately update channels
 * \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();
 *
 *
 * 5. UPDATE VIA CLI DIRECTLY
 * ---------------------------
 * php -r "require 'src/private/php/load.php'; \$result = \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels(); echo json_encode(\$result, JSON_PRETTY_PRINT) . PHP_EOL;"
 */

// =============================================================================
// ADVANCED CONFIGURATION (optional)
// =============================================================================

// You can customize the behavior by modifying the NewsChannelDisplayManager class
// or by extending it. Here are some ideas:

/*
 * Custom News Formatting
 * ----------------------
 * Edit the formatNewsDescription() method in:
 * src/private/php/Utils/NewsChannelDisplayManager.php
 * 
 * To customize:
 * - BBCode styling (colors, sizes, formatting)
 * - Date format
 * - Layout structure
 * - Additional information to display
 *
 *
 * Custom News Source
 * ------------------
 * The system uses DefaultNewsStore by default, which reads from the 'news' table.
 * You can modify the updateChannel() method to use a different news source:
 * - External API
 * - RSS feed
 * - Different database table
 * - Combined sources
 *
 *
 * Channel-Specific Templates
 * --------------------------
 * You could add a 'template' field to the configuration table to allow
 * different formatting templates per channel:
 * - Simple template (titles only)
 * - Detailed template (full content)
 * - Custom template (user-defined BBCode)
 *
 *
 * Conditional Formatting
 * ----------------------
 * Add logic to format news differently based on:
 * - News age (highlight recent news)
 * - News type/category
 * - Content length
 * - Author
 */

// =============================================================================
// SECURITY NOTES
// =============================================================================

/*
 * IMPORTANT SECURITY CONSIDERATIONS:
 * 
 * 1. Token Security
 *    - Use a long, random token (32+ characters)
 *    - Never commit the token to version control
 *    - Don't share the token publicly
 *    - Rotate tokens periodically
 *
 * 2. Admin Access
 *    - Only set admin_uid to trusted users
 *    - Keep the UID private
 *    - Review access logs regularly
 *
 * 3. TeamSpeak Permissions
 *    - The query bot needs minimal permissions
 *    - Only grant b_channel_modify_description
 *    - Don't grant admin permissions to the bot
 *
 * 4. Input Validation
 *    - The system validates all inputs
 *    - BBCode is escaped to prevent injection
 *    - Database uses prepared statements
 *
 * 5. Rate Limiting
 *    - Consider rate limiting API endpoint
 *    - Don't update too frequently (respect TeamSpeak)
 *    - Use reasonable cron intervals (15-60 minutes)
 */

// =============================================================================
// TROUBLESHOOTING
// =============================================================================

/*
 * Common Issues and Solutions:
 *
 * Issue: "Cannot connect to TeamSpeak server"
 * Solution: Check your query credentials in the main config
 *
 * Issue: "Permission denied"
 * Solution: Verify admin_uid matches your user's UID
 *
 * Issue: "Channel not found"
 * Solution: Verify channel ID is correct and channel exists
 *
 * Issue: "Configuration already exists"
 * Solution: Each channel can only have one configuration
 *
 * Issue: "No news available"
 * Solution: Add news via admin panel or check 'news' table
 *
 * Issue: "Invalid token"
 * Solution: Check token in config matches token in URL
 *
 * Issue: "Table doesn't exist"
 * Solution: Access admin panel page to auto-create table
 */

// =============================================================================
// SUPPORT AND DOCUMENTATION
// =============================================================================

/*
 * For more information, see:
 * - NEWS_CHANNEL_DISPLAY_README.md (full documentation)
 * - NEWS_CHANNEL_DISPLAY_QUICK_START.md (5-minute setup guide)
 * - NEWS_CHANNEL_DISPLAY_IMPLEMENTATION.md (technical details)
 *
 * Admin Panel: /admin/news-channel-display.php
 * API Endpoint: /api/update-news-channels.php?token=YOUR_TOKEN
 */
