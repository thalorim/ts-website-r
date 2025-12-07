<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\TemplateUtils;

require_once __DIR__ . "/../private/php/load.php";

// Check if user is logged in and has admin privileges
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

if (!Auth::isAdmin()) {
    http_response_code(403);
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You do not have permission to access the admin panel.");
    exit;
}

$data = [
    "pagetitle" => "Admin Panel",
    "navActiveIndex" => 0,
    "assignerConfigJson" => json_encode(Config::get("assignerconfig") ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "adminStatusGroupsJson" => json_encode(Config::get("adminstatus_groups") ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "rulesHtml" => (string) (Config::get("rules_html") ?? "<p>Rules in <b>HTML</b></p>"),
    "membersGroupsJson" => json_encode(Config::get("members_groups") ?? [6, 7], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "rankBadgeRangeJson" => json_encode(Config::get("rank_badge_range") ?? ["min" => 9, "max" => 18], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    "config" => Config::i()->getConfig(),
];

TemplateUtils::i()->renderTemplate("admin", $data);

