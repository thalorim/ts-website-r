<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\ApiUtils;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getUid() !== "Jv/d1+pX7/q343RrIMPTTVpob+U=") {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

try {
    $assigner = @$_POST["assignerconfig"] ?? '';
    $adminGroups = @$_POST["adminstatus_groups"] ?? '';
    $discordWebhook = @$_POST["discord_login_webhook"] ?? '';
    $membersGroups = @$_POST["members_groups"] ?? '';
    $rankBadgeRange = @$_POST["rank_badge_range"] ?? '';

    if ($assigner !== '') {
        $assignerJson = json_decode($assigner, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("assignerconfig must be valid JSON");
        }
        Config::i()->setValue("assignerconfig", $assignerJson);
    }

    if ($adminGroups !== '') {
        $groupsJson = json_decode($adminGroups, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("adminstatus_groups must be valid JSON");
        }
        Config::i()->setValue("adminstatus_groups", $groupsJson);
    }

    if ($discordWebhook !== '') {
        if (strpos($discordWebhook, "https://discord.com/api/webhooks/") !== 0 && $discordWebhook !== '') {
            throw new \InvalidArgumentException("discord_login_webhook must be a Discord webhook URL");
        }
        Config::i()->setValue("discord_login_webhook", (string) $discordWebhook);
    }

    if ($membersGroups !== '') {
        $membersGroupsJson = json_decode($membersGroups, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("members_groups must be valid JSON");
        }
        if (!is_array($membersGroupsJson)) {
            throw new \InvalidArgumentException("members_groups must be an array");
        }
        Config::i()->setValue("members_groups", $membersGroupsJson);
    }

    if ($rankBadgeRange !== '') {
        $rankBadgeRangeJson = json_decode($rankBadgeRange, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("rank_badge_range must be valid JSON");
        }
        if (!is_array($rankBadgeRangeJson) || !isset($rankBadgeRangeJson['min']) || !isset($rankBadgeRangeJson['max'])) {
            throw new \InvalidArgumentException("rank_badge_range must be an object with 'min' and 'max' properties");
        }
        Config::i()->setValue("rank_badge_range", $rankBadgeRangeJson);
    }

    echo json_encode(["ok" => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

