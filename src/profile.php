<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;

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
        $tsInfo = TeamSpeakUtils::i()->getTSNodeServer()->clientGetDbInfo($cldbid);
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
}

// Persist to DB (upsert)
try {
    if ($db->has("profiles", ["cldbid" => $cldbid])) {
        $db->update("profiles", $profileData, ["cldbid" => $cldbid]);
    } else {
        $db->insert("profiles", $profileData);
    }
} catch (\Exception $e) {
    // Non-fatal for rendering
}

// Prepare data for template
$renderData = [
    "title" => "Profile",
    "navActiveIndex" => 0,
    "isOnline" => $isOnline,
    "profile" => $profileData,
];

TemplateUtils::i()->renderTemplate("profile", $renderData);

