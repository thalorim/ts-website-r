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

// Ensure avatar_url and socials_json columns exist
try {
    $colStmt = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'avatar_url'");
    $colExists = $colStmt && $colStmt->fetchColumn();
    if (!$colExists) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `avatar_url` VARCHAR(255) NULL AFTER `servergroups`");
    }

    $colStmt2 = $db->query("SHOW COLUMNS FROM `{$rawTableName}` LIKE 'socials_json'");
    $colExists2 = $colStmt2 && $colStmt2->fetchColumn();
    if (!$colExists2) {
        $db->query("ALTER TABLE `{$rawTableName}` ADD COLUMN `socials_json` TEXT NULL AFTER `avatar_url`");
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

// Fetch current avatar to display in form
$current = $db->get("profiles", ["avatar_url","socials_json"], ["cldbid" => $requestedCldbid]);
$currentAvatar = $current && isset($current["avatar_url"]) && $current["avatar_url"] ? $current["avatar_url"] : "img/icons/defaulticon-128.png";
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
    "currentSocials" => $currentSocials,
    "message" => $message,
    "error" => $error,
]);

