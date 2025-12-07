<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

// Check if user is logged in and has admin privileges
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "unauthorized", "message" => "You must be logged in"]);
    exit;
}

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden", "message" => "Admin access required"]);
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

