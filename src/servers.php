<?php

use Wruczek\TSWebsite\Utils\TemplateUtils;

require_once __DIR__ . "/private/php/load.php";

// List of server IDs to display
$serverIds = [10809819, 6320256, 679926];

// Fetch server data from the API
$servers = [];
foreach ($serverIds as $serverId) {
    $apiUrl = "https://api.gamemonitoring.net/servers/" . $serverId;
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['response'])) {
            $servers[] = $data['response'];
        }
    } else {
        // Add placeholder data if API fails
        $servers[] = [
            'id' => $serverId,
            'name' => 'Server Unavailable',
            'status' => false,
            'ip' => 'N/A',
            'port' => 0,
            'connect' => 'N/A',
            'numplayers' => 0,
            'maxplayers' => 0,
            'version' => 'Unknown',
            'country' => null,
            'app' => ['name' => 'Unknown'],
            'error' => true
        ];
    }
}

TemplateUtils::i()->renderTemplate("servers", [
    "servers" => $servers,
    "navActiveIndex" => 7
]);
