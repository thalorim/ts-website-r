<?php

namespace Wruczek\TSWebsite;

use Wruczek\PhpFileCache\PhpFileCache;
use Wruczek\TSWebsite\Utils\Language\LanguageUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\TSWebsite\Utils\Utils;

class Auth {

    public static function isLoggedIn() {
        return self::getCldbid() !== null && self::getUid() !== null;
    }

    public static function getUid(): ?string {
        return @$_SESSION["tsuser"]["uid"];
    }

    public static function getCldbid(): ?int {
        return @$_SESSION["tsuser"]["cldbid"];
    }

    public static function getNickname(): ?string {
        return @$_SESSION["tsuser"]["nickname"];
    }

    public static function logout(): void {
        unset($_SESSION["tsuser"]);
    }

    public static function getTsUsersByIp(string $ip = null): ?array {
        if ($ip === null) {
            $ip = Utils::getClientIp();
        }

        $clientList = CacheManager::i()->getClientList();

        if ($clientList === null) {
            return null;
        }

        $ret = [];

        foreach ($clientList as $client) {
            // Skip query clients
            if ($client["client_type"]) continue;

            $clientIp = (string) $client["connection_client_ip"];

            if ($clientIp === $ip) {
                $ret[$client["client_database_id"]] = (string) $client["client_nickname"];
            }
        }

        return $ret;
    }

    /**
     * Returns true if the $cldbid is connected with the same IP address as $ip
     * @param $cldbid int cldbid to check
     * @param $ip string optional, defaults to Utils::getClientIp
     * @return bool true if the cldbid have the same IP address as $ip
     */
    public static function checkClientIp(int $cldbid, string $ip = null): bool {
        if ($ip === null) {
            $ip = Utils::getClientIp();
        }

        $users = self::getTsUsersByIp($ip);

        if ($users === null) {
            return false;
        }

        return array_key_exists($cldbid, $users);
    }

    /**
     * Tries to generate and send confirmation code to the TS client
     * @param $cldbid int
     * @param $poke bool|null true = poke user, false = send a message, null = default value from config
     * @return string|null|false Returns code as string on success, null when
     *         client cannot be found and false when other error occurs.
     * @throws \TeamSpeak3_Adapter_ServerQuery_Exception
     */
    public static function generateConfirmationCode(int $cldbid, ?bool $poke = null) {
        if ($poke === null) {
            $poke = (bool) Config::get("loginpokeclient");
        }

        if (TeamSpeakUtils::i()->checkTSConnection()) {
            try {
                $client = TeamSpeakUtils::i()->getTSNodeServer()->clientGetByDbid($cldbid);
                $code = (string) Utils::getSecureRandomInt(100000, 999999);
                $msg = __get("LOGIN_CONFIRMATION_CODE", $code);

                if ($poke) {
                    $client->poke(mb_substr($msg, 0, 100)); // Max 100 characters for pokes
                } else {
                    $client->message(mb_substr($msg, 0, 1024)); // Max 1024 characters for messages
                }

                self::saveConfirmationCode($cldbid, $code);
                return $code;
            } catch (\TeamSpeak3_Adapter_ServerQuery_Exception $e) {
                if ($e->getCode() === 512) {
                    return null;
                }

                throw $e;
            } catch (\Exception $e) {
                // ignore exceptions from Utils::getSecureRandomInt
            }
        }

        return false;
    }

    /**
     * Checks if there is already a confirmation code cached for this user.
     * Returns the code of found, otherwise NULL.
     * @param $cldbid int
     * @return string|null Confirmation code, null if not found
     */
    public static function getConfirmationCode(int $cldbid): ?string {
        return (new PhpFileCache(__CACHE_DIR, "confirmationcodes"))->retrieve("c_$cldbid");
    }

    /**
     * Saves confirmation code for the user
     * @param $cldbid int
     * @param $code string
     */
    public static function saveConfirmationCode(int $cldbid, string $code): void {
        (new PhpFileCache(__CACHE_DIR, "confirmationcodes"))->store("c_$cldbid", $code, (int) Config::get("cache_logincode"));
    }

    /**
     * Deletes confirmation code for the user
     * @param $cldbid int
     */
    public static function deleteConfirmationCode(int $cldbid): void {
        (new PhpFileCache(__CACHE_DIR, "confirmationcodes"))->eraseKey("c_$cldbid");
    }

    /**
     * Checks confirmation code and logs user in if its correct.
     * @param $cldbid
     * @param $userCode
     * @return bool true if authentication was successful
     */
    public static function checkCodeAndLogin(int $cldbid, string $userCode): bool {
        if (!is_int($cldbid)) {
            throw new \InvalidArgumentException("cldbid must be an int");
        }

        $codeCheck = self::checkConfirmationCode($cldbid, $userCode);

        if ($codeCheck !== true) {
            return false;
        }

        $login = self::loginUser($cldbid);
        if ($login) {
            self::deleteConfirmationCode($cldbid);
            // Send Discord webhook if configured
            self::sendDiscordLoginWebhook($cldbid);
            return true;
        }

        return false;
    }

    /**
     * Sends a Discord webhook for a successful code login
     */
    private static function sendDiscordLoginWebhook(int $cldbid): void {
        $webhook = (string) Config::get("discord_login_webhook", "");
        if ($webhook === "") {
            return;
        }

        $nickname = self::getNickname() ?: ("User #" . $cldbid);
        $uid = self::getUid() ?: '';
        $ip = Utils::getClientIp(true);

        $country = null;
        $client = CacheManager::i()->getClient($cldbid);
        if ($client && !empty($client['client_country'])) {
            $country = (string) $client['client_country'];
        } else {
            // fallback to DB profile
            try {
                $db = Utils\DatabaseUtils::i()->getDb();
                $row = $db->get('profiles', ['country'], ['cldbid' => $cldbid]);
                if ($row && !empty($row['country'])) {
                    $country = (string) $row['country'];
                }
            } catch (\Exception $e) { /* ignore */ }
        }

        $cc = $country ? strtolower($country) : null;
        $flag = $cc ? (":flag_" . $cc . ":") : '';

        $timestamp = date('c');

        $content = null;
        $embed = [
            'title' => 'Website Login Verification',
            'color' => 0x1f4b6e,
            'fields' => [
                ['name' => 'Client', 'value' => $nickname . " (CLDBID: " . $cldbid . ")", 'inline' => true],
                ['name' => 'Country', 'value' => ($country ?: '-') . ' ' . $flag, 'inline' => true],
                ['name' => 'IP', 'value' => "`" . $ip . "`", 'inline' => false],
                ['name' => 'Unique ID', 'value' => ($uid ? ("`" . $uid . "`") : '-') , 'inline' => false],
            ],
            'timestamp' => $timestamp,
        ];

        $payload = json_encode([
            'content' => $content,
            'embeds' => [$embed],
        ]);

        try {
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 3,
                ],
            ];
            @file_get_contents($webhook, false, stream_context_create($opts));
        } catch (\Exception $e) {
            // ignore webhook errors
        }
    }

    /**
     * Checks if the provided confirmation code matches the saved one and returns true on success.
     * @param $cldbid int
     * @param $userCode string
     * @return bool
     */
    public static function checkConfirmationCode(int $cldbid, string $userCode): bool {
        $knownCode = self::getConfirmationCode($cldbid);

        if ($knownCode === null) {
            return false;
        }

        return hash_equals($knownCode, $userCode);
    }

    /**
     * Logins user to this account
     * @param $cldbid int
     * @return bool true on success, false otherwise
     */
    public static function loginUser(int $cldbid): bool {
        $clientList = CacheManager::i()->getClientList();

        foreach ($clientList as $client) {
            if ($client["client_database_id"] === $cldbid) {
                $_SESSION["tsuser"]["uid"] = (string) $client["client_unique_identifier"];
                $_SESSION["tsuser"]["cldbid"] = $client["client_database_id"];
                $_SESSION["tsuser"]["nickname"] = (string) $client["client_nickname"];
                return true;
            }
        }

        return false;
    }

    public static function invalidateUserGroupCache(): void {
        unset($_SESSION["tsuser"]["servergroups"]);
    }

    /**
     * Returns an array containing cached array with group IDs of the user
     * @param $cacheTime int for how long we should cache the IDs?
     * @return array array with server group IDs of the user as ints
     * @throws UserNotAuthenticatedException if user is not logged in
     * @throws \TeamSpeak3_Exception when we cannot get data from the TS server
     */
    public static function getUserServerGroupIds(int $cacheTime = 60): array {
        if (!self::isLoggedIn()) {
            throw new UserNotAuthenticatedException("User is not authenticated");
        }

        // Check if we data is already cached and if we can use it
        // no caching in dev mode
        if (isset($_SESSION["tsuser"]["servergroups"]) && !__DEV_MODE) {
            $cached = $_SESSION["tsuser"]["servergroups"];

            // Calculate how old is the cached data (in seconds)
            $secondsSinceCache = time() - $cached["timestamp"];

            // If we dont need to refresh it, return the data
            if ($secondsSinceCache <= $cacheTime) {
                return $cached["data"];
            }
        }

        // If we end up here, it means we need to refresh the cache

        if (!TeamSpeakUtils::i()->checkTSConnection()) {
            throw new \TeamSpeak3_Exception("Cannot connect to the TeamSpeak server");
        }

        try {
            $tsServer = TeamSpeakUtils::i()->getTSNodeServer();
            // Get all user groups from TS server
            $serverGroups = $tsServer->clientGetServerGroupsByDbid(self::getCldbid());
        } catch (\TeamSpeak3_Exception $e) {
            TeamSpeakUtils::i()->addExceptionToExceptionsList($e);
            throw $e;
        }

        // Since the array in indexed with server group ID's, we can just separate the keys
        // That gives us an array with ID's of user groups
        $serverGroupIds = array_keys($serverGroups);

        // Cache it in session with current time for later cachebusting
        $_SESSION["tsuser"]["servergroups"] = [
            "timestamp" => time(),
            "data" => $serverGroupIds
        ];

        return $serverGroupIds;
    }

    /**
     * Combines sever group ID's from getUserServerGroupIds() with cached
     * server group list and returns full array with user's server groups
     * @see self::getUserServerGroupIds
     * @param int $cacheTime value passed to getUserServerGroupIds()
     * @return array array with user server groups
     * @throws UserNotAuthenticatedException if user is not logged in
     * @throws \TeamSpeak3_Exception when we cannot get data from the TS server
     */
    public static function getUserServerGroups(int $cacheTime = 60): array {
        $serverGroupIds = self::getUserServerGroupIds($cacheTime);
        $serverGroups = CacheManager::i()->getServerGroupList();

        return array_filter($serverGroups, function (array $serverGroup) use ($serverGroupIds) {
            // If the group id is inside $serverGroupIds,
            // keep that group. Otherwise filter it out.
            return in_array($serverGroup["sgid"], $serverGroupIds, true);
        });
    }

    /**
     * Checks if the currently logged-in user is an admin based on configuration.
     * Supports both UID-based and server group-based authentication.
     * 
     * Configuration keys:
     * - admin_uids: array of allowed admin unique identifiers
     * - admin_cldbids: array of allowed admin client database IDs
     * - admin_groups: array of server group IDs that grant admin access
     * 
     * @return bool true if user is an admin, false otherwise
     */
    public static function isAdmin(): bool {
        if (!self::isLoggedIn()) {
            return false;
        }

        // Check UID-based admin access
        $adminUids = Config::get("admin_uids", []);
        if (!empty($adminUids) && is_array($adminUids)) {
            $currentUid = self::getUid();
            if ($currentUid && in_array($currentUid, $adminUids, true)) {
                return true;
            }
        }

        // Check CLDBID-based admin access
        $adminCldbids = Config::get("admin_cldbids", []);
        if (!empty($adminCldbids) && is_array($adminCldbids)) {
            $currentCldbid = self::getCldbid();
            if ($currentCldbid && in_array($currentCldbid, $adminCldbids, true)) {
                return true;
            }
        }

        // Check server group-based admin access
        $adminGroups = Config::get("admin_groups", []);
        if (!empty($adminGroups) && is_array($adminGroups)) {
            try {
                $userGroups = self::getUserServerGroupIds();
                foreach ($adminGroups as $adminGroupId) {
                    if (in_array($adminGroupId, $userGroups, true)) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                // If we can't get user groups, deny access
                return false;
            }
        }

        return false;
    }
}

class UserNotAuthenticatedException extends \Exception {}
