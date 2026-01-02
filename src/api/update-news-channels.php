<?php

// This endpoint updates configured channels with latest news.
// It can be called by a cronjob or external scheduler.
// Protect it with a token (news_channel_update_token in config).

define("DISABLE_CSRF_CHECK", true);

use Wruczek\TSWebsite\Utils\NewsChannelDisplayManager;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\ApiUtils;

require_once __DIR__ . "/../private/php/load.php";

$isCli = (php_sapi_name() === "cli");
$configuredToken = (string) Config::get("news_channel_update_token", "");
$providedToken = (string) ($_GET["token"] ?? "");

// If not running from CLI, require token authentication
if (!$isCli) {
    if ($configuredToken === "") {
        ApiUtils::jsonError("Update token not configured. Set 'news_channel_update_token' in your config.", "TOKEN_NOT_CONFIGURED", 403);
        exit;
    }

    if ($providedToken === "" || !hash_equals($configuredToken, $providedToken)) {
        ApiUtils::jsonError("Invalid or missing token", "INVALID_TOKEN", 403);
        exit;
    }
}

try {
    $manager = NewsChannelDisplayManager::i();
    $result = $manager->updateAllChannels();

    ApiUtils::outputJson([
        "success" => true,
        "timestamp" => time(),
        "result" => $result,
    ]);
} catch (\Exception $e) {
    ApiUtils::jsonError($e->getMessage(), "UPDATE_FAILED", 500);
}
