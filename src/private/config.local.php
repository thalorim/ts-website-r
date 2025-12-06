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

    // CLDBIDs allowed to edit Website & TeamSpeak connectivity data via edit-profile.php.
    // Replace 3 with your actual CLDBID(s).
    'connectivity_admin_cldbids' => [3],
];

