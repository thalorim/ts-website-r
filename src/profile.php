<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Utils\AvatarBorderUtils;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\TSWebsite\Utils\DiscordUtils;
use Wruczek\PhpFileCache\PhpFileCache;

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
        try {
            $tsInfo = TeamSpeakUtils::i()->getTSNodeServer()->clientDbInfo($cldbid);
        } catch (\Exception $e1) {
            // Fallback to raw request in case wrapper call fails
            try {
                $reply = TeamSpeakUtils::i()->getTSNodeServer()->request("clientdbinfo cldbid=" . (int) $cldbid);
                $list = $reply && method_exists($reply, 'toList') ? $reply->toList() : null;
                if (is_array($list) && isset($list[0])) {
                    $tsInfo = $list[0];
                }
            } catch (\Exception $e2) {
                // ignore
            }
        }
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
    
    // Get live timestamps from online client (same as viewer hover data)
    if (isset($onlineClient["client_created"]) && is_numeric($onlineClient["client_created"])) {
        $profileData["created_ts"] = (int) $onlineClient["client_created"];
    }
    if (isset($onlineClient["client_lastconnected"]) && is_numeric($onlineClient["client_lastconnected"])) {
        $profileData["lastconnected_ts"] = (int) $onlineClient["client_lastconnected"];
    }
    if (isset($onlineClient["client_totalconnections"]) && is_numeric($onlineClient["client_totalconnections"])) {
        $profileData["totalconnections"] = (int) $onlineClient["client_totalconnections"];
    }

    // Try to get more live info to compute "online since" timestamp
    try {
        if (TeamSpeakUtils::i()->checkTSConnection() && isset($profileData["clid"])) {
            $live = TeamSpeakUtils::i()->getTSNodeServer()->clientGetById($profileData["clid"])->getInfo(true);
            if (isset($live["connection_connected_time"])) {
                $profileData["online_since_ms"] = (int) $live["connection_connected_time"]; // milliseconds
                // Build human readable duration: days, hours, minutes, seconds
                $totalSeconds = (int) floor(((int) $profileData["online_since_ms"]) / 1000);
                $days = (int) floor($totalSeconds / 86400);
                $hours = (int) floor(($totalSeconds % 86400) / 3600);
                $minutes = (int) floor(($totalSeconds % 3600) / 60);
                $seconds = (int) ($totalSeconds % 60);
                $parts = [];
                if ($days > 0) { $parts[] = $days . " day" . ($days !== 1 ? "s" : ""); }
                if ($hours > 0) { $parts[] = $hours . " hour" . ($hours !== 1 ? "s" : ""); }
                if ($minutes > 0) { $parts[] = $minutes . " minute" . ($minutes !== 1 ? "s" : ""); }
                $parts[] = $seconds . " second" . ($seconds !== 1 ? "s" : "");
                $profileData["online_since_text"] = implode(" ", $parts);
            }
            // Get detailed client info including totalconnections, created, lastconnected
            if (isset($live["client_totalconnections"]) && is_numeric($live["client_totalconnections"])) {
                $profileData["totalconnections"] = (int) $live["client_totalconnections"]; // live value preferred when online
            }
            if (isset($live["client_created"]) && is_numeric($live["client_created"])) {
                $profileData["created_ts"] = (int) $live["client_created"]; // override with live data
            }
            if (isset($live["client_lastconnected"]) && is_numeric($live["client_lastconnected"])) {
                $profileData["lastconnected_ts"] = (int) $live["client_lastconnected"]; // override with live data
            }
            // Bandwidth last minute totals (bytes) -> compute per-second and human readable
            if (isset($live["connection_bandwidth_sent_last_minute_total"])) {
                $lastUpTotal = (int) $live["connection_bandwidth_sent_last_minute_total"];
                $upBps = $lastUpTotal / 60.0;
                $profileData["bw_up_h"] = ($upBps >= 1024*1024)
                    ? number_format($upBps / (1024*1024), 2) . " MB/s"
                    : (($upBps >= 1024)
                        ? number_format($upBps / 1024, 2) . " KB/s"
                        : number_format($upBps, 0) . " B/s");
            }
            if (isset($live["connection_bandwidth_received_last_minute_total"])) {
                $lastDownTotal = (int) $live["connection_bandwidth_received_last_minute_total"];
                $downBps = $lastDownTotal / 60.0;
                $profileData["bw_down_h"] = ($downBps >= 1024*1024)
                    ? number_format($downBps / (1024*1024), 2) . " MB/s"
                    : (($downBps >= 1024)
                        ? number_format($downBps / 1024, 2) . " KB/s"
                        : number_format($downBps, 0) . " B/s");
            }
        }
    } catch (\Exception $e) {
        // ignore
    }
}

// Store last seen display data in cache when online for offline reuse (no DB changes)
if ($isOnline) {
    try {
        $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
        $lastSeenData = [
            "country" => $profileData["country"] ?? null,
            "version" => $profileData["version"] ?? null,
            "platform" => $profileData["platform"] ?? null,
            "totalconnections" => $profileData["totalconnections"] ?? null,
            "created_ts" => $profileData["created_ts"] ?? null,
            "lastconnected_ts" => $profileData["lastconnected_ts"] ?? null,
            "bw_up_h" => $profileData["bw_up_h"] ?? null,
            "bw_down_h" => $profileData["bw_down_h"] ?? null,
            "nickname" => $profileData["nickname"] ?? null,
            "ts" => time(),
        ];
        $lastSeenCache->store("u_" . (int) $cldbid, $lastSeenData, 31536000); // 365 days
    } catch (\Exception $e) {
        // ignore cache errors
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
            // Try to resolve last known nickname from DB by cldbid
            if (empty($profileData["nickname"])) {
                try {
                    $nameInfo = TeamSpeakUtils::i()->getTSNodeServer()->clientGetNameByDbid($cldbid);
                    if (is_array($nameInfo) && isset($nameInfo["name"])) {
                        $profileData["nickname"] = (string) $nameInfo["name"];
                    }
                } catch (\Exception $e) {
                    // fallback to raw request if wrapper not available
                    try {
                        $reply = TeamSpeakUtils::i()->getTSNodeServer()->request("clientgetnamefromdbid cldbid=" . (int) $cldbid);
                        $list = $reply && method_exists($reply, 'toList') ? $reply->toList() : null;
                        if (is_array($list) && isset($list[0]["name"])) {
                            $profileData["nickname"] = (string) $list[0]["name"];
                        }
                    } catch (\Exception $e2) {
                        // ignore
                    }
                }
            }
        }
    } catch (\Exception $e) {
        // ignore
    }
}

// Fetch DB-stored fields FIRST before we update database
$dbProfile = $db->get("profiles", "*", ["cldbid" => $cldbid]);

// Persist to DB (upsert) - do not overwrite existing values with NULLs
// This updates the database with the latest data every time a profile is viewed
// ensuring that data like totalconnections, created_ts, lastconnected_ts persist when user goes offline
// Only save if we have new data (user is online or we got fresh TS data)
if ($isOnline || $tsInfo) {
    try {
        if ($dbProfile) {
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
}

// Prepare data for template

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

$rankImageUrl = null;
$rankLevel = null;
// FACEIT Rank images in order Rank 1..10
$rankImageUrls = [
    'https://support.faceit.com/hc/article_attachments/10525200575516', // Rank 1
    'https://support.faceit.com/hc/article_attachments/10525189649308', // Rank 2
    'https://support.faceit.com/hc/article_attachments/10525200576796', // Rank 3
    'https://support.faceit.com/hc/article_attachments/10525185037724', // Rank 4
    'https://support.faceit.com/hc/article_attachments/10525215800860', // Rank 5
    'https://support.faceit.com/hc/article_attachments/10525245409692', // Rank 6
    'https://support.faceit.com/hc/article_attachments/10525185034012', // Rank 7
    'https://support.faceit.com/hc/article_attachments/10525189648796', // Rank 8
    'https://support.faceit.com/hc/article_attachments/10525200576028', // Rank 9
    'https://support.faceit.com/hc/article_attachments/10525189646876', // Rank 10
];

// Determine rank by presence of server group ids 9..18 (9->Rank1, 18->Rank10)
if (!empty($groupsDetailed)) {
    $matchedRankGroupIds = [];
    foreach ($groupsDetailed as $g) {
        $sgid = isset($g['sgid']) ? (int) $g['sgid'] : 0;
        if ($sgid >= 9 && $sgid <= 18) {
            $matchedRankGroupIds[] = $sgid;
        }
    }
    if (!empty($matchedRankGroupIds)) {
        // Prefer the highest group id -> highest rank
        $chosenGroupId = max($matchedRankGroupIds);
        $rankLevel = $chosenGroupId - 8; // 9->1, 18->10
        $idx = $rankLevel - 1;
        if ($idx >= 0 && $idx < count($rankImageUrls)) {
            $rankImageUrl = $rankImageUrls[$idx];
        }
    }
}

$avatarUrl = ($dbProfile && !empty($dbProfile["avatar_url"])) ? $dbProfile["avatar_url"] : "img/icons/defaulticon-128.png";
$bannerUrl = ($dbProfile && !empty($dbProfile["banner_url"])) ? $dbProfile["banner_url"] : null;
$avatarBorderKey = $dbProfile && isset($dbProfile["avatar_border"]) ? AvatarBorderUtils::normalize($dbProfile["avatar_border"]) : AvatarBorderUtils::getDefaultKey();
$avatarBorderUrl = AvatarBorderUtils::getUrl($avatarBorderKey);
$userbarUrl = ($dbProfile && !empty($dbProfile["userbar_url"])) ? $dbProfile["userbar_url"] : null;

// Fetch Discord data if discord_id is set
$discordData = null;
if ($dbProfile && !empty($dbProfile["discord_id"])) {
    try {
        $discordData = DiscordUtils::i()->getUserData($dbProfile["discord_id"]);
    } catch (\Exception $e) {
        // Silently fail - Discord widget just won't show
    }
}

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
// Preserve timestamps and total connections always (even when offline)
$preserveNumericKeys = ["created_ts", "lastconnected_ts", "totalconnections"];
foreach ($preserveNumericKeys as $k) {
    if (!isset($profileData[$k]) || $profileData[$k] === null || $profileData[$k] === '') {
        if ($dbProfile && isset($dbProfile[$k]) && $dbProfile[$k] !== null && $dbProfile[$k] !== '') {
            $profileData[$k] = is_numeric($dbProfile[$k]) ? (int) $dbProfile[$k] : $dbProfile[$k];
        }
    }
}

// Preserve additional Overview fields from DB when offline or missing
foreach (["country", "version", "platform", "badges"] as $k) {
    if (!isset($profileData[$k]) || $profileData[$k] === null || $profileData[$k] === '') {
        if ($dbProfile && isset($dbProfile[$k]) && $dbProfile[$k] !== null && $dbProfile[$k] !== '') {
            $profileData[$k] = $dbProfile[$k];
        }
    }
}

// Offline fallback: use last seen values from cache so UI shows the last online data
if (!$isOnline) {
    try {
        $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
        $last = $lastSeenCache->retrieve("u_" . (int) $cldbid);
        if (is_array($last)) {
            foreach (["country", "version", "platform", "bw_up_h", "bw_down_h", "nickname", "totalconnections", "created_ts", "lastconnected_ts"] as $k) {
                if (!isset($profileData[$k]) || $profileData[$k] === null || $profileData[$k] === '') {
                    if (isset($last[$k]) && $last[$k] !== null && $last[$k] !== '') {
                        $profileData[$k] = $last[$k];
                    }
                }
            }
            // Use cached timestamp as lastconnected_ts fallback if missing (legacy support)
            if ((!isset($profileData["lastconnected_ts"]) || $profileData["lastconnected_ts"] === null) && isset($last['ts']) && is_numeric($last['ts'])) {
                $profileData["lastconnected_ts"] = (int) $last['ts'];
            }
        }
    } catch (\Exception $e) {
        // ignore cache errors
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
    "avatarBorderUrl" => $avatarBorderUrl,
    "avatarBorderKey" => $avatarBorderKey,
    "bannerUrl" => $bannerUrl,
    "userbarUrl" => $userbarUrl,
    "groups" => $groupsDetailed,
    "socials" => $socialItems,
    "currentChannelName" => $currentChannelName,
    "currentChannelId" => isset($profileData['cid']) ? (int) $profileData['cid'] : null,
    "rankImageUrl" => $rankImageUrl,
    "rankLevel" => $rankLevel,
    "discordData" => $discordData,
];

// Compute last seen text for offline users
if (!$isOnline) {
    $lastSeenTs = null;
    if (isset($profileData["lastconnected_ts"]) && is_numeric($profileData["lastconnected_ts"])) {
        $lastSeenTs = (int) $profileData["lastconnected_ts"]; // unix seconds
    }
    if ($lastSeenTs === null) {
        try {
            $lastSeenCache = new \Wruczek\PhpFileCache\PhpFileCache(__CACHE_DIR, "profile_last_seen");
            $cached = $lastSeenCache->retrieve("u_" . (int) $cldbid);
            if (is_array($cached) && isset($cached["ts"]) && is_numeric($cached["ts"])) {
                $lastSeenTs = (int) $cached["ts"];
            }
        } catch (\Exception $e) {
            // ignore
        }
    }

    $lastSeenText = null;
    if ($lastSeenTs !== null && $lastSeenTs > 0) {
        $now = time();
        $diff = max(0, $now - $lastSeenTs);
        if ($diff < 60) {
            $lastSeenText = (int) $diff . " seconds ago";
        } else if ($diff < 3600) { // < 60 minutes
            $mins = (int) floor($diff / 60);
            $lastSeenText = $mins . " minute" . ($mins !== 1 ? "s" : "") . " ago";
        } else if ($diff < 86400) { // < 24 hours
            $hrs = (int) floor($diff / 3600);
            $lastSeenText = $hrs . " hour" . ($hrs !== 1 ? "s" : "") . " ago";
        } else if ($diff < 2592000) { // < 30 days
            $days = (int) floor($diff / 86400);
            $lastSeenText = $days . " day" . ($days !== 1 ? "s" : "") . " ago";
        } else {
            $lastSeenText = date('d/m/Y', $lastSeenTs);
        }
    }

    $renderData["lastSeenText"] = $lastSeenText;
}

// Compute human-readable first connected and last online date strings for Overview
// Use the same format as viewer hover: DD/MM/YYYY HH:MM:SS
if (isset($profileData["created_ts"]) && is_numeric($profileData["created_ts"])) {
    $renderData["profile"]["first_connected_human"] = date('d/m/Y H:i:s', (int) $profileData["created_ts"]);
}

// Last online: show "Now" when online, or date/time when offline
if ($isOnline) {
    $renderData["profile"]["last_online_human"] = "Now";
} else if (isset($profileData["lastconnected_ts"]) && is_numeric($profileData["lastconnected_ts"])) {
    $renderData["profile"]["last_online_human"] = date('d/m/Y H:i:s', (int) $profileData["lastconnected_ts"]);
}

TemplateUtils::i()->renderTemplate("profile", $renderData);

