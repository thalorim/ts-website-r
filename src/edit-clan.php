<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/private/php/load.php";

if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to edit clan settings.");
    exit;
}

$groupId = isset($_GET["groupid"]) ? (int) $_GET["groupid"] : 0;

if ($groupId <= 0) {
    TemplateUtils::i()->renderErrorTemplate("404", "Not Found", "Invalid group ID");
    exit;
}

$db = DatabaseUtils::i()->getDb();
$dbConfig = Config::i()->getDatabaseConfig();
$prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
$rawTableName = $prefix . "clan_groups";

// Ensure clan_groups table exists
try {
    $existsStmt = $db->query("SHOW TABLES LIKE '" . addslashes($rawTableName) . "'");
    $exists = $existsStmt && $existsStmt->fetchColumn();

    if (!$exists) {
        $createSql = "CREATE TABLE IF NOT EXISTS `{$rawTableName}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `group_id` INT(11) NOT NULL,
            `clan_name` VARCHAR(255) DEFAULT NULL,
            `clan_description` TEXT NULL,
            `clan_avatar` VARCHAR(255) DEFAULT NULL,
            `editor_cldbids` TEXT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_group_id` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $db->query($createSql);
    } else {
        // Add editor_cldbids column if it doesn't exist
        try {
            $columnsStmt = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'editor_cldbids'");
            $columnExists = $columnsStmt && $columnsStmt->fetchColumn();
            if (!$columnExists) {
                $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `editor_cldbids` TEXT NULL AFTER `clan_avatar`");
            }
        } catch (\Exception $e) {
            // Non-fatal
        }
    }
} catch (\Exception $e) {
    TemplateUtils::i()->renderErrorTemplate("DB error", "Failed ensuring clan_groups table", $e->getMessage());
    exit;
}

// Check if this clan group exists in the database
$clanGroupData = $db->get("clan_groups", "*", ["group_id" => $groupId]);
if (!$clanGroupData) {
    TemplateUtils::i()->renderErrorTemplate("404", "Not Found", "Clan page not found for this group. Please ask an admin to set it up first.");
    exit;
}

// Check if user has permission to edit this clan page
$userCldbid = Auth::getCldbid();
$editorCldbids = [];
if (!empty($clanGroupData['editor_cldbids'])) {
    $editorCldbids = array_map('intval', array_filter(explode(',', $clanGroupData['editor_cldbids']), function($v) {
        return trim($v) !== '';
    }));
}

if (!in_array($userCldbid, $editorCldbids)) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You do not have permission to edit this clan page. Contact an admin if you need access.");
    exit;
}

$message = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $updateData = [];

        // Handle clan name
        if (isset($_POST["clan_name"])) {
            $clanName = trim((string) $_POST["clan_name"]);
            $updateData["clan_name"] = $clanName !== "" ? $clanName : "Clan Group 19";
        }

        // Handle clan description
        if (isset($_POST["clan_description"])) {
            $desc = trim((string) $_POST["clan_description"]);
            $updateData["clan_description"] = $desc !== "" ? $desc : null;
        }

        // Handle clan avatar (optional)
        if (isset($_FILES["clan_avatar"]) && $_FILES["clan_avatar"]["error"] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES["clan_avatar"];
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
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];
            if (!isset($allowed[$mime])) {
                throw new \Exception("Invalid image type. Allowed: PNG, JPG, WEBP, GIF");
            }

            $clansDir = __BASE_DIR . "/img/clans";
            if (!is_dir($clansDir)) {
                @mkdir($clansDir, 0775, true);
            }
            if (!is_dir($clansDir) || !is_writable($clansDir)) {
                throw new \Exception("Clan directory is not writable: " . $clansDir);
            }

            $ext = $allowed[$mime];
            $filename = sprintf('clan_%d_%d.%s', $groupId, time(), $ext);
            $targetFsPath = $clansDir . "/" . $filename;
            $publicPath = "img/clans/" . $filename;

            if (!move_uploaded_file($file["tmp_name"], $targetFsPath)) {
                throw new \Exception("Failed to save uploaded file");
            }

            $updateData["clan_avatar"] = $publicPath;
        }

        // Persist changes
        if ($db->has("clan_groups", ["group_id" => $groupId])) {
            $db->update("clan_groups", $updateData, ["group_id" => $groupId]);
        } else {
            $db->insert("clan_groups", ["group_id" => $groupId] + $updateData);
        }

        $message = "Clan settings updated successfully";
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch current data to display in form
$current = $db->get("clan_groups", "*", ["group_id" => $groupId]);
$currentName = $current && isset($current["clan_name"]) ? $current["clan_name"] : "Clan Group 19";
$currentDescription = $current && isset($current["clan_description"]) ? $current["clan_description"] : null;
$currentAvatar = $current && isset($current["clan_avatar"]) && $current["clan_avatar"] ? $current["clan_avatar"] : "img/icons/defaulticon-128.png";

TemplateUtils::i()->renderTemplate("edit-clan", [
    "title" => "Edit Clan Settings",
    "navActiveIndex" => 0,
    "groupId" => $groupId,
    "currentName" => $currentName,
    "currentDescription" => $currentDescription,
    "currentAvatar" => $currentAvatar,
    "message" => $message,
    "error" => $error,
]);
