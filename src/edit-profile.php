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

// Ensure userbar_groups table exists
$userbarGroupsTable = $prefix . "userbar_groups";
try {
    $existsStmt = $db->query("SHOW TABLES LIKE '" . addslashes($userbarGroupsTable) . "'");
    $exists = $existsStmt && $existsStmt->fetchColumn();

    if (!$exists) {
        $createSql = "CREATE TABLE IF NOT EXISTS `{$userbarGroupsTable}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `sgid` INT(11) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_sgid` (`sgid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $db->query($createSql);
        
        // Insert default allowed groups (532 and 556)
        $db->insert($userbarGroupsTable, ["sgid" => 532]);
        $db->insert($userbarGroupsTable, ["sgid" => 556]);
    }
} catch (\Exception $e) {
    TemplateUtils::i()->renderErrorTemplate("DB error", "Failed ensuring userbar_groups table", $e->getMessage());
    exit;
}

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

    $colStmt5 = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'userbar_url'");
    $colExists5 = $colStmt5 && $colStmt5->fetchColumn();
    if (!$colExists5) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `userbar_url` VARCHAR(512) NULL AFTER `description`");
    }

    $colStmt6 = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'discord_id'");
    $colExists6 = $colStmt6 && $colStmt6->fetchColumn();
    if (!$colExists6) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `discord_id` VARCHAR(64) NULL AFTER `userbar_url`");
    }
} catch (\Exception $e) {
    TemplateUtils::i()->renderErrorTemplate("DB error", "Failed ensuring avatar column", $e->getMessage());
    exit;
}

// Fetch user's current server groups to check permissions
$userServerGroups = [];
$currentDbProfile = $db->get("profiles", ["servergroups"], ["cldbid" => $requestedCldbid]);
if ($currentDbProfile && !empty($currentDbProfile["servergroups"])) {
    foreach (explode(',', (string) $currentDbProfile["servergroups"]) as $id) {
        $id = (int) trim($id);
        if ($id > 0) $userServerGroups[] = $id;
    }
}

// Fetch allowed userbar groups from database
$allowedUserbarGroups = [];
try {
    $allowedRows = $db->select($userbarGroupsTable, ["sgid"]);
    foreach ($allowedRows as $row) {
        $allowedUserbarGroups[] = (int) $row["sgid"];
    }
} catch (\Exception $e) {
    // ignore
}

// Check if user can edit userbar
$canEditUserbar = !empty(array_intersect($userServerGroups, $allowedUserbarGroups));

$message = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $updateData = [];

        // Handle socials
        $socialKeys = ["instagram","facebook","youtube","twitter","steam","soundcloud","github","telegram","twitch","discord"];
        $socials = [];
        foreach ($socialKeys as $key) {
            $val = isset($_POST[$key]) ? trim((string) $_POST[$key]) : "";
            if ($val !== "") {
                $socials[$key] = $val;
            }
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
        }

        // Handle description (optional)
        if (isset($_POST["description"])) {
            $desc = trim((string) $_POST["description"]);
            $updateData["description"] = $desc !== "" ? $desc : null;
        }

        // Handle userbar URL (only if user has permission)
        if ($canEditUserbar && isset($_POST["userbar_url"])) {
            $userbarUrl = trim((string) $_POST["userbar_url"]);
            $updateData["userbar_url"] = $userbarUrl !== "" ? $userbarUrl : null;
        }

        // Handle Discord ID
        if (isset($_POST["discord_id"])) {
            $discordIdInput = trim((string) $_POST["discord_id"]);
            
            if ($discordIdInput === "") {
                // Empty = remove Discord ID
                $updateData["discord_id"] = null;
            } else {
                // Try to extract Discord ID from input (supports both raw ID and URL)
                $discordId = null;
                
                // Check if it's a URL
                if (preg_match('#discord\.com/users/(\d{17,19})#', $discordIdInput, $matches)) {
                    $discordId = $matches[1];
                } else if (preg_match('/^\d{17,19}$/', $discordIdInput)) {
                    // Direct ID input
                    $discordId = $discordIdInput;
                }
                
                if ($discordId) {
                    $updateData["discord_id"] = $discordId;
                } else {
                    throw new \Exception("Invalid Discord ID format. Please enter either your Discord User ID (17-19 digits) or your Discord profile URL.");
                }
            }
        }

        $selectedBorder = isset($_POST["avatar_border"]) ? trim((string) $_POST["avatar_border"]) : null;
        $updateData["avatar_border"] = AvatarBorderUtils::normalize($selectedBorder);

        // Persist changes
        if ($db->has("profiles", ["cldbid" => $requestedCldbid])) {
            $db->update("profiles", $updateData, ["cldbid" => $requestedCldbid]);
        } else {
            $db->insert("profiles", ["cldbid" => $requestedCldbid] + $updateData);
        }

        $message = "Profile updated";
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch current data to display in form
$current = $db->get("profiles", ["avatar_url","avatar_border","socials_json","banner_url","description","userbar_url","discord_id"], ["cldbid" => $requestedCldbid]);
$currentAvatar = $current && isset($current["avatar_url"]) && $current["avatar_url"] ? $current["avatar_url"] : "img/icons/defaulticon-128.png";
$currentAvatarBorder = AvatarBorderUtils::normalize($current["avatar_border"] ?? null);
$currentAvatarBorderUrl = AvatarBorderUtils::getUrl($currentAvatarBorder);
$currentDescription = $current && isset($current["description"]) ? $current["description"] : null;
$currentUserbarUrl = $current && isset($current["userbar_url"]) ? $current["userbar_url"] : null;
$currentDiscordId = $current && isset($current["discord_id"]) ? $current["discord_id"] : null;
$currentSocials = [];
if ($current && !empty($current["socials_json"])) {
    $decoded = json_decode((string) $current["socials_json"], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $currentSocials = $decoded;
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
    "currentSocials" => $currentSocials,
    "currentUserbarUrl" => $currentUserbarUrl,
    "currentDiscordId" => $currentDiscordId,
    "canEditUserbar" => $canEditUserbar,
    "message" => $message,
    "error" => $error,
]);

