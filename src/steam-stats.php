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

// Fetch all profiles that have Steam links
$steamProfiles = [];
try {
    $rows = $db->select("profiles", ["cldbid", "nickname", "steam", "social_links"], ["ORDER" => ["nickname" => "ASC"]]);
    
    foreach ($rows as $r) {
        $steamId = null;
        
        // Try to get Steam ID from social_links JSON
        if (!empty($r['social_links'])) {
            $socialData = json_decode($r['social_links'], true);
            if (is_array($socialData) && !empty($socialData['steam'])) {
                $steamId = extractSteamId($socialData['steam']);
            }
        }
        
        // Fallback to steam column
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
    // Continue with empty list
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
            'gameCount' => count($games)
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
