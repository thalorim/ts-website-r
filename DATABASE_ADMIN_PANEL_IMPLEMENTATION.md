# Database Admin Panel Implementation

## Overview
This implementation adds a comprehensive database settings management interface to the admin panel, allowing administrators to view and modify database connection settings directly from the web interface.

## Features Implemented

### 1. Database Settings Form (`src/private/templates/admin.latte`)
- **Location**: Top section of the admin panel
- **Fields**:
  - Database Type (MySQL/MariaDB or SQLite)
  - Server/Hostname
  - Username
  - Password (with option to keep existing)
  - Database Name
  - Table Prefix
  - Port
  - Charset
- **Pre-populated**: All fields are automatically filled with current database configuration values
- **Visual Design**: Clean, organized layout with helpful tooltips and form validation

### 2. Admin Page Updates (`src/admin/index.php`)
- Retrieves current database configuration from `Config::i()->getDatabaseConfig()`
- Passes database config data to the template as `$dbConfig`
- Handles cases where database config might not exist yet with sensible defaults
- Maintains backward compatibility with existing admin panel features

### 3. Save Database Configuration API (`src/api/save-database-config.php`)
- **Endpoint**: `api/save-database-config.php`
- **Security**: 
  - Requires authentication (CLDBID 3 only)
  - CSRF token validation
  - Input validation for all required fields
- **Features**:
  - Tests database connection before saving
  - Creates automatic backup of existing config file
  - Writes configuration to `private/dbconfig.php`
  - Sets secure file permissions (0600)
  - Preserves existing password if left blank
  - Returns detailed success/error messages
- **Error Handling**: Comprehensive validation and connection testing

### 4. Test Database Connection API (`src/api/test-database-connection.php`)
- **Endpoint**: `api/test-database-connection.php`
- **Purpose**: Test database connectivity without saving changes
- **Security**: Same authentication requirements as save endpoint
- **Features**:
  - Validates connection parameters
  - Tests actual database connectivity
  - Returns connection details (server, database name, table count)
  - Provides helpful error messages for common connection issues
  - 5-second timeout for connection attempts

### 5. JavaScript Handler (`src/js/admin.js`)
- **Test Connection Button**: 
  - Validates form data
  - Sends AJAX request to test endpoint
  - Displays results with success/error messages
  - Shows database details on successful connection
  - Provides loading state during test
- **Save Configuration Form**:
  - Confirmation dialog before saving
  - Sends AJAX request to save endpoint
  - Creates backup before modifying config
  - Displays success/error messages
  - Offers page reload after successful save
  - Proper error handling and user feedback
- **User Experience**:
  - Real-time feedback
  - Disabled buttons during operations
  - Loading spinners
  - Color-coded alerts (success/error/info)

## Database Configuration File Format

The database configuration is stored in `src/private/dbconfig.php` with the following structure:

```php
<?php
/*
 * TS-website database config file
 * Last modified at YYYY-MM-DD HH:MM:SS
 */

return [
    'database_type' => 'mysql',
    'server' => '127.0.0.1',
    'username' => 'db_user',
    'password' => 'db_password',
    'database_name' => 'db_name',
    'prefix' => 'tsw_',
    'port' => '3306',
    'charset' => 'utf8mb4'
];
```

## Security Features

1. **Authentication**: Only users with CLDBID 3 can access the admin panel
2. **CSRF Protection**: All forms include CSRF tokens
3. **Validation**: Comprehensive input validation on both client and server side
4. **Connection Testing**: Database credentials are tested before being saved
5. **Backup Creation**: Automatic backup of existing configuration before changes
6. **Secure Permissions**: Config file is set to 0600 (read/write owner only)
7. **Password Handling**: 
   - Option to keep existing password
   - Not displayed in browser (password field type)
   - Properly escaped in config file

## Usage Instructions

### Accessing the Database Settings
1. Log in with an account that has CLDBID 3
2. Navigate to the Admin Panel
3. Database settings are shown at the top of the page

### Testing Database Connection
1. Fill in or modify the database connection fields
2. Click "Test Connection" button
3. Review the connection test results
4. If successful, you'll see:
   - Database name
   - Server address
   - Number of tables found with the specified prefix
   - Table prefix being used

### Saving Database Configuration
1. After testing successfully, click "Save Database Config"
2. Confirm the action in the dialog box
3. Wait for the save operation to complete
4. A backup of the old configuration is automatically created
5. Page reload is recommended after saving

### Password Field Behavior
- If you leave the password field **blank**, the existing password from the current configuration will be retained
- If you enter a password, it will replace the existing password
- This allows you to update other fields without re-entering the password

## Error Handling

The implementation provides helpful error messages for common issues:

- **Access Denied (1045)**: Incorrect username/password
- **Unknown Database (1049)**: Database doesn't exist - needs to be created manually
- **Connection Refused**: Cannot reach database server - check hostname/port
- **Permission Errors**: File system permission issues with config file
- **Invalid Input**: Missing or invalid required fields

## Files Modified/Created

### Modified Files:
1. `src/private/templates/admin.latte` - Added database settings form section
2. `src/admin/index.php` - Added database config data passing to template

### Created Files:
1. `src/api/save-database-config.php` - API endpoint for saving database configuration
2. `src/api/test-database-connection.php` - API endpoint for testing database connections
3. `src/js/admin.js` - JavaScript for handling form interactions and AJAX requests

## Technical Dependencies

- **PHP**: Medoo database library for connection handling
- **JavaScript**: jQuery for AJAX and DOM manipulation
- **CSS**: Bootstrap for styling (already present in the project)
- **Icons**: Font Awesome icons (already present in the project)

## Compatibility

- Works with existing Config and DatabaseUtils classes
- Maintains backward compatibility with existing admin panel features
- Follows the same patterns used throughout the TSWebsite project
- Uses Latte template syntax consistent with the rest of the application

## Future Enhancements (Optional)

Potential improvements that could be added:
- Support for additional database types (PostgreSQL, etc.)
- Database migration tools from the admin panel
- Connection pooling configuration
- Read/write splitting configuration
- Backup/restore database functionality
- Database status monitoring dashboard
