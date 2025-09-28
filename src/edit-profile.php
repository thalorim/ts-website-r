<?php

use Wruczek\TSWebsite\Auth;
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

// Ensure avatar_url column exists
try {
    $colStmt = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'avatar_url'");
    $colExists = $colStmt && $colStmt->fetchColumn();
    if (!$colExists) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `avatar_url` VARCHAR(255) NULL AFTER `servergroups`");
    }
} catch (\Exception $e) {
    TemplateUtils::i()->renderErrorTemplate("DB error", "Failed ensuring avatar column", $e->getMessage());
    exit;
}

$message = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!isset($_FILES["avatar"]) || $_FILES["avatar"]["error"] === UPLOAD_ERR_NO_FILE) {
            throw new \Exception("No file uploaded");
        }

        $file = $_FILES["avatar"];
        if ($file["error"] !== UPLOAD_ERR_OK) {
            throw new \Exception("Upload error: " . $file["error"]);
        }

        // Validate size (<= 2MB)
        if ($file["size"] > 2 * 1024 * 1024) {
            throw new \Exception("File too large. Max 2MB.");
        }

        // Validate mime
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

        // Ensure directory exists
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

        // Persist url
        if ($db->has("profiles", ["cldbid" => $requestedCldbid])) {
            $db->update("profiles", ["avatar_url" => $publicPath], ["cldbid" => $requestedCldbid]);
        } else {
            $db->insert("profiles", ["cldbid" => $requestedCldbid, "avatar_url" => $publicPath]);
        }

        $message = "Avatar updated";
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch current avatar to display in form
$current = $db->get("profiles", ["avatar_url"], ["cldbid" => $requestedCldbid]);
$currentAvatar = $current && isset($current["avatar_url"]) && $current["avatar_url"] ? $current["avatar_url"] : "img/icons/defaulticon-128.png";

TemplateUtils::i()->renderTemplate("edit-profile", [
    "title" => "Edit profile",
    "navActiveIndex" => 0,
    "cldbid" => $requestedCldbid,
    "currentAvatar" => $currentAvatar,
    "message" => $message,
    "error" => $error,
]);

