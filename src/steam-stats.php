<?php

use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;

require_once __DIR__ . "/private/php/load.php";

$db = DatabaseUtils::i()->getDb();

// Get Steam API key from config
$steamApiKey = Config::get('steam_api_key', '');

if (empty($steamApiKey)) {
    TemplateUtils::i()->renderErrorTemplate(
        '500',
        'Configuration Error',
        'Steam API key is not configured. Please add "steam_api_key" to your config.local.php file.'
    );
    exit;
}

// ====================================================================
// MANUAL STEAM IDS - Add your Steam IDs here
// ====================================================================
// Format: ['nickname' => 'Display Name', 'steamId' => 'Steam ID or URL', 'cldbid' => profile_id]
// 
// Parameters:
//   - nickname: The display name shown on the card (required)
//   - steamId: Steam ID or URL in any format (required)
//   - cldbid: TeamSpeak profile ID for linking to profile.php, use 0 for no link (optional)
//
// Supported Steam ID formats:
//   - Direct Steam ID (64-bit): '76561198123456789'
//   - Profile URL: 'https://steamcommunity.com/profiles/76561198123456789'
//   - Custom URL: 'https://steamcommunity.com/id/yourusername'
//
// Examples:
$manualSteamProfiles = [
    // Uncomment and edit these examples:
    // ['nickname' => 'John Doe', 'steamId' => '76561198123456789', 'cldbid' => 123],
    // ['nickname' => 'Jane Smith', 'steamId' => 'https://steamcommunity.com/id/janesmith', 'cldbid' => 0],
    // ['nickname' => 'Roshke', 'steamId' => 'https://steamcommunity.com/id/Roshkeee', 'cldbid' => 456],
];
// ====================================================================

// Process manual Steam profiles
$steamProfiles = [];
foreach ($manualSteamProfiles as $manual) {
    if (!empty($manual['steamId'])) {
        $steamId = extractSteamId($manual['steamId']);
        if ($steamId) {
            $steamProfiles[] = [
                'cldbid' => isset($manual['cldbid']) ? (int) $manual['cldbid'] : 0,
                'nickname' => isset($manual['nickname']) ? $manual['nickname'] : 'Unknown Player',
                'steamId' => $steamId
            ];
        }
    }
}

// Fetch all profiles from database that have Steam links
try {
    $rows = $db->select("profiles", ["cldbid", "nickname", "steam", "socials_json"], ["ORDER" => ["nickname" => "ASC"]]);
    
    foreach ($rows as $r) {
        $steamId = null;
        
        // Try to get Steam ID from socials_json column
        if (!empty($r['socials_json'])) {
            $socialData = json_decode($r['socials_json'], true);
            if (is_array($socialData) && !empty($socialData['steam'])) {
                $steamId = extractSteamId($socialData['steam']);
            }
        }
        
        // Fallback to steam column if exists
        if (!$steamId && !empty($r['steam'])) {
            $steamId = extractSteamId($r['steam']);
        }
        
        if ($steamId) {
            $steamProfiles[] = [
                'cldbid' => (int) $r['cldbid'],
                'nickname' => $r['nickname'] ?: ('User #' . $r['cldbid']),
                'steamId' => $steamId
            ];
        }
    }
} catch (\Exception $e) {
    // Continue with manual profiles only
}

// Function to extract Steam ID from various Steam URL formats
function extractSteamId($input) {
    if (empty($input)) {
        return null;
    }
    
    // If it's already a Steam ID (numeric, 17 digits)
    if (preg_match('/^[0-9]{17}$/', $input)) {
        return $input;
    }
    
    // Extract from Steam profile URLs
    // https://steamcommunity.com/profiles/76561198123456789
    if (preg_match('/steamcommunity\.com\/profiles\/([0-9]{17})/', $input, $matches)) {
        return $matches[1];
    }
    
    // https://steamcommunity.com/id/customurl - we can't convert custom URLs without API call
    // For now, we'll try to resolve it via API
    if (preg_match('/steamcommunity\.com\/id\/([^\/\?]+)/', $input, $matches)) {
        return 'vanity:' . $matches[1]; // Mark as vanity URL for later resolution
    }
    
    return null;
}

// Function to resolve vanity URL to Steam ID
function resolveVanityUrl($vanityUrl, $apiKey) {
    $url = "https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?key=" . urlencode($apiKey) . "&vanityurl=" . urlencode($vanityUrl);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['response']['success']) && $data['response']['success'] == 1) {
            return $data['response']['steamid'];
        }
    }
    
    return null;
}

// Resolve vanity URLs
foreach ($steamProfiles as &$profile) {
    if (strpos($profile['steamId'], 'vanity:') === 0) {
        $vanityUrl = substr($profile['steamId'], 7);
        $resolvedId = resolveVanityUrl($vanityUrl, $steamApiKey);
        if ($resolvedId) {
            $profile['steamId'] = $resolvedId;
        } else {
            $profile['steamId'] = null; // Failed to resolve
        }
    }
}
unset($profile);

// Remove profiles with invalid Steam IDs
$steamProfiles = array_filter($steamProfiles, function($p) {
    return $p['steamId'] !== null && !strpos($p['steamId'], 'vanity:');
});

// Fetch Steam data for all profiles
$steamData = [];
if (!empty($steamProfiles)) {
    $steamIds = array_column($steamProfiles, 'steamId');
    $steamIdsStr = implode(',', $steamIds);
    
    // Fetch player summaries
    $playerSummaries = [];
    $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key=" . urlencode($steamApiKey) . "&steamids=" . urlencode($steamIdsStr);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['response']['players'])) {
            foreach ($data['response']['players'] as $player) {
                $playerSummaries[$player['steamid']] = $player;
            }
        }
    }
    
    // Fetch Steam levels for all users
    $steamLevels = [];
    foreach ($steamProfiles as $profile) {
        $steamId = $profile['steamId'];
        $levelUrl = "https://api.steampowered.com/IPlayerService/GetSteamLevel/v1/?key=" . urlencode($steamApiKey) . "&steamid=" . urlencode($steamId);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $levelUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['response']['player_level'])) {
                $steamLevels[$steamId] = (int) $data['response']['player_level'];
            }
        }
    }
    
    // Fetch games data for each profile
    foreach ($steamProfiles as $profile) {
        $steamId = $profile['steamId'];
        
        // Get owned games
        $gamesUrl = "https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/?key=" . urlencode($steamApiKey) . "&steamid=" . urlencode($steamId) . "&include_appinfo=1&include_played_free_games=1";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gamesUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $games = [];
        $totalPlaytime = 0;
        $mostPlayedGame = null;
        
        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['response']['games']) && is_array($data['response']['games'])) {
                $games = $data['response']['games'];
                
                // Calculate total playtime
                foreach ($games as $game) {
                    $totalPlaytime += isset($game['playtime_forever']) ? $game['playtime_forever'] : 0;
                }
                
                // Find most played game
                if (!empty($games)) {
                    usort($games, function($a, $b) {
                        $aTime = isset($a['playtime_forever']) ? $a['playtime_forever'] : 0;
                        $bTime = isset($b['playtime_forever']) ? $b['playtime_forever'] : 0;
                        return $bTime - $aTime;
                    });
                    
                    if (isset($games[0]) && isset($games[0]['playtime_forever']) && $games[0]['playtime_forever'] > 0) {
                        $mostPlayedGame = $games[0];
                    }
                }
            }
        }
        
        // Get recently played games
        $recentUrl = "https://api.steampowered.com/IPlayerService/GetRecentlyPlayedGames/v1/?key=" . urlencode($steamApiKey) . "&steamid=" . urlencode($steamId) . "&count=3";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $recentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $recentGames = [];
        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['response']['games']) && is_array($data['response']['games'])) {
                $recentGames = array_slice($data['response']['games'], 0, 3);
            }
        }
        
        $steamData[$steamId] = [
            'cldbid' => $profile['cldbid'],
            'nickname' => $profile['nickname'],
            'steamId' => $steamId,
            'playerInfo' => isset($playerSummaries[$steamId]) ? $playerSummaries[$steamId] : null,
            'totalPlaytime' => $totalPlaytime,
            'mostPlayedGame' => $mostPlayedGame,
            'recentGames' => $recentGames,
            'gameCount' => count($games),
            'steamLevel' => isset($steamLevels[$steamId]) ? $steamLevels[$steamId] : 0
        ];
    }
}

// Sort by total playtime descending
uasort($steamData, function($a, $b) {
    return $b['totalPlaytime'] - $a['totalPlaytime'];
});

TemplateUtils::i()->renderTemplate("steam-stats", [
    "steamData" => array_values($steamData),
    "navActiveIndex" => 9
]);
