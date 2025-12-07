<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\TSWebsite\Config;
use Wruczek\PhpFileCache\PhpFileCache;

require_once __DIR__ . "/private/php/load.php";

$groupId = isset($_GET["groupid"]) ? (int) $_GET["groupid"] : 0;

if ($groupId !== 19) {
    TemplateUtils::i()->renderErrorTemplate("404", "Not Found", "Clan page not found for this group");
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
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_group_id` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $db->query($createSql);
    }
} catch (\Exception $e) {
    TemplateUtils::i()->renderErrorTemplate("DB error", "Failed ensuring clan_groups table", $e->getMessage());
    exit;
}

// Fetch clan data
$clanData = $db->get("clan_groups", "*", ["group_id" => $groupId]);
if (!$clanData) {
    // Initialize with defaults
    $clanData = [
        "group_id" => $groupId,
        "clan_name" => "Clan Group 19",
        "clan_description" => null,
        "clan_avatar" => null,
    ];
    try {
        $db->insert("clan_groups", $clanData);
    } catch (\Exception $e) {
        // Non-fatal
    }
}

$clanName = $clanData["clan_name"] ?? "Clan Group 19";
$clanDescription = $clanData["clan_description"] ?? null;
$clanAvatar = $clanData["clan_avatar"] ?? "img/icons/defaulticon-128.png";

// Fetch members of the group
$members = [];
if (TeamSpeakUtils::i()->checkTSConnection()) {
    try {
        $node = TeamSpeakUtils::i()->getTSNodeServer();
        $groupClients = $node->serverGroupClientList((int)$groupId) ?: [];
        
        $dbidsInGroup = [];
        foreach ($groupClients as $c) {
            $dbid = isset($c['cldbid']) ? (int) $c['cldbid'] : (isset($c['client_database_id']) ? (int) $c['client_database_id'] : null);
            if ($dbid) {
                $dbidsInGroup[] = $dbid;
            }
        }

        if (!empty($dbidsInGroup)) {
            // Prefetch profile data
            $profilesById = [];
            try {
                $rows = $db->select('profiles', ['cldbid', 'nickname', 'country', 'servergroups'], ['cldbid' => $dbidsInGroup]);
                foreach ($rows as $r) {
                    $profilesById[(int)$r['cldbid']] = [
                        'nickname' => (string) $r['nickname'],
                        'country' => (string) $r['country'],
                        'servergroups' => (string) $r['servergroups'],
                    ];
                }
            } catch (\Exception $e) { /* ignore */ }

            foreach ($dbidsInGroup as $dbid) {
                $nick = null;
                $country = null;
                $servergroups = null;
                
                $online = CacheManager::i()->getClient($dbid);
                if ($online) {
                    if (isset($online['client_nickname'])) { $nick = (string) $online['client_nickname']; }
                    if (!empty($online['client_country'])) { $country = (string) $online['client_country']; }
                    if (!empty($online['client_servergroups'])) { $servergroups = (string) $online['client_servergroups']; }
                }
                
                if (!$nick || !$country || !$servergroups) {
                    if (isset($profilesById[$dbid])) {
                        if (!$nick && !empty($profilesById[$dbid]['nickname'])) { $nick = $profilesById[$dbid]['nickname']; }
                        if (!$country && !empty($profilesById[$dbid]['country'])) { $country = $profilesById[$dbid]['country']; }
                        if (!$servergroups && !empty($profilesById[$dbid]['servergroups'])) { $servergroups = $profilesById[$dbid]['servergroups']; }
                    }
                }
                
                if (!$country) {
                    try {
                        $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
                        $cached = $lastSeenCache->retrieve("u_" . (int) $dbid);
                        if (is_array($cached) && !empty($cached['country'])) {
                            $country = (string) $cached['country'];
                        }
                    } catch (\Exception $e) { /* ignore */ }
                }
                
                $members[] = [
                    'cldbid' => (int) $dbid,
                    'nickname' => $nick ?: ('User #' . $dbid),
                    'country' => $country ?: null,
                    'servergroups' => $servergroups,
                    'isOnline' => $online !== null,
                ];
            }
        }
    } catch (\Exception $e) {
        // ignore
    }
}

// Fallback to profiles table if TS server not reachable
if (empty($members)) {
    try {
        $rows = $db->select("profiles", ["cldbid", "nickname", "country", "servergroups"], ["ORDER" => ["cldbid" => "ASC"]]);
        foreach ($rows as $r) {
            $sg = isset($r["servergroups"]) ? (string) $r["servergroups"] : "";
            $ids = array_values(array_filter(array_map(function ($x) { return (int) trim($x); }, explode(",", $sg)), function ($v) { return $v > 0; }));
            
            if (in_array($groupId, $ids)) {
                $dbid = (int) $r["cldbid"];
                $nick = (string) ($r["nickname"] ?: ("User #" . $dbid));
                $country = isset($r['country']) ? (string) $r['country'] : null;
                
                if (!$country) {
                    try {
                        $lastSeenCache = new \Wruczek\PhpFileCache\PhpFileCache(__CACHE_DIR, "profile_last_seen");
                        $cached = $lastSeenCache->retrieve("u_" . $dbid);
                        if (is_array($cached) && !empty($cached['country'])) {
                            $country = (string) $cached['country'];
                        }
                    } catch (\Exception $e) { /* ignore */ }
                }
                
                $members[] = [
                    "cldbid" => $dbid,
                    "nickname" => $nick,
                    "country" => $country ?: null,
                    "servergroups" => $r["servergroups"],
                    "isOnline" => false,
                ];
            }
        }
    } catch (\Exception $e) { /* ignore */ }
}

// Sort members: online first, then by cldbid
usort($members, function ($a, $b) {
    if ($a["isOnline"] !== $b["isOnline"]) {
        return $b["isOnline"] <=> $a["isOnline"]; // online first
    }
    return $a["cldbid"] <=> $b["cldbid"];
});

// Get rank badge range for rank icons
$rankBadgeRange = Config::get("rank_badge_range", ["min" => 9, "max" => 18]);
$rankMin = isset($rankBadgeRange['min']) ? (int)$rankBadgeRange['min'] : 9;
$rankMax = isset($rankBadgeRange['max']) ? (int)$rankBadgeRange['max'] : 18;

// Enrich members with rank icon
try {
    $serverGroups = CacheManager::i()->getServerGroupList();
} catch (\Exception $e) { $serverGroups = null; }

if (!empty($members) && $serverGroups) {
    foreach ($members as &$m) {
        $sgids = [];
        if (!empty($m['servergroups'])) {
            $sgids = array_values(array_filter(array_map(function ($x) { return (int) trim($x); }, explode(',', $m['servergroups'])), function ($v) { return $v > 0; }));
        }
        
        if (!empty($sgids)) {
            $rankGroups = array_values(array_filter($sgids, function ($g) use ($rankMin, $rankMax) { 
                return $g >= $rankMin && $g <= $rankMax; 
            }));
            if (!empty($rankGroups)) {
                $chosen = max($rankGroups);
                if (isset($serverGroups[$chosen]) && !empty($serverGroups[$chosen]['iconid'])) {
                    $m['rank_iconid'] = (int) $serverGroups[$chosen]['iconid'];
                }
            }
        }
    }
    unset($m);
}

TemplateUtils::i()->renderTemplate("clan", [
    "title" => $clanName,
    "navActiveIndex" => 0,
    "groupId" => $groupId,
    "clanName" => $clanName,
    "clanDescription" => $clanDescription,
    "clanAvatar" => $clanAvatar,
    "members" => $members,
]);
