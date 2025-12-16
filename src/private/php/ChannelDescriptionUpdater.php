<?php

namespace Wruczek\TSWebsite;

use TeamSpeak3_Helper_String;
use Wruczek\PhpFileCache\PhpFileCache;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;

/**
 * Updates configured TeamSpeak channel descriptions with
 * TS + Steam user status in BBCode (for channel descriptions).
 *
 * Configure using Config keys (DB or private/config.local.php overrides):
 * - channel_description_updater_targets (array)
 * - steam_api_key (string)
 */
class ChannelDescriptionUpdater {

    /** @var PhpFileCache */
    private $cache;

    public function __construct() {
        $this->cache = new PhpFileCache(__CACHE_DIR, "channel_description_updater");
    }

    /**
     * Updates all configured channels.
     * @return array result summary
     */
    public function updateAll(): array {
        $targets = Config::get("channel_description_updater_targets", []);
        if (!is_array($targets)) {
            $targets = [];
        }

        if (!TeamSpeakUtils::i()->checkTSConnection()) {
            return [
                "success" => false,
                "error" => "Cannot connect to TeamSpeak server",
                "exceptions" => array_map(function ($e) { return (string) $e->getMessage(); }, TeamSpeakUtils::i()->getExceptionsList()),
            ];
        }

        $server = TeamSpeakUtils::i()->getTSNodeServer();
        $results = [];

        foreach ($targets as $idx => $target) {
            $cid = (int) ($target["channel_id"] ?? 0);
            $cldbid = isset($target["cldbid"]) ? (int) $target["cldbid"] : null;
            $steamId64 = isset($target["steamid64"]) ? (string) $target["steamid64"] : "";
            $label = isset($target["label"]) ? (string) $target["label"] : ("target_" . $idx);

            if ($cid <= 0 || $cldbid === null || $cldbid <= 0) {
                $results[] = [
                    "label" => $label,
                    "channel_id" => $cid,
                    "success" => false,
                    "error" => "Invalid target config (requires channel_id and cldbid)",
                ];
                continue;
            }

            try {
                $tsData = $this->getTsUserData($server, $cldbid);
                $steamData = $this->getSteamUserData($steamId64);

                $description = $this->renderDescriptionBb($tsData, $steamData, $target);

                // Update channel description
                $channel = $server->channelGetById($cid);
                $channel->modify([
                    "channel_description" => $description
                ]);

                $results[] = [
                    "label" => $label,
                    "channel_id" => $cid,
                    "success" => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    "label" => $label,
                    "channel_id" => $cid,
                    "success" => false,
                    "error" => $e->getMessage(),
                ];
            }
        }

        $ok = true;
        foreach ($results as $r) {
            if (empty($r["success"])) {
                $ok = false;
                break;
            }
        }

        return [
            "success" => $ok,
            "results" => $results,
            "updated_at" => time(),
        ];
    }

    private function getTsUserData($server, int $cldbid): array {
        $online = false;
        $nickname = "User #" . $cldbid;
        $activeForSeconds = null;
        $connections = null;
        $lastSeenTs = null;

        // Always try to read DB info for stable fields (connections, last seen, default nickname)
        try {
            $dbInfo = $server->clientDbInfo($cldbid);
            $nickname = $this->toScalar($dbInfo["client_nickname"] ?? $nickname);
            $connections = (int) ($this->toScalar($dbInfo["client_totalconnections"] ?? 0));
            $lastConnected = $this->toScalar($dbInfo["client_lastconnected"] ?? null);
            if (is_numeric($lastConnected)) {
                $lastSeenTs = (int) $lastConnected;
            }
        } catch (\Exception $e) {
            // ignore; still can show online info if connected
        }

        // If user is online, get live info
        try {
            $client = $server->clientGetByDbid($cldbid);
            $info = $client->getInfo(true);
            $online = true;

            $nickname = $this->toScalar($info["client_nickname"] ?? $nickname);

            $connectedMs = $this->toScalar($info["connection_connected_time"] ?? null);
            if (is_numeric($connectedMs)) {
                $activeForSeconds = (int) floor(((int) $connectedMs) / 1000);
            }

            // Prefer DB connections if available, else fall back to client DB id info fields
            if ($connections === null) {
                $connections = (int) ($this->toScalar($info["client_totalconnections"] ?? 0));
            }
        } catch (\Exception $e) {
            // offline or cannot fetch live info
        }

        return [
            "cldbid" => $cldbid,
            "nickname" => $nickname,
            "online" => $online,
            "active_for_seconds" => $activeForSeconds,
            "connections" => $connections,
            "last_seen_ts" => $lastSeenTs,
        ];
    }

    private function getSteamUserData(string $steamId64): ?array {
        $steamId64 = trim($steamId64);
        if ($steamId64 === "") {
            return null;
        }

        $key = (string) Config::get("steam_api_key", "");
        if ($key === "") {
            return [
                "steamid" => $steamId64,
                "error" => "Steam API key not configured",
            ];
        }

        $cacheKey = "steam_" . preg_replace("/[^0-9]/", "", $steamId64);

        return $this->cache->refreshIfExpired($cacheKey, function () use ($key, $steamId64) {
            $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?" .
                http_build_query(["key" => $key, "steamids" => $steamId64]);

            $ctx = stream_context_create([
                "http" => [
                    "timeout" => 3,
                    "ignore_errors" => true,
                    "header" => "User-Agent: ts-website\r\n",
                ]
            ]);

            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false || $raw === "") {
                return [
                    "steamid" => $steamId64,
                    "error" => "Failed to fetch Steam profile",
                ];
            }

            $json = json_decode($raw, true);
            if (!is_array($json) || empty($json["response"]["players"][0])) {
                return [
                    "steamid" => $steamId64,
                    "error" => "Steam profile not found",
                ];
            }

            $p = $json["response"]["players"][0];

            return [
                "steamid" => (string) ($p["steamid"] ?? $steamId64),
                "name" => (string) ($p["personaname"] ?? ""),
                "profile_url" => (string) ($p["profileurl"] ?? ""),
                "avatar" => (string) ($p["avatarfull"] ?? ($p["avatarmedium"] ?? "")),
                "personastate" => (int) ($p["personastate"] ?? 0),
                "game" => (string) ($p["gameextrainfo"] ?? ""),
                "lastlogoff" => isset($p["lastlogoff"]) ? (int) $p["lastlogoff"] : null,
                "visibility" => (int) ($p["communityvisibilitystate"] ?? 0),
            ];
        }, (int) Config::get("cache_channel_description_updater_steam", 60));
    }

    private function renderDescriptionBb(array $ts, ?array $steam, array $target): string {
        $title = (string) ($target["title"] ?? "Description:");

        $tsName = (string) $ts["nickname"];
        $tsStatus = $ts["online"] ? "[color=green]ONLINE[/color]" : "[color=red]OFFLINE[/color]";
        $activeBy = $ts["online"] && is_int($ts["active_for_seconds"])
            ? $this->formatDuration($ts["active_for_seconds"])
            : null;

        $connections = isset($ts["connections"]) ? (int) $ts["connections"] : null;
        $tsLastSeen = (!$ts["online"] && isset($ts["last_seen_ts"]) && is_int($ts["last_seen_ts"]))
            ? date("Y-m-d H:i:s", (int) $ts["last_seen_ts"])
            : null;

        $lines = [];
        $lines[] = "[center][b][size=15]" . $this->escapeBb($title) . "[/size][/b][/center]";
        $lines[] = "";
        $lines[] = "[b]" . $this->escapeBb($tsName) . "[/b]";
        $lines[] = "Status: " . $tsStatus;
        if ($activeBy !== null) {
            $lines[] = "Active by: " . $this->escapeBb($activeBy);
        }
        if ($tsLastSeen !== null) {
            $lines[] = "Last seen: " . $this->escapeBb($tsLastSeen);
        }
        if ($connections !== null) {
            $lines[] = "Connections: " . $connections;
        }

        if ($steam !== null) {
            $lines[] = "";
            $lines[] = "[hr]";
            $lines[] = "";

            $steamName = (string) ($steam["name"] ?? "");
            $avatar = (string) ($steam["avatar"] ?? "");

            if (!empty($avatar)) {
                $lines[] = "[img]" . $this->escapeBb($avatar) . "[/img]";
                $lines[] = "";
            }

            if (!empty($steam["error"])) {
                $lines[] = "[b]Steam[/b]";
                $lines[] = "Error: " . $this->escapeBb((string) $steam["error"]);
            } else {
                $steamStatus = $this->formatSteamStatus($steam);
                $lastSeen = isset($steam["lastlogoff"]) && is_int($steam["lastlogoff"])
                    ? date("Y-m-d H:i:s", (int) $steam["lastlogoff"])
                    : null;
                $game = trim((string) ($steam["game"] ?? ""));
                $profileUrl = trim((string) ($steam["profile_url"] ?? ""));

                $lines[] = "Nick steam: " . $this->escapeBb($steamName !== "" ? $steamName : "-");
                $lines[] = "Status: " . $this->escapeBb($steamStatus);
                if ($lastSeen !== null) {
                    $lines[] = "Last seen: " . $this->escapeBb($lastSeen);
                }

                if ($game !== "") {
                    $lines[] = "Currently playing: " . $this->escapeBb($game);
                } else {
                    $lines[] = "Currently doesn't play anything";
                }

                if ($profileUrl !== "") {
                    $lines[] = "Profile: [url=" . $this->escapeBb($profileUrl) . "]steamcommunity.com[/url]";
                }
            }
        }

        $showUpdatedAt = (bool) ($target["show_updated_at"] ?? true);
        if ($showUpdatedAt) {
            $lines[] = "";
            $lines[] = "[size=10][color=grey]Updated: " . $this->escapeBb(date("Y-m-d H:i:s")) . "[/color][/size]";
        }

        return implode("\n", $lines);
    }

    private function formatSteamStatus(array $steam): string {
        $persona = (int) ($steam["personastate"] ?? 0);
        $game = trim((string) ($steam["game"] ?? ""));

        if ($game !== "") {
            return "In-Game";
        }

        // https://partner.steamgames.com/doc/api/ISteamUser#PersonaState
        switch ($persona) {
            case 1: return "Online";
            case 2: return "Busy";
            case 3: return "Away";
            case 4: return "Snooze";
            case 5: return "Looking to Trade";
            case 6: return "Looking to Play";
            default: return "Offline";
        }
    }

    private function formatDuration(int $seconds): string {
        if ($seconds < 0) $seconds = 0;

        $h = (int) floor($seconds / 3600);
        $m = (int) floor(($seconds % 3600) / 60);
        $s = (int) ($seconds % 60);

        if ($h > 0) {
            return $h . "h " . $m . "m";
        }
        if ($m > 0) {
            return $m . "m " . $s . "s";
        }
        return $s . "s";
    }

    private function toScalar($val) {
        if ($val instanceof TeamSpeak3_Helper_String) {
            return (string) $val;
        }
        return $val;
    }

    /**
     * Minimal BBCode escaping (avoid breaking tags with brackets).
     */
    private function escapeBb(string $str): string {
        return str_replace(["[", "]"], ["(", ")"], $str);
    }
}

