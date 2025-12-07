<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\TemplateUtils;

require_once __DIR__ . "/../private/php/load.php";

if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You do not have access to this page.");
    exit;
}

// Fetch existing news posts
$newsStore = new \Wruczek\TSWebsite\News\DefaultNewsStore();
$newsList = $newsStore->getNewsList(50); // Get last 50 news posts

// Fetch FAQ items
$db = \Wruczek\TSWebsite\Utils\DatabaseUtils::i()->getDb();
$faqList = $db->select("faq", "*", ["ORDER" => ["id" => "ASC"]]);

$data = [
    "pagetitle" => "Admin Panel",
    "navActiveIndex" => 0,
    "assignerConfigJson" => json_encode(Config::get("assignerconfig") ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "adminStatusGroupsJson" => json_encode(Config::get("adminstatus_groups") ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "rulesHtml" => (string) (Config::get("rules_html") ?? "<p>Rules in <b>HTML</b></p>"),
    "config" => [
        "discord_login_webhook" => Config::get("discord_login_webhook", ""),
        "steam_api_key" => Config::get("steam_api_key", ""),
        "ts3_host" => Config::get("query_host", ""),
        "ts3_queryport" => Config::get("query_port", ""),
        "ts3_serverport" => Config::get("voice_port", ""),
        "ts3_displayip" => Config::get("query_displayip", ""),
        "ts3_username" => Config::get("query_username", ""),
        "ts3_password" => Config::get("query_password", ""),
    ],
    "newsList" => $newsList,
    "faqList" => $faqList,
];

TemplateUtils::i()->renderTemplate("admin", $data);

