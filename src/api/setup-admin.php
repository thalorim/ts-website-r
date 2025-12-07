<?php
/**
 * One-Time Admin Setup Script
 * 
 * SECURITY WARNING: Delete this file after use!
 * 
 * This script allows you to grant admin access to yourself if you're logged in.
 * After running this once, DELETE IT for security.
 */

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

// Check if already configured
$existingAdmins = Config::get("admin_uids", []);
$existingCldbids = Config::get("admin_cldbids", []);
$existingGroups = Config::get("admin_groups", []);

$hasAdminConfig = !empty($existingAdmins) || !empty($existingCldbids) || !empty($existingGroups);

if ($hasAdminConfig && !Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode([
        "ok" => false,
        "error" => "Admin configuration already exists. You don't have access.",
        "message" => "For security reasons, this setup script only works when there are NO admins configured, or if you already have admin access.",
        "action" => "Contact your system administrator or use the database SQL method to add yourself as admin.",
    ], JSON_PRETTY_PRINT);
    exit;
}

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "error" => "You must be logged in to use this setup script",
        "action" => "Log in to the website first, then visit this page again.",
    ], JSON_PRETTY_PRINT);
    exit;
}

// Get current user info
$uid = Auth::getUid();
$cldbid = Auth::getCldbid();
$nickname = Auth::getNickname();

// If they're already an admin, just show status
if (Auth::isAdmin()) {
    echo json_encode([
        "ok" => true,
        "already_admin" => true,
        "message" => "You already have admin access!",
        "user" => [
            "uid" => $uid,
            "cldbid" => $cldbid,
            "nickname" => $nickname,
        ],
        "action" => "You can access /admin/ now. Remember to DELETE this setup-admin.php file!",
    ], JSON_PRETTY_PRINT);
    exit;
}

// Grant admin access by adding to admin_uids
try {
    $currentAdminUids = Config::get("admin_uids", []);
    if (!is_array($currentAdminUids)) {
        $currentAdminUids = [];
    }
    
    // Add current user's UID if not already present
    if (!in_array($uid, $currentAdminUids, true)) {
        $currentAdminUids[] = $uid;
    }
    
    Config::i()->setValue("admin_uids", $currentAdminUids);
    
    echo json_encode([
        "ok" => true,
        "message" => "Admin access granted successfully!",
        "user" => [
            "uid" => $uid,
            "cldbid" => $cldbid,
            "nickname" => $nickname,
        ],
        "config_updated" => [
            "admin_uids" => $currentAdminUids,
        ],
        "next_steps" => [
            "1. Visit /admin/ to access the admin panel",
            "2. IMPORTANT: Delete this setup-admin.php file for security!",
            "3. You can now add other admins through the admin panel or configuration",
        ],
        "security_warning" => "DELETE /src/api/setup-admin.php NOW!"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage(),
        "message" => "Failed to grant admin access. You may need to add configuration manually via database.",
    ], JSON_PRETTY_PRINT);
}
