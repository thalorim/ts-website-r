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

$data = [
    "pagetitle" => "Admin Panel",
    "navActiveIndex" => 0,
    "assignerConfigJson" => json_encode(Config::get("assignerconfig") ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "adminStatusGroupsJson" => json_encode(Config::get("adminstatus_groups") ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "rulesHtml" => (string) (Config::get("rules_html") ?? "<p>Rules in <b>HTML</b></p>"),
    "newsList" => $newsList,
];

TemplateUtils::i()->renderTemplate("admin", $data);

