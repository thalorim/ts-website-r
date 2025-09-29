<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\PhpFileCache\PhpFileCache;

require_once __DIR__ . "/private/php/load.php";

$db = DatabaseUtils::i()->getDb();

// Build members from live TS server group membership (preferred), fallback to DB profiles
$members = [];
if (TeamSpeakUtils::i()->checkTSConnection()) {
    try {
        $node = TeamSpeakUtils::i()->getTSNodeServer();
        $g6 = $node->serverGroupClientList(6) ?: [];
        $g7 = $node->serverGroupClientList(7) ?: [];

        $map = [];
        foreach ($g6 as $c) {
            $dbid = isset($c['cldbid']) ? (int) $c['cldbid'] : (isset($c['client_database_id']) ? (int) $c['client_database_id'] : null);
            if (!$dbid) continue;
            if (!isset($map[$dbid])) $map[$dbid] = ['has6' => false, 'has7' => false];
            $map[$dbid]['has6'] = true;
        }
        foreach ($g7 as $c) {
            $dbid = isset($c['cldbid']) ? (int) $c['cldbid'] : (isset($c['client_database_id']) ? (int) $c['client_database_id'] : null);
            if (!$dbid) continue;
            if (!isset($map[$dbid])) $map[$dbid] = ['has6' => false, 'has7' => false];
            $map[$dbid]['has7'] = true;
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

            foreach ($map as $dbid => $flags) {
                $has6 = (bool) $flags['has6'];
                $has7 = (bool) $flags['has7'];
                if (!$has6 && !$has7) continue;
                // Category: 0 = group 6 (also includes 6&7), 1 = only group 7
                $cat = $has6 ? 0 : 1;
                $nick = null;
                $country = null;
                $online = CacheManager::i()->getClient($dbid);
                if ($online) {
                    if (isset($online['client_nickname'])) { $nick = (string) $online['client_nickname']; }
                    if (!empty($online['client_country'])) { $country = (string) $online['client_country']; }
                }
                if (!$nick || !$country) {
                    if (isset($profilesById[$dbid])) {
                        if (!$nick && !empty($profilesById[$dbid]['nickname'])) { $nick = $profilesById[$dbid]['nickname']; }
                        if (!$country && !empty($profilesById[$dbid]['country'])) { $country = $profilesById[$dbid]['country']; }
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
            $has6 = in_array(6, $ids, true);
            $has7 = in_array(7, $ids, true);
            if (!$has6 && !$has7) continue;
            $cat = $has6 ? 0 : 1;
            $members[] = [
                "cldbid" => (int) $r["cldbid"],
                "nickname" => (string) ($r["nickname"] ?: ("User #" . $r["cldbid"])) ,
                "country" => isset($r['country']) ? (string) $r['country'] : null,
                "cat" => $cat
            ];
        }
    } catch (\Exception $e) { /* ignore */ }
}

// Sort: group 6 first, then group 7; within group by cldbid ascending
$members && usort($members, function ($a, $b) {
    if ($a["cat"] === $b["cat"]) {
        return $a["cldbid"] <=> $b["cldbid"];
    }
    return $a["cat"] <=> $b["cat"];
});

// Pagination
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$perPage = 10;
$start = ($page - 1) * $perPage;
$pageItems = array_slice($members, $start, $perPage);
$hasMore = count($members) > ($start + $perPage);
$nextPageUrl = $hasMore ? ("members.php?page=" . ($page + 1)) : null;

// Enrich page items with rank icon (group id 9..18 highest icon)
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
            $rankGroups = array_values(array_filter($sgids, function ($g) { return $g >= 9 && $g <= 18; }));
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

TemplateUtils::i()->renderTemplate("members", [
    "title" => "Members list",
    "navActiveIndex" => 6,
    "members" => $pageItems,
    "hasMore" => $hasMore,
    "nextPageUrl" => $nextPageUrl,
    "page" => $page,
]);

