<?php
/**
 * Admin Debug Tool
 * 
 * Use this to diagnose admin access issues.
 * Access at: /api/admin-debug.php
 */

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

$debug = [];

// Check if logged in
$debug['logged_in'] = Auth::isLoggedIn();

if ($debug['logged_in']) {
    $debug['user'] = [
        'uid' => Auth::getUid(),
        'cldbid' => Auth::getCldbid(),
        'nickname' => Auth::getNickname(),
    ];
    
    // Try to get server groups
    try {
        $debug['user']['server_groups'] = Auth::getUserServerGroupIds();
    } catch (\Exception $e) {
        $debug['user']['server_groups'] = 'Error: ' . $e->getMessage();
    }
}

// Check admin configuration
$debug['admin_config'] = [
    'admin_uids' => Config::get("admin_uids", []),
    'admin_cldbids' => Config::get("admin_cldbids", []),
    'admin_groups' => Config::get("admin_groups", []),
];

// Check if user is admin
$debug['is_admin'] = Auth::isAdmin();

// Provide solution
if ($debug['logged_in'] && !$debug['is_admin']) {
    $debug['solution'] = [
        'problem' => 'You are logged in but not configured as an admin',
        'your_credentials' => [
            'uid' => $debug['user']['uid'],
            'cldbid' => $debug['user']['cldbid'],
        ],
        'next_steps' => [
            '1. Choose one of these SQL queries to run on your database:',
            '',
            'Option A - Grant admin by UID (recommended):',
            "INSERT OR REPLACE INTO config (key, value) VALUES ('admin_uids', '[\"" . $debug['user']['uid'] . "\"]');",
            '',
            'Option B - Grant admin by CLDBID:',
            "INSERT OR REPLACE INTO config (key, value) VALUES ('admin_cldbids', '[" . $debug['user']['cldbid'] . "]');",
            '',
            'Option C - Grant admin by server group (if you have admin groups):',
            "INSERT OR REPLACE INTO config (key, value) VALUES ('admin_groups', '[9, 10]');",
            '',
            '2. After running the SQL, refresh this page to verify',
            '3. Then try accessing /admin/ again',
        ]
    ];
} elseif (!$debug['logged_in']) {
    $debug['solution'] = [
        'problem' => 'You are not logged in',
        'next_steps' => [
            '1. Go to the website homepage',
            '2. Click the login button',
            '3. Complete TeamSpeak verification',
            '4. Return to this page',
        ]
    ];
} else {
    $debug['solution'] = [
        'status' => 'SUCCESS',
        'message' => 'You have admin access! You can access /admin/ now.',
    ];
}

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
