<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;

require_once __DIR__ . "/private/php/load.php";

$db = DatabaseUtils::i()->getDb();

$cldbid = isset($_GET["cldbid"]) ? (int) $_GET["cldbid"] : null;
if ($cldbid === null && Auth::isLoggedIn()) {
    $cldbid = Auth::getCldbid();
}

// Stats are most useful for a specific user; if none specified and not logged in, show generic page
$userScoped = $cldbid !== null && $cldbid > 0;

$stats = [
    "country" => null,
    "last_online_text" => null,
    "time_online_text" => null,
    "timeouts" => null,
    "kicks_received" => 0,
    "bans_received" => 0,
    "total_bantime_received" => 0,
    "kicks_given" => 0,
    "bans_given" => 0,
    "total_bantime_given" => 0,
    "first_connection_text" => null,
];

$tsClient = null;
$uid = null;
$nickname = null;
$isOnline = false;

if ($userScoped) {
    try {
        $onlineClient = CacheManager::i()->getClient($cldbid);
        $isOnline = $onlineClient !== null;

        // TS DB info
        $dbinfo = null;
        if (TeamSpeakUtils::i()->checkTSConnection()) {
            try {
                $dbinfo = TeamSpeakUtils::i()->getTSNodeServer()->clientDbInfo($cldbid);
            } catch (\Exception $e) {
                $dbinfo = null;
            }
        }

        if ($dbinfo) {
            $uid = isset($dbinfo["client_unique_identifier"]) ? (string) $dbinfo["client_unique_identifier"] : null;
            $nickname = isset($dbinfo["client_nickname"]) ? (string) $dbinfo["client_nickname"] : null;
            $firstTs = isset($dbinfo["client_created"]) ? (int) $dbinfo["client_created"] : null; // unix seconds
            if ($firstTs) {
                $stats["first_connection_text"] = date('d/m/Y H:i:s', $firstTs);
            }
            $lastTs = isset($dbinfo["client_lastconnected"]) ? (int) $dbinfo["client_lastconnected"] : null;
            if ($lastTs) {
                $stats["last_online_text"] = buildLastSeenString($lastTs);
            }
        }

        if ($isOnline) {
            $nickname = (string) $onlineClient["client_nickname"];
            // Country from live info
            if (!empty($onlineClient["client_country"])) {
                $stats["country"] = (string) $onlineClient["client_country"];
            }
            // Session time
            try {
                if (TeamSpeakUtils::i()->checkTSConnection() && isset($onlineClient["clid"])) {
                    $live = TeamSpeakUtils::i()->getTSNodeServer()->clientGetById((int) $onlineClient["clid"])->getInfo(true);
                    if (isset($live["connection_connected_time"])) {
                        $ms = (int) $live["connection_connected_time"]; // ms
                        $seconds = (int) round($ms / 1000);
                        $stats["time_online_text"] = formatSecondsHMS($seconds);
                    }
                }
            } catch (\Exception $e) { /* ignore */ }
        }

        // Country fallback from DB profile
        if ($stats["country"] === null) {
            try {
                $dbProfile = $db->get("profiles", ["country"], ["cldbid" => $cldbid]);
                if ($dbProfile && !empty($dbProfile["country"])) {
                    $stats["country"] = (string) $dbProfile["country"];
                }
            } catch (\Exception $e) { /* ignore */ }
        }

        // Bans and kicks statistics based on banlist
        try {
            $banlist = CacheManager::i()->getBanList();
            if (is_array($banlist)) {
                foreach ($banlist as $ban) {
                    $duration = isset($ban["duration"]) && is_numeric($ban["duration"]) ? (int) $ban["duration"] : 0;
                    // Received: by UID if available, else by last nickname match
                    if ($uid && !empty($ban["uid"]) && (string) $ban["uid"] === $uid) {
                        $stats["bans_received"] += 1;
                        if ($duration > 0) $stats["total_bantime_received"] += $duration;
                    } else if ($nickname && !empty($ban["lastnickname"]) && (string) $ban["lastnickname"] === $nickname) {
                        $stats["bans_received"] += 1;
                        if ($duration > 0) $stats["total_bantime_received"] += $duration;
                    }
                    // Given: invoker name matches current nickname
                    if ($nickname && !empty($ban["invokername"]) && (string) $ban["invokername"] === $nickname) {
                        $stats["bans_given"] += 1;
                        if ($duration > 0) $stats["total_bantime_given"] += $duration;
                    }
                }
            }
        } catch (\Exception $e) { /* ignore */ }

        // Timeouts and kicks received are not tracked; set to 0 if unknown
        $stats["timeouts"] = 0;
        $stats["kicks_received"] = 0;
        $stats["kicks_given"] = $stats["kicks_given"] ?? 0;

    } catch (\Exception $e) {
        // ignore and render minimal page
    }
}

// Top connections from profiles table
$topConnections = [];
try {
    $rows = $db->select("profiles", ["cldbid", "nickname", "totalconnections"], [
        "totalconnections[!]" => null,
        "ORDER" => ["totalconnections" => "DESC"],
        "LIMIT" => 5,
    ]);
    foreach ($rows as $r) {
        $topConnections[] = [
            "cldbid" => (int) $r["cldbid"],
            "nickname" => (string) ($r["nickname"] ?: ("User #" . $r["cldbid"])),
            "totalconnections" => (int) $r["totalconnections"],
        ];
    }
} catch (\Exception $e) { /* ignore */ }

// Last 7 days activity: users with lastconnected in each day
$chartLabels = [];
$chartData = [];
try {
    $end = new DateTime('today');
    for ($i = 6; $i >= 0; $i--) {
        $day = clone $end;
        $day->modify("-{$i} day");
        $startTs = (int) $day->setTime(0,0,0)->getTimestamp();
        $endTs = (int) $day->setTime(23,59,59)->getTimestamp();
        $chartLabels[] = $day->format('d/m');
        try {
            $cnt = $db->count("profiles", [
                "lastconnected_ts[>=]" => $startTs,
                "lastconnected_ts[<=]" => $endTs,
            ]);
            $chartData[] = (int) $cnt;
        } catch (\Exception $e) {
            $chartData[] = 0;
        }
    }
} catch (\Exception $e) { /* ignore */ }

// Helpers
function buildLastSeenString(int $lastTs): string {
    $now = time();
    $diff = max(0, $now - $lastTs);
    if ($diff < 60) {
        $rel = (int) $diff . " seconds ago";
    } else if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        $rel = $mins . " minute" . ($mins !== 1 ? "s" : "") . " ago";
    } else if ($diff < 86400) {
        $hrs = (int) floor($diff / 3600);
        $rel = $hrs . " hour" . ($hrs !== 1 ? "s" : "") . " ago";
    } else if ($diff < 2592000) {
        $days = (int) floor($diff / 86400);
        $rel = $days . " day" . ($days !== 1 ? "s" : "") . " ago";
    } else {
        $rel = null;
    }
    $date = date('d/m/Y H:i:s', $lastTs);
    return $rel ? ($rel . " - " . $date) : $date;
}

function formatSecondsHMS(int $seconds): string {
    $h = (int) floor($seconds / 3600);
    $m = (int) floor(($seconds % 3600) / 60);
    $s = (int) ($seconds % 60);
    if ($h > 0) return sprintf('%02dh%02dm', $h, $m);
    if ($m > 0) return sprintf('%02dm%02ds', $m, $s);
    return sprintf('%02ds', $s);
}

// Member list: users who have only server group IDs 6,7; order by cldbid ASC; paginate 10 per page
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$members = [];
try {
    $rows = $db->select("profiles", ["cldbid", "nickname", "servergroups"], ["ORDER" => ["cldbid" => "ASC"]]);
    foreach ($rows as $r) {
        $sg = isset($r["servergroups"]) ? (string) $r["servergroups"] : "";
        $ids = array_values(array_filter(array_map(function ($x) { return (int) trim($x); }, explode(",", $sg)), function ($v) { return $v > 0; }));
        if (empty($ids)) continue;
        $allInSet = true;
        foreach ($ids as $gid) { if ($gid !== 6 && $gid !== 7) { $allInSet = false; break; } }
        if (!$allInSet) continue;
        $members[] = [
            "cldbid" => (int) $r["cldbid"],
            "nickname" => (string) ($r["nickname"] ?: ("User #" . $r["cldbid"]))
        ];
    }
} catch (\Exception $e) { /* ignore */ }

$perPage = 10;
$start = ($page - 1) * $perPage;
$pageItems = array_slice($members, $start, $perPage);
$hasMore = count($members) > ($start + $perPage);
$nextPageUrl = $hasMore ? ("activity.php?page=" . ($page + 1)) : null;

TemplateUtils::i()->renderTemplate("activity", [
    "title" => "Activity",
    "navActiveIndex" => 6,
    // keep old keys in case template still references them
    "userScoped" => $userScoped,
    "stats" => $stats,
    "topConnections" => $topConnections,
    "chartLabels" => $chartLabels,
    "chartData" => $chartData,
    "cldbid" => $cldbid,
    // new member list payload
    "members" => $pageItems,
    "hasMore" => $hasMore,
    "nextPageUrl" => $nextPageUrl,
    "page" => $page,
]);

