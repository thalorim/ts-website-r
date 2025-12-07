<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getUid() !== "Jv/d1+pX7/q343RrIMPTTVpob+U=") {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

$rules = (string) (@$_POST["rules_html"] ?? '');

try {
    Config::i()->setValue("rules_html", $rules);
    echo json_encode(["ok" => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

