<?php

use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/private/php/load.php";

$data = [
    "pagetitle" => __get("RULES_TITLE"),
    "navActiveIndex" => 4,
    "paneltitle" => '<i class="fas fa-book"></i>' . __get("RULES_PANEL_TITLE"),
    "panelcontent" => (string) (Config::get("rules_html") ?? "Rules in <b>HTML</b>")
];

TemplateUtils::i()->renderTemplate("simple-page", $data);
