<?php

// Local configuration overrides.
// Return key => value pairs. Values should be proper PHP types.
return [
    // Minimal assigner config with a single category using group ID 7
    'assignerconfig' => [
        [
            'name' => 'Local Assigner',
            'max' => 1,
            'groups' => [7],
        ],
    ],

    // Admin status will display members of server group ID 6
    'adminstatus_groups' => [6],

    // Steam Web API Key - Get yours from: https://steamcommunity.com/dev/apikey
    // Example: 'steam_api_key' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
    'steam_api_key' => '',

    // ========================================
    // TeamSpeak 3 Server Connection Settings
    // ========================================
    
    // ServerQuery host (IP or hostname)
    'query_host' => '127.0.0.1',
    
    // ServerQuery port (default: 10011)
    'query_port' => 10011,
    
    // Voice server port (default: 9987)
    'voice_port' => 9987,
    
    // Public IP/domain that clients will use to connect
    'query_displayip' => 'ts.example.com',
    
    // ServerQuery login username (usually "serveradmin")
    'query_username' => 'serveradmin',
    
    // ServerQuery login password
    'query_password' => '',
];

