<?php

namespace Wruczek\TSWebsite;

use Wruczek\PhpFileCache\PhpFileCache;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;

class Assigner {

    public static function getAssignerConfig(): array {
        return Config::get("assignerconfig");
    }

    public static function getAssignerArray(): array {
        $assignerConfig = self::getAssignerConfig();

        if (empty($assignerConfig)) {
            return []; // not configured, do not get more data and just return an empty array
        }

        $userGroups = Auth::getUserServerGroupIds();
        $serverGroups = CacheManager::i()->getServerGroupList();

        foreach ($assignerConfig as $index => $category) {
            $assignedCount = 0;
            $groups = [];

            foreach ($category["groups"] as $sgid) {
                $serverGroup = @$serverGroups[$sgid];

                if ($serverGroup === null) {
                    continue;
                }

                $assigned = in_array($sgid, $userGroups);
                $groups[$sgid] = $serverGroup + ["assigned" => $assigned];

                if ($assigned) {
                    $assignedCount++;
                }
            }

            $assignerConfig[$index]["assignedCount"] = $assignedCount;
            $assignerConfig[$index]["groups"] = $groups;
        }

        return $assignerConfig;
    }

    public static function isAssignable(int $sgid): bool {
        foreach (self::getAssignerConfig() as $category) {
            if (in_array($sgid, $category["groups"], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempts to change user groups with the provided $newGroups
     * @param array $newGroups array of new SGIDs that the user should
     *              have. any assigner groups not present in this array will
     *              be removed from the user
     * @return int status code that shows result of the group change.
     *             0 - groups have been successfully changed
     *             1 - no change between current groups and submitted groups
     *             2 - group assigner is not configured, stopping
     *             3 - reached category group limit
     * @throws UserNotAuthenticatedException
     * @throws \TeamSpeak3_Exception
     */
    public static function changeGroups(array $newGroups): int {
        $assignerConfig = self::getAssignerConfig();

        if (empty($assignerConfig)) {
            return 2; // if the assigner is not configured, stop there
        }

        $userGroups = Auth::getUserServerGroupIds();
        $groupsToAdd = [];
        $groupsToRemove = [];

        foreach ($assignerConfig as $config) {
            $groupsToAssign = 0;

            foreach ($config["groups"] as $group) {
                // true if the $group is currently assigned to the user
                $isAssigned = in_array($group, $userGroups, true);
                // true if the user wants to be added to $group
                $wantToAssign = in_array($group, $newGroups, true);

                // if the group is already assigned, or is to be assigned,
                // check for the max group limit in this category:
                // - add 1 to the "groupsToAssign", and
                // - check if its bigger than the max limit
                if ($wantToAssign && (++$groupsToAssign > $config["max"])) {
                    return 3;
                }

                // ADD GROUP if the group is not assigned, but the user wants to be added
                if (!$isAssigned && $wantToAssign) {
                    // ok, seems like we can add this group!
                    $groupsToAdd[] = $group;
                }

                // REMOVE GROUP if the group is currently assigned, but the user does not want to have it
                if ($isAssigned && !$wantToAssign) {
                    $groupsToRemove[] = $group;
                }
            }
        }

        // empty arrays - nothing to change
        if (!$groupsToAdd && !$groupsToRemove) {
            return 1;
        }

        // finally, add or remove the groups
        $tsServer = TeamSpeakUtils::i()->getTSNodeServer();

        foreach ($groupsToAdd as $sgid) {
            try {
                $tsServer->serverGroupClientAdd($sgid, Auth::getCldbid());
            } catch (\TeamSpeak3_Exception $e) {} // TODO log it to the admin panel?
        }

        foreach ($groupsToRemove as $sgid) {
            try {
                $tsServer->serverGroupClientDel($sgid, Auth::getCldbid());
            } catch (\TeamSpeak3_Exception $e) {} // TODO log it to the admin panel?
        }

        // Send Discord webhook for group changes
        if (!empty($groupsToAdd) || !empty($groupsToRemove)) {
            self::sendDiscordGroupWebhook($groupsToAdd, $groupsToRemove);
        }

        return 0;
    }

    public static function getRequiredSgids(): array {
        return Config::get("assigner_required_sgids");
    }

    public static function canUseAssigner(): bool {
        // if there are no required sgids, the user can use assigner and
        // we can skip the other, more expensive checks that will require
        // fetching user groups from TS3
        if (empty(self::getRequiredSgids())) {
            return true;
        }

        $userGroups = Auth::getUserServerGroupIds();
        return self::canUseAssignerSgArray($userGroups);
    }

    public static function canUseAssignerSgArray(array $serverGroups): bool {
        // user needs to be in at least one of those groups to be able
        // to use group assigner
        $requiredSgid = self::getRequiredSgids();

        foreach ($requiredSgid as $sgid) {
            if (in_array($sgid, $serverGroups, false)) {
                return true;
            }
        }

        return false;
    }

    private static function getCache(): PhpFileCache {
        return new PhpFileCache(__CACHE_DIR, "assigner");
    }

    public static function getCooldownSeconds(): int {
        return Config::get("assigner_cooldown_seconds");
    }

    /**
     * Checks if current user is on assigner cooldown.
     * @return int assigner cooldown remaining in seconds, or 0 if no cooldown exists
     * @throws UserNotAuthenticatedException
     */
    public static function getCooldownSecondsRemaining(): int {
        $cooldownSeconds = self::getCooldownSeconds();

        // cooldown of 0 or less = no cooldown
        if ($cooldownSeconds <= 0) {
            return 0;
        }

        $cacheKey = "last_use_" . Auth::getCldbid();
        $lastUseTimestamp = self::getCache()->retrieve($cacheKey);
        return max(0, $cooldownSeconds - (time() - $lastUseTimestamp));
    }

    /**
     * Set current user's last assigner use time. Used to check cooldown.
     * @throws UserNotAuthenticatedException
     */
    public static function updateLastUseTime(): void {
        $cooldownSeconds = self::getCooldownSeconds();

        // cooldown of 0 or less = no cooldown
        if ($cooldownSeconds <= 0) {
            return;
        }

        $cacheKey = "last_use_" . Auth::getCldbid();
        self::getCache()->store($cacheKey, time(), $cooldownSeconds);
    }

    /**
     * Sends Discord webhook notification for group changes
     */
    private static function sendDiscordGroupWebhook(array $groupsAdded, array $groupsRemoved): void {
        $webhook = (string) Config::get("discord_login_webhook", "");
        if ($webhook === "") {
            return;
        }

        $cldbid = Auth::getCldbid();
        $nickname = Auth::getNickname() ?: ("User #" . $cldbid);
        $serverGroups = CacheManager::i()->getServerGroupList();
        
        $addedNames = [];
        $removedNames = [];
        $isRegistered = false;
        
        foreach ($groupsAdded as $sgid) {
            if (isset($serverGroups[$sgid])) {
                $addedNames[] = $serverGroups[$sgid]['name'];
            }
            if ($sgid === 9) {
                $isRegistered = true;
            }
        }
        
        foreach ($groupsRemoved as $sgid) {
            if (isset($serverGroups[$sgid])) {
                $removedNames[] = $serverGroups[$sgid]['name'];
            }
        }

        $timestamp = date('c');
        $description = '**' . $nickname . '** modified their server groups.';
        
        if ($isRegistered) {
            $description = '🎉 **' . $nickname . '** just registered and received the **Registered** group!';
        }

        $fields = [
            ['name' => '👤 User', 'value' => $nickname, 'inline' => true],
            ['name' => '🆔 CLDBID', 'value' => (string) $cldbid, 'inline' => true],
        ];

        if (!empty($addedNames)) {
            $fields[] = ['name' => '➕ Groups Added', 'value' => implode(', ', $addedNames), 'inline' => false];
        }

        if (!empty($removedNames)) {
            $fields[] = ['name' => '➖ Groups Removed', 'value' => implode(', ', $removedNames), 'inline' => false];
        }

        $embed = [
            'title' => $isRegistered ? '🎊 New Registration' : '🛡️ Group Assignment',
            'description' => $description,
            'color' => $isRegistered ? 0xffc107 : 0x17a2b8, // Yellow for registration, Cyan for regular
            'fields' => $fields,
            'footer' => [
                'text' => 'Group Assigner',
                'icon_url' => 'https://cdn-icons-png.flaticon.com/512/1828/1828911.png'
            ],
            'timestamp' => $timestamp,
        ];

        $payload = json_encode([
            'username' => 'TS-Website Groups',
            'avatar_url' => 'https://cdn-icons-png.flaticon.com/512/1828/1828911.png',
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

}
