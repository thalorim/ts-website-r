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

    // -------------------------------
    // Channel Description Updater (TS + Steam)
    // -------------------------------
    //
    // Steam Web API key (used for avatar/name/status/current game)
    // Create one here: https://steamcommunity.com/dev/apikey
    'steam_api_key' => '',

    // Protect /api/update-channel-descriptions.php with a token
    // Call: /api/update-channel-descriptions.php?token=YOUR_TOKEN
    'channel_description_updater_token' => '',

    // Optional: cache time for Steam profile fetches (seconds)
    'cache_channel_description_updater_steam' => 60,

    // Map channels to TeamSpeak clients + Steam profiles.
    // - channel_id: TeamSpeak channel ID to update
    // - cldbid: TeamSpeak client database ID (stable identity)
    // - steamid64: SteamID64 for Steam profile (optional)
    // - title: description title (optional)
    // - label: label for logs (optional)
    // - show_updated_at: include "Updated: ..." line (optional)
    'channel_description_updater_targets' => [
        // Example:
        // [
        //     'label' => 'rxnat',
        //     'title' => 'Description',
        //     'channel_id' => 1544,
        //     'cldbid' => 123,
        //     'steamid64' => '7656119XXXXXXXXXX',
        //     'show_updated_at' => true,
        // ],
    ],

    // Legacy-style config (supported) — uses cldbid/steamid64/channel_id as requested:
    // 'function' => [
    //     'clientstatus' => [
    //         'enable' => true,
    //         'aalgroup' => [6, 30],
    //         'steamstatus' => true,
    //         'steamapi' => '',
    //         'info' => [
    //             1 => ['cldbid' => 2, 'channel_id' => 184, 'steamid64' => '76561198101162681'],
    //         ],
    //         'channelname' => "[cspacer]◥◣━[RANG]┃[NICK]┃[STATUS]━◢◤",
    //         'interval' => ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 10],
    //         'interval2' => ['days' => 0, 'hours' => 0, 'minutes' => 2, 'seconds' => 0],
    //     ],
    // ],
];

