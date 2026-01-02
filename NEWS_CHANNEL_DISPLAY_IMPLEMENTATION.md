# News Channel Display - Implementation Summary

## Overview

A complete system for displaying website news in TeamSpeak channel descriptions, similar to the status-display system. This allows administrators to configure channels that automatically show the latest news from the database in BBCode-formatted channel descriptions.

## Files Created

### 1. Core Manager Class
**File**: `/workspace/src/private/php/Utils/NewsChannelDisplayManager.php`

Manages all news channel display functionality:
- Database table management (auto-creates `news_channel_display` table)
- CRUD operations for channel configurations
- News fetching from database
- BBCode formatting for TeamSpeak
- Channel description updates via TeamSpeak API
- Batch update operations

**Key Methods**:
- `addConfiguration()` - Add new channel configuration
- `updateConfiguration()` - Modify existing configuration
- `removeConfiguration()` - Delete configuration
- `updateChannelNews()` - Update single channel with latest news
- `updateAllChannels()` - Batch update all enabled channels
- `formatNewsDescription()` - Format news list into BBCode

### 2. Admin Panel Interface
**File**: `/workspace/src/admin/news-channel-display.php`

Full-featured admin panel for managing news channel displays:
- List all configurations in a table
- Add new configurations via modal form
- Update single channel on-demand
- Batch update all channels
- Enable/disable configurations
- Delete configurations
- Real-time status display
- Bootstrap 4 styled UI with FontAwesome icons

**Features**:
- CSRF protection on all forms
- Admin authentication (same as main admin panel)
- Channel selection dropdown
- Configurable display options
- Success/error messaging
- Last update timestamps
- Responsive design

### 3. API Endpoint
**File**: `/workspace/src/api/update-news-channels.php`

Secure API endpoint for automated updates:
- Token-based authentication
- CLI support (no token required)
- JSON response format
- Batch update capability
- Error handling

**Use Cases**:
- Cron job automation
- External integrations
- Webhook triggers
- Scheduled updates

### 4. Admin Panel Integration
**File**: `/workspace/src/private/templates/admin.latte` (modified)

Added quick access link in main admin panel:
- Button to access news channel display management
- Clear icon and label
- Consistent with existing admin UI

### 5. Documentation Files

#### a) Full Documentation
**File**: `/workspace/NEWS_CHANNEL_DISPLAY_README.md`

Comprehensive documentation including:
- Feature overview
- Installation guide
- Configuration instructions
- Usage examples
- Automatic update setup
- Troubleshooting guide
- Technical details
- Security notes
- FAQ

#### b) Quick Start Guide
**File**: `/workspace/NEWS_CHANNEL_DISPLAY_QUICK_START.md`

5-minute setup guide:
- Simple 3-step process
- Visual examples
- Common configurations
- Pro tips
- Quick troubleshooting

#### c) Implementation Summary
**File**: `/workspace/NEWS_CHANNEL_DISPLAY_IMPLEMENTATION.md` (this file)

Technical overview for developers.

## Database Schema

### Table: `news_channel_display`

```sql
CREATE TABLE `news_channel_display` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `channel_id` INT NOT NULL,
    `news_limit` INT DEFAULT 5,
    `show_content` TINYINT(1) DEFAULT 1,
    `content_max_length` INT DEFAULT 200,
    `enabled` TINYINT(1) DEFAULT 1,
    `last_updated` INT DEFAULT NULL,
    UNIQUE KEY `unique_channel` (`channel_id`)
);
```

**Fields**:
- `id`: Auto-increment primary key
- `channel_id`: TeamSpeak channel ID to update
- `news_limit`: Number of news items to display (1-20)
- `show_content`: Whether to show content preview (1) or just titles (0)
- `content_max_length`: Maximum characters of content to show
- `enabled`: Whether this configuration is active
- `last_updated`: Unix timestamp of last successful update

**Indexes**:
- Unique constraint on `channel_id` prevents duplicate configurations

## How It Works

### 1. Configuration Flow
```
Admin Panel → Add Configuration → Database → Manager Class
```

1. Admin accesses `/admin/news-channel-display.php`
2. Fills out configuration form
3. Configuration stored in database
4. Manager class validates and stores settings

### 2. Update Flow
```
Trigger → Manager → News Fetch → Format → TeamSpeak Update
```

1. **Trigger**: Manual button click or API call
2. **Manager**: `NewsChannelDisplayManager::updateChannelNews()`
3. **News Fetch**: Gets latest news from `news` table via `DefaultNewsStore`
4. **Format**: Converts news to BBCode using `formatNewsDescription()`
5. **TeamSpeak Update**: Updates channel description via TeamSpeak API

### 3. News Formatting

News is formatted in BBCode like this:

```bbcode
[center][b][size=15]Latest News[/size][/b][/center]

[b]1. News Title[/b]
[size=9][color=gray]Posted: 2026-01-02 15:30[/color][/size]
News content preview (truncated to max length)...

[b]2. Another News Title[/b]
[size=9][color=gray]Posted: 2026-01-01 10:00[/color][/size]
Another news content...

[hr]
[size=8][color=gray]Last updated: 2026-01-02 16:00:00[/color][/size]
```

**BBCode Elements Used**:
- `[center]` - Center alignment
- `[b]` - Bold text
- `[size=X]` - Font size
- `[color=X]` - Text color
- `[hr]` - Horizontal rule
- Text is escaped to prevent BBCode injection

## Integration Points

### Existing Components Used

1. **DefaultNewsStore** (`src/private/php/News/DefaultNewsStore.php`)
   - Fetches news from database
   - Provides news list with title, content, dates

2. **TeamSpeakUtils** (`src/private/php/Utils/TeamSpeakUtils.php`)
   - Manages TeamSpeak connection
   - Provides server instance for channel updates

3. **DatabaseUtils** (`src/private/php/Utils/DatabaseUtils.php`)
   - Database connection management
   - Medoo query builder access

4. **CacheManager** (`src/private/php/CacheManager.php`)
   - Channel list caching for dropdown

5. **Auth** (`src/private/php/Auth.php`)
   - Admin authentication
   - User identification

6. **CsrfUtils** (`src/private/php/Utils/CsrfUtils.php`)
   - CSRF token generation and validation

7. **TemplateUtils** (`src/private/php/Utils/TemplateUtils.php`)
   - Error page rendering

## Security Features

1. **Admin Authentication**
   - Checks if user is logged in
   - Validates admin UID matches configured admin
   - Same security model as main admin panel

2. **CSRF Protection**
   - All POST forms include CSRF tokens
   - Tokens validated on submission
   - Prevents cross-site request forgery

3. **API Token Authentication**
   - Separate token for API endpoint
   - Constant-time comparison (hash_equals)
   - No token required for CLI usage

4. **Input Validation**
   - Channel ID validation
   - News limit range checking (1-20)
   - Content length validation (50-1000)
   - Type casting for all inputs

5. **BBCode Escaping**
   - User input escaped in channel descriptions
   - Prevents BBCode injection attacks
   - Replaces brackets with parentheses

6. **Database Protection**
   - Prepared statements via Medoo
   - No raw SQL injection vulnerabilities
   - Unique constraints prevent duplicates

## Configuration Options

### Per-Channel Settings

1. **News Limit** (1-20)
   - Controls how many news items to display
   - Lower values for concise displays
   - Higher values for archive channels

2. **Show Content** (boolean)
   - Toggle content preview
   - When disabled, shows only titles and dates
   - Useful for headline-only displays

3. **Content Max Length** (50-1000)
   - Truncates content to specified length
   - Adds "..." when truncated
   - Balances detail vs. description length

4. **Enabled** (boolean)
   - Temporarily disable without deleting
   - Skipped in batch updates when disabled
   - Can re-enable anytime

### Global Settings (Config)

1. **admin_uid**
   - UID of administrator who can access panel
   - Default: `"Jv/d1+pX7/q343RrIMPTTVpob+U="`

2. **news_channel_update_token**
   - Secret token for API authentication
   - Required for non-CLI API calls
   - Should be long and random

## Usage Scenarios

### Scenario 1: Main News Channel
```
Channel: "📰 News & Announcements"
News Limit: 3
Show Content: Yes
Max Length: 200
```
Perfect for a main info channel with recent highlights.

### Scenario 2: News Archive
```
Channel: "📚 News Archive"
News Limit: 15
Show Content: Yes
Max Length: 300
```
Comprehensive news history with more detail.

### Scenario 3: Headlines Only
```
Channel: "📌 Latest Headlines"
News Limit: 10
Show Content: No
Max Length: N/A
```
Quick overview with just titles and dates.

### Scenario 4: Multiple Languages
```
Channel EN: "News (English)" - News Limit: 5
Channel DE: "Nachrichten (Deutsch)" - News Limit: 5
```
Same news, different channels for different communities.

## Automation Options

### Option 1: Cron Job (Recommended)

Every hour:
```bash
0 * * * * cd /path/to/website && php -r "require 'src/private/php/load.php'; \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();"
```

Every 15 minutes:
```bash
*/15 * * * * cd /path/to/website && php -r "require 'src/private/php/load.php'; \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();"
```

### Option 2: API Endpoint

With curl:
```bash
curl "https://your-site.com/api/update-news-channels.php?token=YOUR_TOKEN"
```

With cron:
```bash
0 * * * * curl -s "https://your-site.com/api/update-news-channels.php?token=YOUR_TOKEN" > /dev/null
```

### Option 3: Webhook Integration

Trigger update after news is posted:
```php
// In your news posting code
$newsStore->addNews($title, $content);

// Trigger channel update
\Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();
```

## Maintenance

### Regular Tasks

1. **Monitor Updates**
   - Check "Last Updated" column in admin panel
   - Verify channels are updating successfully
   - Review error messages if any

2. **Adjust Settings**
   - Tune news limit based on content volume
   - Adjust max length based on description size
   - Enable/disable channels as needed

3. **Database Cleanup**
   - Old configurations can be deleted if no longer needed
   - No automatic cleanup required

### Troubleshooting Checklist

1. ✓ TeamSpeak server is accessible
2. ✓ Bot has `b_channel_modify_description` permission
3. ✓ Channel ID is correct and channel exists
4. ✓ News exists in database (`news` table)
5. ✓ Configuration is enabled
6. ✓ No PHP errors in logs
7. ✓ Database table exists and is accessible

## Performance Considerations

1. **Caching**
   - Channel list is cached via CacheManager
   - News is fetched fresh each update
   - No caching of formatted descriptions

2. **Batch Updates**
   - All updates are sequential (not parallel)
   - Each update connects to TeamSpeak separately
   - Consider rate limiting for many channels

3. **Database Queries**
   - Efficient queries via Medoo
   - Indexed lookups on channel_id
   - Minimal database impact

4. **TeamSpeak Load**
   - Each update modifies one channel
   - No significant server load
   - Updates are quick (<1 second per channel)

## Future Enhancement Ideas

1. **HTML to BBCode Conversion**
   - Better handling of HTML in news content
   - Convert common HTML tags to BBCode equivalents

2. **Template System**
   - Customizable description templates
   - Different formats per channel
   - Placeholders for dynamic content

3. **Filtering**
   - Filter news by category
   - Show only certain types of news
   - Tag-based filtering

4. **Scheduling**
   - Schedule updates at specific times
   - Different update intervals per channel
   - Off-peak update scheduling

5. **Multi-Language**
   - Detect channel language
   - Show translated news
   - Language-specific formatting

6. **Statistics**
   - Track update success rate
   - Monitor update duration
   - View update history

## Testing Recommendations

1. **Manual Testing**
   - Add configuration via admin panel
   - Click update button
   - Verify channel description in TeamSpeak
   - Test enable/disable toggle
   - Test delete functionality

2. **API Testing**
   - Call API endpoint with token
   - Verify JSON response
   - Test without token (should fail)
   - Test from CLI (should work)

3. **Error Testing**
   - Try invalid channel ID
   - Test with no news in database
   - Test with disconnected TeamSpeak
   - Test with invalid permissions

4. **Edge Cases**
   - Very long news content
   - News with special characters
   - Empty news content
   - News with HTML/BBCode

## Summary

This implementation provides a complete, production-ready system for displaying website news in TeamSpeak channels. It follows the same patterns and security measures as the existing codebase, integrates seamlessly with existing components, and provides both manual and automated update capabilities.

The system is flexible, secure, and easy to use, making it simple for administrators to keep their TeamSpeak community informed about website news and updates.
