<?php

namespace Wruczek\TSWebsite\Utils;

/**
 * Discord utility class to fetch Discord user presence data
 */
class DiscordUtils {
    use SingletonTait;

    /**
     * Fetch Discord user data using Lanyard API
     * @param string $discordId The Discord user ID
     * @return array|null User data or null if failed
     */
    public function getUserData($discordId) {
        if (empty($discordId) || !preg_match('/^\d{17,19}$/', $discordId)) {
            return null;
        }

        try {
            $url = "https://api.lanyard.rest/v1/users/" . urlencode($discordId);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'TSWebsite-Discord-Widget/1.0');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['success']) || !$data['success']) {
                return null;
            }
            
            if (!isset($data['data'])) {
                return null;
            }
            
            $userData = $data['data'];
            
            // Extract and format the data we need
            $result = [
                'discord_user' => $userData['discord_user'] ?? [],
                'activities' => $userData['activities'] ?? [],
                'discord_status' => $userData['discord_status'] ?? 'offline',
                'active_on_discord_mobile' => $userData['active_on_discord_mobile'] ?? false,
                'active_on_discord_desktop' => $userData['active_on_discord_desktop'] ?? false,
            ];
            
            // Parse user info
            if (isset($result['discord_user'])) {
                $user = $result['discord_user'];
                $result['username'] = $user['username'] ?? 'Unknown';
                $result['global_name'] = $user['global_name'] ?? $result['username'];
                $result['discriminator'] = $user['discriminator'] ?? '0';
                $result['avatar_hash'] = $user['avatar'] ?? null;
                
                // Build avatar URL
                if ($result['avatar_hash']) {
                    $ext = (strpos($result['avatar_hash'], 'a_') === 0) ? 'gif' : 'png';
                    $result['avatar_url'] = "https://cdn.discordapp.com/avatars/{$discordId}/{$result['avatar_hash']}.{$ext}?size=128";
                } else {
                    // Default avatar based on discriminator
                    $defaultNum = ($result['discriminator'] === '0') ? 
                        (intval($discordId) >> 22) % 6 : 
                        intval($result['discriminator']) % 5;
                    $result['avatar_url'] = "https://cdn.discordapp.com/embed/avatars/{$defaultNum}.png";
                }
            }
            
            // Format status text and CSS class
            $result['status_text'] = ucfirst($result['discord_status']);
            $result['status_color'] = $this->getStatusColor($result['discord_status']);
            $result['status_class'] = $this->getStatusClass($result['discord_status']);
            
            return $result;
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get status color for display
     * @param string $status Discord status
     * @return string CSS color class
     */
    private function getStatusColor($status) {
        switch ($status) {
            case 'online':
                return '#43b581'; // Green
            case 'idle':
                return '#faa61a'; // Orange
            case 'dnd':
                return '#f04747'; // Red
            case 'offline':
            default:
                return '#747f8d'; // Gray
        }
    }
    
    /**
     * Get status CSS class for display
     * @param string $status Discord status
     * @return string CSS class name
     */
    private function getStatusClass($status) {
        switch ($status) {
            case 'online':
                return 'discord-online';
            case 'idle':
                return 'discord-idle';
            case 'dnd':
                return 'discord-dnd';
            case 'offline':
            default:
                return 'discord-offline';
        }
    }
}
