<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;

require_once __DIR__ . "/private/php/load.php";

$cldbid = isset($_GET["cldbid"]) ? (int) $_GET["cldbid"] : 0;

if ($cldbid <= 0) {
    TemplateUtils::i()->renderErrorTemplate("400", "Invalid request", "Missing or invalid cldbid");
    exit;
}

// Ensure profiles table exists
$db = DatabaseUtils::i()->getDb();
$dbConfig = Config::i()->getDatabaseConfig();
$prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
$rawTableName = $prefix . "profiles";

try {
    $existsStmt = $db->query("SHOW TABLES LIKE '" . addslashes($rawTableName) . "'");
    $exists = $existsStmt && $existsStmt->fetchColumn();

    if (!$exists) {
        $createSql = "CREATE TABLE IF NOT EXISTS `{$rawTableName}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `cldbid` INT(11) NOT NULL,
            `cluid` VARCHAR(64) DEFAULT NULL,
            `nickname` VARCHAR(128) DEFAULT NULL,
            `description` TEXT NULL,
            `country` VARCHAR(4) DEFAULT NULL,
            `version` VARCHAR(128) DEFAULT NULL,
            `platform` VARCHAR(64) DEFAULT NULL,
            `badges` TEXT NULL,
            `servergroups` TEXT NULL,
            `created_ts` INT(11) DEFAULT NULL,
            `lastconnected_ts` INT(11) DEFAULT NULL,
            `totalconnections` INT(11) DEFAULT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_cldbid` (`cldbid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $db->query($createSql);
    }
} catch (\Exception $e) {
    TemplateUtils::i()->renderErrorTemplate("DB error", "Failed ensuring profiles table", $e->getMessage());
    exit;
}

// Fetch TS data
$onlineClient = CacheManager::i()->getClient($cldbid);
$isOnline = $onlineClient !== null;

$tsInfo = null;
try {
    if (TeamSpeakUtils::i()->checkTSConnection()) {
        // TS3 server query command is "clientdbinfo" -> method name clientDbInfo
        $tsInfo = TeamSpeakUtils::i()->getTSNodeServer()->clientDbInfo($cldbid);
    }
} catch (\Exception $e) {
    // Non-fatal: proceed with what we have
}

// Normalize data
$profileData = [
    "cldbid" => $cldbid,
    "cluid" => null,
    "nickname" => null,
    "description" => null,
    "country" => null,
    "version" => null,
    "platform" => null,
    "badges" => null,
    "servergroups" => null,
    "created_ts" => null,
    "lastconnected_ts" => null,
    "totalconnections" => null,
];

if ($tsInfo) {
    // TeamSpeak3_Helper_String may be returned, cast to string where applicable
    $profileData["cluid"] = isset($tsInfo["client_unique_identifier"]) ? (string) $tsInfo["client_unique_identifier"] : null;
    $profileData["nickname"] = isset($tsInfo["client_nickname"]) ? (string) $tsInfo["client_nickname"] : null;
    // Some frameworks expose only created/lastconnected/total via dbinfo; guard carefully
    $profileData["description"] = isset($tsInfo["client_description"]) ? (string) $tsInfo["client_description"] : null;
    $profileData["created_ts"] = isset($tsInfo["client_created"]) ? (int) $tsInfo["client_created"] : null;
    $profileData["lastconnected_ts"] = isset($tsInfo["client_lastconnected"]) ? (int) $tsInfo["client_lastconnected"] : null;
    $profileData["totalconnections"] = isset($tsInfo["client_totalconnections"]) ? (int) $tsInfo["client_totalconnections"] : null;
}

if ($onlineClient) {
    $profileData["nickname"] = (string) $onlineClient["client_nickname"];
    $profileData["country"] = isset($onlineClient["client_country"]) ? (string) $onlineClient["client_country"] : null;
    $profileData["version"] = isset($onlineClient["client_version"]) ? (string) $onlineClient["client_version"] : null;
    $profileData["platform"] = isset($onlineClient["client_platform"]) ? (string) $onlineClient["client_platform"] : null;
    $profileData["badges"] = isset($onlineClient["client_badges"]) ? (string) $onlineClient["client_badges"] : null;
    $profileData["servergroups"] = isset($onlineClient["client_servergroups"]) ? (string) $onlineClient["client_servergroups"] : null;
    // Enhance with current channel and online since
    $profileData["cid"] = isset($onlineClient["cid"]) ? (int) $onlineClient["cid"] : null;
    $profileData["clid"] = isset($onlineClient["clid"]) ? (int) $onlineClient["clid"] : null;

    // Try to get more live info to compute "online since" timestamp
    try {
        if (TeamSpeakUtils::i()->checkTSConnection() && isset($profileData["clid"])) {
            $live = TeamSpeakUtils::i()->getTSNodeServer()->clientGetById($profileData["clid"])->getInfo(true);
            if (isset($live["connection_connected_time"])) {
                $profileData["online_since_ms"] = (int) $live["connection_connected_time"]; // milliseconds
            }
        }
    } catch (\Exception $e) {
        // ignore
    }
}

// If offline, try to get server groups by dbid as fallback
if (!$isOnline) {
    try {
        if (TeamSpeakUtils::i()->checkTSConnection()) {
            $sgByDb = TeamSpeakUtils::i()->getTSNodeServer()->clientGetServerGroupsByDbid($cldbid);
            if (is_array($sgByDb) && !empty($sgByDb)) {
                $profileData["servergroups"] = implode(",", array_keys($sgByDb));
            }
        }
    } catch (\Exception $e) {
        // ignore
    }
}

// Persist to DB (upsert) - do not overwrite existing values with NULLs
try {
    if ($db->has("profiles", ["cldbid" => $cldbid])) {
        $updateData = array_filter($profileData, function ($v) { return $v !== null; });
        if (!empty($updateData)) {
            $db->update("profiles", $updateData, ["cldbid" => $cldbid]);
        }
    } else {
        $db->insert("profiles", $profileData);
    }
} catch (\Exception $e) {
    // Non-fatal for rendering
}

// Prepare data for template
// Fetch DB-stored fields (e.g., avatar)
$dbProfile = $db->get("profiles", "*", ["cldbid" => $cldbid]);

// Resolve server group details
$groupsDetailed = [];
try {
    $serverGroups = CacheManager::i()->getServerGroupList();
    $sgids = [];
    if (!empty($profileData["servergroups"])) {
        foreach (explode(',', (string) $profileData["servergroups"]) as $id) {
            $id = (int) trim($id);
            if ($id > 0) $sgids[] = $id;
        }
    }
    foreach ($sgids as $sgid) {
        if (isset($serverGroups[$sgid])) {
            $g = $serverGroups[$sgid];
            $groupsDetailed[] = [
                "sgid" => (int) $g["sgid"],
                "name" => (string) $g["name"],
                "iconid" => isset($g["iconid"]) ? (int) $g["iconid"] : null,
            ];
        } else {
            $groupsDetailed[] = ["sgid" => $sgid, "name" => "Group #$sgid", "iconid" => null];
        }
    }
} catch (\Exception $e) {
    // ignore
}

$avatarUrl = ($dbProfile && !empty($dbProfile["avatar_url"])) ? $dbProfile["avatar_url"] : "img/icons/defaulticon-128.png";
$bannerUrl = ($dbProfile && !empty($dbProfile["banner_url"])) ? $dbProfile["banner_url"] : null;
// Prefer user-saved description if present
if ($dbProfile && !empty($dbProfile["description"])) {
    $profileData["description"] = (string) $dbProfile["description"];
}

// Fallback nickname and history data from DB when offline/unknown
if ((empty($profileData["nickname"]) || $profileData["nickname"] === null) && $dbProfile && !empty($dbProfile["nickname"])) {
    $profileData["nickname"] = (string) $dbProfile["nickname"];
}
if ((empty($profileData["servergroups"]) || $profileData["servergroups"] === null) && $dbProfile && !empty($dbProfile["servergroups"])) {
    $profileData["servergroups"] = (string) $dbProfile["servergroups"];
}
foreach (["created_ts", "lastconnected_ts", "totalconnections"] as $k) {
    if (!isset($profileData[$k]) || $profileData[$k] === null) {
        if ($dbProfile && isset($dbProfile[$k]) && $dbProfile[$k] !== null) {
            $profileData[$k] = is_numeric($dbProfile[$k]) ? (int) $dbProfile[$k] : $dbProfile[$k];
        }
    }
}

// Resolve current channel name if available
$currentChannelName = null;
if ($isOnline && isset($profileData["cid"])) {
    try {
        $channels = CacheManager::i()->getChannelList();
        if (isset($channels[$profileData["cid"]]) && isset($channels[$profileData["cid"]]["channel_name"])) {
            $currentChannelName = (string) $channels[$profileData["cid"]]["channel_name"];
        }
    } catch (\Exception $e) {
        // ignore
    }
}
// Parse socials
$socials = [];
if ($dbProfile && !empty($dbProfile["socials_json"])) {
    $decoded = json_decode((string) $dbProfile["socials_json"], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $socials = $decoded;
    }
}

// Prepare social items with icon classes for template
$socialIconMap = [
    'instagram' => 'fab fa-instagram',
    'facebook' => 'fab fa-facebook',
    'youtube' => 'fab fa-youtube',
    'twitter' => 'fab fa-twitter',
    'steam' => 'fab fa-steam',
    'soundcloud' => 'fab fa-soundcloud',
    'github' => 'fab fa-github',
    'telegram' => 'fab fa-telegram',
    'twitch' => 'fab fa-twitch',
    'discord' => 'fab fa-discord',
];
$socialItems = [];
foreach ($socials as $key => $url) {
    if (!is_string($url) || trim($url) === '') continue;
    $icon = $socialIconMap[$key] ?? 'fas fa-link';
    $socialItems[] = [
        'key' => $key,
        'url' => $url,
        'icon' => $icon,
    ];
}

$renderData = [
    "title" => "Profile",
    "navActiveIndex" => 0,
    "isOnline" => $isOnline,
    "profile" => $profileData,
    "avatarUrl" => $avatarUrl,
    "bannerUrl" => $bannerUrl,
    "groups" => $groupsDetailed,
    "socials" => $socialItems,
    "currentChannelName" => $currentChannelName,
];

TemplateUtils::i()->renderTemplate("profile", $renderData);

