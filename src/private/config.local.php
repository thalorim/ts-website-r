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
];

