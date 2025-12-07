<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\AvatarBorderUtils;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/private/php/load.php";

if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to edit your profile.");
    exit;
}

$requestedCldbid = isset($_GET["cldbid"]) ? (int) $_GET["cldbid"] : Auth::getCldbid();
if ($requestedCldbid !== Auth::getCldbid()) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You can only edit your own profile.");
    exit;
}

$db = DatabaseUtils::i()->getDb();
$dbConfig = Config::i()->getDatabaseConfig();
$prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
$rawTableName = $prefix . "profiles";

// Ensure avatar/border/social/banner/description columns exist
try {
    $colStmt = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'avatar_url'");
    $colExists = $colStmt && $colStmt->fetchColumn();
    if (!$colExists) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `avatar_url` VARCHAR(255) NULL AFTER `servergroups`");
    }

    $colStmtBorder = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'avatar_border'");
    $colExistsBorder = $colStmtBorder && $colStmtBorder->fetchColumn();
    if (!$colExistsBorder) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `avatar_border` VARCHAR(64) NULL AFTER `avatar_url`");
    }

    $colStmt2 = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'socials_json'");
    $colExists2 = $colStmt2 && $colStmt2->fetchColumn();
    if (!$colExists2) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `socials_json` TEXT NULL AFTER `avatar_border`");
    }

    $colStmt3 = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'banner_url'");
    $colExists3 = $colStmt3 && $colStmt3->fetchColumn();
    if (!$colExists3) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `banner_url` VARCHAR(255) NULL AFTER `socials_json`");
    }

    $colStmt4 = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'description'");
    $colExists4 = $colStmt4 && $colStmt4->fetchColumn();
    if (!$colExists4) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `description` TEXT NULL AFTER `banner_url`");
    }

    $colStmt5 = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'steam_id'");
    $colExists5 = $colStmt5 && $colStmt5->fetchColumn();
    if (!$colExists5) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `steam_id` VARCHAR(255) NULL AFTER `description`");
    }
} catch (\Exception $e) {
    TemplateUtils::i()->renderErrorTemplate("DB error", "Failed ensuring avatar column", $e->getMessage());
    exit;
}

$message = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $updateData = [];
        $changes = []; // Track what changed for webhook

        // Get current data for comparison
        $currentData = $db->get("profiles", ["avatar_url","socials_json","steam_id","description","banner_url"], ["cldbid" => $requestedCldbid]);

        // Handle socials
        $socialKeys = ["instagram","facebook","youtube","twitter","steam","soundcloud","github","telegram","twitch","discord"];
        $socials = [];
        $oldSocials = [];
        
        if ($currentData && !empty($currentData["socials_json"])) {
            $oldSocials = json_decode((string) $currentData["socials_json"], true) ?: [];
        }
        
        foreach ($socialKeys as $key) {
            $val = isset($_POST[$key]) ? trim((string) $_POST[$key]) : "";
            if ($val !== "") {
                $socials[$key] = $val;
            }
        }
        
        // Check if socials changed
        if ($socials !== $oldSocials) {
            $changes[] = 'social_links';
        }
        
        if (!empty($socials)) {
            $updateData["socials_json"] = json_encode($socials);
        } else {
            $updateData["socials_json"] = null;
        }

        // Handle avatar (optional)
        if (isset($_FILES["avatar"]) && $_FILES["avatar"]["error"] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES["avatar"];
            if ($file["error"] !== UPLOAD_ERR_OK) {
                throw new \Exception("Upload error: " . $file["error"]);
            }

            if ($file["size"] > 2 * 1024 * 1024) {
                throw new \Exception("File too large. Max 2MB.");
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file["tmp_name"]);
            $allowed = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp'
            ];
            if (!isset($allowed[$mime])) {
                throw new \Exception("Invalid image type. Allowed: PNG, JPG, WEBP");
            }

            $avatarsDir = __BASE_DIR . "/img/avatars";
            if (!is_dir($avatarsDir)) {
                @mkdir($avatarsDir, 0775, true);
            }
            if (!is_dir($avatarsDir) || !is_writable($avatarsDir)) {
                throw new \Exception("Avatar directory is not writable: " . $avatarsDir);
            }

            $ext = $allowed[$mime];
            $filename = sprintf('%d_%d.%s', $requestedCldbid, time(), $ext);
            $targetFsPath = $avatarsDir . "/" . $filename;
            $publicPath = "img/avatars/" . $filename;

            if (!move_uploaded_file($file["tmp_name"], $targetFsPath)) {
                throw new \Exception("Failed to save uploaded file");
            }

            $updateData["avatar_url"] = $publicPath;
            $changes[] = 'avatar';
        }

        // Handle banner (optional)
        if (isset($_FILES["banner"]) && $_FILES["banner"]["error"] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES["banner"];
            if ($file["error"] !== UPLOAD_ERR_OK) {
                throw new \Exception("Banner upload error: " . $file["error"]);
            }

            if ($file["size"] > 5 * 1024 * 1024) {
                throw new \Exception("Banner too large. Max 5MB.");
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file["tmp_name"]);
            $allowed = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];
            if (!isset($allowed[$mime])) {
                throw new \Exception("Invalid banner image type. Allowed: PNG, JPG, WEBP, GIF");
            }

            $bannersDir = __BASE_DIR . "/img/banners";
            if (!is_dir($bannersDir)) {
                @mkdir($bannersDir, 0775, true);
            }
            if (!is_dir($bannersDir) || !is_writable($bannersDir)) {
                throw new \Exception("Banner directory is not writable: " . $bannersDir);
            }

            $ext = $allowed[$mime];
            $filename = sprintf('%d_%d.%s', $requestedCldbid, time(), $ext);
            $targetFsPath = $bannersDir . "/" . $filename;
            $publicPath = "img/banners/" . $filename;

            if (!move_uploaded_file($file["tmp_name"], $targetFsPath)) {
                throw new \Exception("Failed to save uploaded banner");
            }

            $updateData["banner_url"] = $publicPath;
            $changes[] = 'banner';
        }

        // Handle description (optional)
        if (isset($_POST["description"])) {
            $desc = trim((string) $_POST["description"]);
            $updateData["description"] = $desc !== "" ? $desc : null;
        }

        // Handle Steam ID (optional)
        if (isset($_POST["steam_id"])) {
            $steamId = trim((string) $_POST["steam_id"]);
            $oldSteamId = $currentData && isset($currentData["steam_id"]) ? $currentData["steam_id"] : null;
            
            if ($steamId !== $oldSteamId) {
                if ($steamId !== "" && $oldSteamId === null) {
                    $changes[] = 'steam_added';
                } else if ($steamId !== "" && $oldSteamId !== null) {
                    $changes[] = 'steam_changed';
                }
            }
            
            $updateData["steam_id"] = $steamId !== "" ? $steamId : null;
        }

        $selectedBorder = isset($_POST["avatar_border"]) ? trim((string) $_POST["avatar_border"]) : null;
        $updateData["avatar_border"] = AvatarBorderUtils::normalize($selectedBorder);

        // Persist changes
        if ($db->has("profiles", ["cldbid" => $requestedCldbid])) {
            $db->update("profiles", $updateData, ["cldbid" => $requestedCldbid]);
        } else {
            $db->insert("profiles", ["cldbid" => $requestedCldbid] + $updateData);
        }

        // Send Discord webhook for profile updates
        if (!empty($changes)) {
            sendProfileUpdateWebhook($requestedCldbid, $changes, $updateData);
        }

        $message = "Profile updated";
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch current data to display in form
$current = $db->get("profiles", ["avatar_url","avatar_border","socials_json","banner_url","description","steam_id"], ["cldbid" => $requestedCldbid]);
$currentAvatar = $current && isset($current["avatar_url"]) && $current["avatar_url"] ? $current["avatar_url"] : "img/icons/defaulticon-128.png";
$currentAvatarBorder = AvatarBorderUtils::normalize($current["avatar_border"] ?? null);
$currentAvatarBorderUrl = AvatarBorderUtils::getUrl($currentAvatarBorder);
$currentDescription = $current && isset($current["description"]) ? $current["description"] : null;
$currentSteamId = $current && isset($current["steam_id"]) ? $current["steam_id"] : null;
$currentSocials = [];
if ($current && !empty($current["socials_json"])) {
    $decoded = json_decode((string) $current["socials_json"], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $currentSocials = $decoded;
    }
}

/**
 * Sends Discord webhook for profile updates
 */
function sendProfileUpdateWebhook(int $cldbid, array $changes, array $updateData): void {
    $webhook = (string) Config::get("discord_login_webhook", "");
    if ($webhook === "") {
        return;
    }

    $nickname = Auth::getNickname() ?: ("User #" . $cldbid);
    $timestamp = date('c');
    
    $changeDescriptions = [
        'avatar' => '🖼️ Updated avatar',
        'banner' => '🎨 Updated profile banner',
        'social_links' => '🔗 Modified social links',
        'steam_added' => '🎮 Linked Steam account',
        'steam_changed' => '🎮 Updated Steam ID',
    ];
    
    $changesList = [];
    foreach ($changes as $change) {
        if (isset($changeDescriptions[$change])) {
            $changesList[] = $changeDescriptions[$change];
        }
    }
    
    $description = '**' . $nickname . '** updated their profile.';
    $fields = [
        ['name' => '👤 User', 'value' => $nickname, 'inline' => true],
        ['name' => '🆔 CLDBID', 'value' => (string) $cldbid, 'inline' => true],
        ['name' => '📝 Changes Made', 'value' => implode("\n", $changesList), 'inline' => false],
    ];
    
    // Add Steam ID if it was added/changed
    if (in_array('steam_added', $changes) || in_array('steam_changed', $changes)) {
        if (isset($updateData['steam_id']) && $updateData['steam_id']) {
            $fields[] = ['name' => '🎮 Steam ID', 'value' => '`' . $updateData['steam_id'] . '`', 'inline' => false];
        }
    }
    
    // Add social links info if changed
    if (in_array('social_links', $changes) && isset($updateData['socials_json'])) {
        $socials = json_decode($updateData['socials_json'], true);
        if ($socials && !empty($socials)) {
            $socialList = [];
            foreach ($socials as $key => $value) {
                $socialList[] = ucfirst($key);
            }
            $fields[] = ['name' => '🔗 Social Platforms', 'value' => implode(', ', $socialList), 'inline' => false];
        }
    }

    $embed = [
        'title' => '✏️ Profile Updated',
        'description' => $description,
        'color' => 0x9b59b6, // Purple
        'fields' => $fields,
        'footer' => [
            'text' => 'Profile Editor',
            'icon_url' => 'https://cdn-icons-png.flaticon.com/512/1077/1077114.png'
        ],
        'timestamp' => $timestamp,
    ];

    $payload = json_encode([
        'username' => 'TS-Website Profiles',
        'avatar_url' => 'https://cdn-icons-png.flaticon.com/512/1077/1077114.png',
        'embeds' => [$embed],
    ]);

    try {
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 3,
            ],
        ];
        @file_get_contents($webhook, false, stream_context_create($opts));
    } catch (\Exception $e) {
        // ignore webhook errors
    }
}

TemplateUtils::i()->renderTemplate("edit-profile", [
    "title" => "Edit profile",
    "navActiveIndex" => 0,
    "cldbid" => $requestedCldbid,
    "currentAvatar" => $currentAvatar,
    "currentAvatarBorder" => $currentAvatarBorder,
    "currentAvatarBorderUrl" => $currentAvatarBorderUrl,
    "borderOptions" => AvatarBorderUtils::getOptions(),
    "currentDescription" => $currentDescription,
    "currentSteamId" => $currentSteamId,
    "currentSocials" => $currentSocials,
    "message" => $message,
    "error" => $error,
]);

