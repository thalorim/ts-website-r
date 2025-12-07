<?php
/**
 * Whoami API Endpoint
 * 
 * Returns information about the currently logged-in user.
 * Useful for finding your UID and CLDBID to configure admin access.
 */

use Wruczek\TSWebsite\Auth;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "logged_in" => false,
        "message" => "You must be logged in to use this endpoint"
    ]);
    exit;
}

try {
    $uid = Auth::getUid();
    $cldbid = Auth::getCldbid();
    $nickname = Auth::getNickname();
    $isAdmin = Auth::isAdmin();
    
    // Try to get user's server groups
    $serverGroups = [];
    try {
        $serverGroups = Auth::getUserServerGroupIds();
    } catch (\Exception $e) {
        // Ignore if we can't get server groups
    }

    echo json_encode([
        "ok" => true,
        "logged_in" => true,
        "is_admin" => $isAdmin,
        "user" => [
            "uid" => $uid,
            "cldbid" => $cldbid,
            "nickname" => $nickname,
            "server_groups" => $serverGroups
        ],
        "message" => $isAdmin 
            ? "You have admin access" 
            : "You do not have admin access. Add your UID, CLDBID, or server group to the admin configuration.",
        "configuration_example" => [
            "admin_uids" => [$uid],
            "admin_cldbids" => [$cldbid],
            "admin_groups" => $serverGroups
        ]
    ], JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage()
    ]);
}
