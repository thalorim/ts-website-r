<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\PhpFileCache\PhpFileCache;

require_once __DIR__ . "/private/php/load.php";

$db = DatabaseUtils::i()->getDb();

// Get configurable member groups from database, default to [532, 556, 542, 533]
// Display order: 532, then 556, then 542, then 533
$memberGroups = Config::get("members_groups", [532, 556, 542, 533]);
if (!is_array($memberGroups) || empty($memberGroups)) {
    $memberGroups = [532, 556, 542, 533];
}

// Define priority order for member groups (lower priority value = displayed first)
$groupPriority = array_flip(array_values($memberGroups));

// Helper function to get the best priority for a user based on their groups
$getBestPriority = function($groups) use ($groupPriority) {
    $priorities = [];
    foreach ($groups as $gid) {
        if (isset($groupPriority[$gid])) {
            $priorities[] = $groupPriority[$gid];
        }
    }
    return !empty($priorities) ? min($priorities) : PHP_INT_MAX;
};

// Build members from live TS server group membership (preferred), fallback to DB profiles
$members = [];
if (TeamSpeakUtils::i()->checkTSConnection()) {
    try {
        $node = TeamSpeakUtils::i()->getTSNodeServer();
        // Dynamically fetch all configured member groups
        $groupClients = [];
        foreach ($memberGroups as $groupId) {
            $groupClients[$groupId] = $node->serverGroupClientList((int)$groupId) ?: [];
        }

        // Map each client to their groups
        $map = [];
        foreach ($groupClients as $groupId => $clients) {
            foreach ($clients as $c) {
                $dbid = isset($c['cldbid']) ? (int) $c['cldbid'] : (isset($c['client_database_id']) ? (int) $c['client_database_id'] : null);
                if (!$dbid) continue;
                if (!isset($map[$dbid])) {
                    $map[$dbid] = ['groups' => []];
                }
                $map[$dbid]['groups'][] = (int)$groupId;
            }
        }

        if (!empty($map)) {
            // Prefetch profile country/nickname where available
            $profilesById = [];
            try {
                $ids = array_keys($map);
                if (!empty($ids)) {
                    $rows = $db->select('profiles', ['cldbid', 'nickname', 'country'], ['cldbid' => $ids]);
                    foreach ($rows as $r) {
                        $profilesById[(int)$r['cldbid']] = [
                            'nickname' => (string) $r['nickname'],
                            'country' => (string) $r['country'],
                        ];
                    }
                }
            } catch (\Exception $e) { /* ignore */ }

            foreach ($map as $dbid => $data) {
                $groups = $data['groups'];
                if (empty($groups)) continue;
                // Category: best priority from user's groups (for sorting)
                $cat = $getBestPriority($groups);
                $nick = null;
                $country = null;
                $isOnline = false;
                
                // Try to get data from online client (highest priority)
                $online = CacheManager::i()->getClient($dbid);
                if ($online) {
                    $isOnline = true;
                    if (isset($online['client_nickname'])) { $nick = (string) $online['client_nickname']; }
                    if (!empty($online['client_country'])) { $country = (string) $online['client_country']; }
                    
                    // Cache online data for offline use (similar to viewer/profile behavior)
                    if ($nick || $country) {
                        try {
                            $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
                            $cacheData = $lastSeenCache->retrieve("u_" . (int) $dbid);
                            if (!is_array($cacheData)) {
                                $cacheData = [];
                            }
                            if ($nick) { $cacheData['nickname'] = $nick; }
                            if ($country) { $cacheData['country'] = $country; }
                            $cacheData['ts'] = time();
                            $lastSeenCache->store("u_" . (int) $dbid, $cacheData, 31536000); // 365 days
                        } catch (\Exception $e) { /* ignore cache errors */ }
                    }
                }
                
                // Fallback to profile DB data if still missing
                if (!$nick || !$country) {
                    if (isset($profilesById[$dbid])) {
                        if (!$nick && !empty($profilesById[$dbid]['nickname'])) { $nick = $profilesById[$dbid]['nickname']; }
                        if (!$country && !empty($profilesById[$dbid]['country'])) { $country = $profilesById[$dbid]['country']; }
                    }
                }
                
                // Final fallback: use cached "last seen" data from when user was online
                if (!$nick || !$country) {
                    try {
                        $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
                        $cached = $lastSeenCache->retrieve("u_" . (int) $dbid);
                        if (is_array($cached)) {
                            if (!$nick && !empty($cached['nickname'])) { $nick = (string) $cached['nickname']; }
                            if (!$country && !empty($cached['country'])) { $country = (string) $cached['country']; }
                        }
                    } catch (\Exception $e) { /* ignore */ }
                }
                
                $members[] = [
                    'cldbid' => (int) $dbid,
                    'nickname' => $nick ?: ('User #' . $dbid),
                    'country' => $country ?: null,
                    'cat' => $cat,
                ];
            }
        }
    } catch (\Exception $e) { /* ignore */ }
}

// Fallback to profiles table if TS server not reachable
if (empty($members)) {
    try {
        $rows = $db->select("profiles", ["cldbid", "nickname", "country", "servergroups"], ["ORDER" => ["cldbid" => "ASC"]]);
        foreach ($rows as $r) {
            $sg = isset($r["servergroups"]) ? (string) $r["servergroups"] : "";
            $ids = array_values(array_filter(array_map(function ($x) { return (int) trim($x); }, explode(",", $sg)), function ($v) { return $v > 0; }));
            if (empty($ids)) continue;
            // Check if user has any of the configured member groups
            $matchingGroups = array_intersect($ids, $memberGroups);
            if (empty($matchingGroups)) continue;
            $cat = $getBestPriority(array_values($matchingGroups));
            $dbid = (int) $r["cldbid"];
            $nick = isset($r["nickname"]) && $r["nickname"] !== '' ? (string) $r["nickname"] : null;
            $country = isset($r['country']) && $r['country'] !== '' ? (string) $r['country'] : null;
            
            // Try to use cached "last seen" data as fallback (from when user was online)
            if (!$nick || !$country) {
                try {
                    $lastSeenCache = new \Wruczek\PhpFileCache\PhpFileCache(__CACHE_DIR, "profile_last_seen");
                    $cached = $lastSeenCache->retrieve("u_" . $dbid);
                    if (is_array($cached)) {
                        if (!$nick && !empty($cached['nickname'])) { $nick = (string) $cached['nickname']; }
                        if (!$country && !empty($cached['country'])) { $country = (string) $cached['country']; }
                    }
                } catch (\Exception $e) { /* ignore */ }
            }
            
            $members[] = [
                "cldbid" => $dbid,
                "nickname" => $nick ?: ("User #" . $dbid),
                "country" => $country ?: null,
                "cat" => $cat
            ];
        }
    } catch (\Exception $e) { /* ignore */ }
}

// Sort: by category (priority order: 532, 556, 542, 533), then by cldbid ascending
$members && usort($members, function ($a, $b) {
    if ($a["cat"] === $b["cat"]) {
        return $a["cldbid"] <=> $b["cldbid"];
    }
    return $a["cat"] <=> $b["cat"];
});

// Calculate top 5 countries for pie chart
$countryStats = [];
foreach ($members as $m) {
    if (!empty($m['country'])) {
        $country = strtoupper((string) $m['country']);
        if (!isset($countryStats[$country])) {
            $countryStats[$country] = 0;
        }
        $countryStats[$country]++;
    }
}
arsort($countryStats);
$topCountries = array_slice($countryStats, 0, 5, true);

// Pagination
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$perPage = 10;
$start = ($page - 1) * $perPage;
$pageItems = array_slice($members, $start, $perPage);
$hasMore = count($members) > ($start + $perPage);
$nextPageUrl = $hasMore ? ("members.php?page=" . ($page + 1)) : null;

// Get configurable rank badge range from database, default to 9-18
$rankBadgeRange = Config::get("rank_badge_range", ["min" => 996, "max" => 1005]);
$rankMin = isset($rankBadgeRange['min']) ? (int)$rankBadgeRange['min'] : 996;
$rankMax = isset($rankBadgeRange['max']) ? (int)$rankBadgeRange['max'] : 1005;

// Enrich page items with rank icon (configurable group id range)
try {
    $serverGroups = CacheManager::i()->getServerGroupList();
} catch (\Exception $e) { $serverGroups = null; }

if (!empty($pageItems) && $serverGroups) {
    $idsOnPage = array_map(function ($m) { return (int) $m['cldbid']; }, $pageItems);
    $profileSgById = [];
    try {
        $rows = $db->select('profiles', ['cldbid', 'servergroups'], ['cldbid' => $idsOnPage]);
        foreach ($rows as $r) { $profileSgById[(int)$r['cldbid']] = (string) $r['servergroups']; }
    } catch (\Exception $e) { /* ignore */ }

    $tsOk = TeamSpeakUtils::i()->checkTSConnection();
    $node = $tsOk ? TeamSpeakUtils::i()->getTSNodeServer() : null;

    foreach ($pageItems as &$m) {
        $dbid = (int) $m['cldbid'];
        $sgids = [];
        if ($tsOk) {
            try {
                $byDb = $node->clientGetServerGroupsByDbid($dbid);
                if (is_array($byDb)) { $sgids = array_keys($byDb); }
            } catch (\Exception $e) { /* fallback below */ }
        }
        if (empty($sgids) && isset($profileSgById[$dbid]) && $profileSgById[$dbid] !== '') {
            $sgids = array_values(array_filter(array_map(function ($x) { return (int) trim($x); }, explode(',', $profileSgById[$dbid])), function ($v) { return $v > 0; }));
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

// Prepare chart data for JavaScript
$chartDataJson = null;
if (!empty($topCountries)) {
    $chartData = [];
    foreach ($topCountries as $code => $count) {
        $chartData[] = [(string)$code, (int)$count];
    }
    $chartDataJson = json_encode($chartData);
}

TemplateUtils::i()->renderTemplate("members", [
    "title" => "Members list",
    "navActiveIndex" => 6,
    "members" => $pageItems,
    "hasMore" => $hasMore,
    "nextPageUrl" => $nextPageUrl,
    "page" => $page,
    "topCountries" => $topCountries,
    "chartDataJson" => $chartDataJson,
]);
