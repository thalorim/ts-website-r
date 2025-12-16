<?php

// This endpoint is intended to be called by a cronjob / external scheduler.
// It updates configured channels with TS + Steam status descriptions.
// Protect it with a token (channel_description_updater_token).

define("DISABLE_CSRF_CHECK", true);

use Wruczek\TSWebsite\ChannelDescriptionUpdater;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\ApiUtils;

require_once __DIR__ . "/../private/php/load.php";

$isCli = (php_sapi_name() === "cli");
$configuredToken = (string) Config::get("channel_description_updater_token", "");
$providedToken = (string) ($_GET["token"] ?? "");

if (!$isCli) {
    if ($configuredToken === "") {
        ApiUtils::jsonError("Updater token not configured", "TOKEN_NOT_CONFIGURED", 403);
        exit;
    }

    if ($providedToken === "" || !hash_equals($configuredToken, $providedToken)) {
        ApiUtils::jsonError("Invalid token", "INVALID_TOKEN", 403);
        exit;
    }
}

$updater = new ChannelDescriptionUpdater();
$result = $updater->updateAll();

ApiUtils::outputJson($result);

