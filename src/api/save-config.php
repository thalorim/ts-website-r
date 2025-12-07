<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\ApiUtils;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

try {
    $assigner = @$_POST["assignerconfig"] ?? '';
    $adminGroups = @$_POST["adminstatus_groups"] ?? '';
    $discordWebhook = @$_POST["discord_login_webhook"] ?? '';
    $steamApiKey = @$_POST["steam_api_key"] ?? '';
    
    // TeamSpeak 3 settings
    $ts3Host = @$_POST["ts3_host"] ?? '';
    $ts3QueryPort = @$_POST["ts3_queryport"] ?? '';
    $ts3ServerPort = @$_POST["ts3_serverport"] ?? '';
    $ts3DisplayIp = @$_POST["ts3_displayip"] ?? '';
    $ts3Username = @$_POST["ts3_username"] ?? '';
    $ts3Password = @$_POST["ts3_password"] ?? '';

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
        if (strpos($discordWebhook, "https://discord.com/api/webhooks/") !== 0) {
            throw new \InvalidArgumentException("discord_login_webhook must be a Discord webhook URL");
        }
        Config::i()->setValue("discord_login_webhook", (string) $discordWebhook);
    }

    if ($steamApiKey !== '') {
        // Validate Steam API key format (32 hex characters)
        if (!preg_match('/^[A-F0-9]{32}$/i', $steamApiKey)) {
            throw new \InvalidArgumentException("steam_api_key must be a valid 32-character Steam API key");
        }
        Config::i()->setValue("steam_api_key", (string) $steamApiKey);
    }

    // Save TeamSpeak 3 settings
    if ($ts3Host !== '') {
        Config::i()->setValue("query_host", (string) $ts3Host);
    }
    
    if ($ts3QueryPort !== '') {
        $port = (int) $ts3QueryPort;
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException("ts3_queryport must be between 1 and 65535");
        }
        Config::i()->setValue("query_port", $port);
    }
    
    if ($ts3ServerPort !== '') {
        $port = (int) $ts3ServerPort;
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException("ts3_serverport must be between 1 and 65535");
        }
        Config::i()->setValue("voice_port", $port);
    }
    
    if ($ts3DisplayIp !== '') {
        Config::i()->setValue("query_displayip", (string) $ts3DisplayIp);
    }
    
    if ($ts3Username !== '') {
        Config::i()->setValue("query_username", (string) $ts3Username);
    }
    
    if ($ts3Password !== '') {
        Config::i()->setValue("query_password", (string) $ts3Password);
    }

    echo json_encode(["ok" => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

