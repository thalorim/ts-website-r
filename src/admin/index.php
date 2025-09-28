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

$data = [
    "pagetitle" => "Admin Panel",
    "navActiveIndex" => 0,
    "assignerConfigJson" => json_encode(Config::get("assignerconfig") ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "adminStatusGroupsJson" => json_encode(Config::get("adminstatus_groups") ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "rulesHtml" => (string) (Config::get("rules_html") ?? "<p>Rules in <b>HTML</b></p>"),
];

TemplateUtils::i()->renderTemplate("admin", $data);

