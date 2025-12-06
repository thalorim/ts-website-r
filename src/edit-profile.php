<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/private/php/load.php";

if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to edit your profile.");
    exit;
}

$currentUserCldbid = Auth::getCldbid();
$requestedCldbid = isset($_GET["cldbid"]) ? (int) $_GET["cldbid"] : $currentUserCldbid;
if ($requestedCldbid !== $currentUserCldbid) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You can only edit your own profile.");
    exit;
}

$db = DatabaseUtils::i()->getDb();
$dbConfig = Config::i()->getDatabaseConfig();
$prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
$rawTableName = $prefix . "profiles";

// Ensure avatar_url, banner_url and socials_json, description columns exist
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
} catch (\Exception $e) {
    TemplateUtils::i()->renderErrorTemplate("DB error", "Failed ensuring avatar column", $e->getMessage());
    exit;
}

$fallbackAdminCldbid = 3;
$configuredAdmins = Config::get("connectivity_config_cldbids");
$defaultAdminList = [$fallbackAdminCldbid];
$connectivityAdmins = [];

if (is_array($configuredAdmins)) {
    foreach ($configuredAdmins as $id) {
        $id = (int) $id;
        if ($id > 0 && !in_array($id, $connectivityAdmins, true)) {
            $connectivityAdmins[] = $id;
        }
    }
}

if (empty($connectivityAdmins)) {
    $connectivityAdmins = $defaultAdminList;
}

if (!in_array($fallbackAdminCldbid, $connectivityAdmins, true)) {
    $connectivityAdmins[] = $fallbackAdminCldbid;
}

$canManageConnectivity = in_array($currentUserCldbid, $connectivityAdmins, true);

$connectivityDefaults = [
    "query_hostname" => (string) (Config::get("query_hostname") ?? ""),
    "query_displayip" => (string) (Config::get("query_displayip") ?? ""),
    "query_port" => (string) (Config::get("query_port") ?? ""),
    "tsserver_port" => (string) (Config::get("tsserver_port") ?? ""),
    "query_username" => (string) (Config::get("query_username") ?? ""),
    "query_nickname" => (string) (Config::get("query_nickname") ?? ""),
];
$connectivityAdminsInput = implode(", ", $connectivityAdmins);

$message = null;
$error = null;
$connectivityMessage = null;
$connectivityError = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formType = isset($_POST["form"]) ? (string) $_POST["form"] : "profile";

    if ($formType === "profile") {
        try {
            $updateData = [];

            // Handle socials
            $socialKeys = ["instagram","facebook","youtube","twitter","steam","soundcloud","github","telegram","twitch","discord"];
            $socials = [];
            foreach ($socialKeys as $key) {
                $val = isset($_POST[$key]) ? trim((string) $_POST[$key]) : "";
                if ($val === "") { continue; }

                if ($key === 'discord') {
                    // Accept only numeric Discord user IDs; reject invite links or URLs
                    if (preg_match('/^https?:/i', $val) || stripos($val, 'discord.gg') !== false) {
                        throw new \Exception("Discord must be a numeric user ID, not a link.");
                    }
                    if (!preg_match('/^[0-9]{15,25}$/', $val)) {
                        throw new \Exception("Invalid Discord user ID. Use numbers only (15-25 digits).");
                    }
                    $socials[$key] = $val; // store raw ID
                } else {
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
    } elseif ($formType === "connectivity") {
        if (!$canManageConnectivity) {
            $connectivityError = "You are not allowed to update connectivity settings.";
        } else {
            try {
                $hostname = trim((string) ($_POST["query_hostname"] ?? ""));
                $displayIp = trim((string) ($_POST["query_displayip"] ?? ""));
                $username = trim((string) ($_POST["query_username"] ?? ""));
                $queryPortRaw = (string) ($_POST["query_port"] ?? "");
                $serverPortRaw = (string) ($_POST["tsserver_port"] ?? "");
                $queryNickname = trim((string) ($_POST["query_nickname"] ?? ""));
                $queryPassword = (string) ($_POST["query_password"] ?? "");
                $adminsField = trim((string) ($_POST["connectivity_admins"] ?? ""));

                $connectivityDefaults["query_hostname"] = $hostname;
                $connectivityDefaults["query_displayip"] = $displayIp;
                $connectivityDefaults["query_username"] = $username;
                $connectivityDefaults["query_port"] = $queryPortRaw;
                $connectivityDefaults["tsserver_port"] = $serverPortRaw;
                $connectivityDefaults["query_nickname"] = $queryNickname;
                $connectivityAdminsInput = $adminsField;

                $requiredStrings = [
                    "Query hostname/IP" => $hostname,
                    "Displayed address" => $displayIp,
                    "Query username" => $username,
                ];

                foreach ($requiredStrings as $label => $value) {
                    if ($value === "") {
                        throw new \Exception($label . " is required.");
                    }
                }

                $queryPortInt = filter_var($queryPortRaw, FILTER_VALIDATE_INT, [
                    "options" => ["min_range" => 1, "max_range" => 65535]
                ]);
                if ($queryPortInt === false) {
                    throw new \Exception("Query port must be a number between 1 and 65535.");
                }

                $serverPortInt = filter_var($serverPortRaw, FILTER_VALIDATE_INT, [
                    "options" => ["min_range" => 1, "max_range" => 65535]
                ]);
                if ($serverPortInt === false) {
                    throw new \Exception("Server port must be a number between 1 and 65535.");
                }

                if ($adminsField === "") {
                    throw new \Exception("Please provide at least one CLDBID allowed to manage connectivity.");
                }

                $adminParts = preg_split('/[,\s]+/', $adminsField, -1, PREG_SPLIT_NO_EMPTY);
                if (!$adminParts) {
                    throw new \Exception("Please provide at least one valid CLDBID.");
                }

                $newAdmins = [];
                foreach ($adminParts as $part) {
                    $part = trim($part);
                    if ($part === "") {
                        continue;
                    }
                    if (!ctype_digit($part)) {
                        throw new \Exception("CLDBIDs must contain numbers only.");
                    }
                    $id = (int) $part;
                    if ($id <= 0) {
                        throw new \Exception("CLDBIDs must be positive numbers.");
                    }
                    if (!in_array($id, $newAdmins, true)) {
                        $newAdmins[] = $id;
                    }
                }

                if (empty($newAdmins)) {
                    throw new \Exception("Please provide at least one valid CLDBID.");
                }

                if (!in_array($fallbackAdminCldbid, $newAdmins, true)) {
                    $newAdmins[] = $fallbackAdminCldbid;
                }

                $configInstance = Config::i();
                $configInstance->setValue("query_hostname", $hostname);
                $configInstance->setValue("query_displayip", $displayIp);
                $configInstance->setValue("query_port", (int) $queryPortInt);
                $configInstance->setValue("tsserver_port", (int) $serverPortInt);
                $configInstance->setValue("query_username", $username);
                $configInstance->setValue("query_nickname", $queryNickname);
                $configInstance->setValue("connectivity_config_cldbids", $newAdmins);

                if ($queryPassword !== "") {
                    $configInstance->setValue("query_password", $queryPassword);
                }

                TeamSpeakUtils::i()->reset();

                $connectivityAdmins = $newAdmins;
                $connectivityAdminsInput = implode(", ", $connectivityAdmins);
                $connectivityDefaults["query_port"] = (string) $queryPortInt;
                $connectivityDefaults["tsserver_port"] = (string) $serverPortInt;

                $connectivityMessage = "Connectivity settings updated.";
            } catch (\Exception $e) {
                $connectivityError = $e->getMessage();
            }
        }
    }
}

// Fetch current avatar to display in form
$current = $db->get("profiles", ["avatar_url","socials_json","banner_url","description"], ["cldbid" => $requestedCldbid]);
$currentAvatar = $current && isset($current["avatar_url"]) && $current["avatar_url"] ? $current["avatar_url"] : "img/icons/defaulticon-128.png";
$currentDescription = $current && isset($current["description"]) ? $current["description"] : null;
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
    "currentDescription" => $currentDescription,
    "currentSocials" => $currentSocials,
    "message" => $message,
    "error" => $error,
    "canManageConnectivity" => $canManageConnectivity,
    "connectivityMessage" => $connectivityMessage,
    "connectivityError" => $connectivityError,
    "connectivityFormDefaults" => $connectivityDefaults,
    "connectivityAdminsInput" => $connectivityAdminsInput,
]);

