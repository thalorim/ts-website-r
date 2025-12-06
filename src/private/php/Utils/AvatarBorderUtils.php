<?php

namespace Wruczek\TSWebsite\Utils;

/**
 * Provides the available avatar border presets and helper utilities.
 */
class AvatarBorderUtils {

    private const DEFAULT_KEY = 'radiant-vortex';

    /**
     * @var array<string, array{label: string, url: string, description: string}>
     */
    private const BORDER_OPTIONS = [
        'radiant-vortex' => [
            'label' => 'Radiant Vortex',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/1145350/7e753023f517ba01d4fd5d4031d69addb68751f5.png',
            'description' => 'Default purple lightning overlay featured on all avatars.',
        ],
        'ember-crown' => [
            'label' => 'Ember Crown',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/1145350/cca6167155dbaef1e10dab7e5af123875b995078.png',
            'description' => 'Fiery orange crown with molten glow.',
        ],
        'neon-rift' => [
            'label' => 'Neon Rift',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/1676840/05ee638859c560a8ffd09664debfe41d491aa9f3.png',
            'description' => 'Cyber teal aura with crystalline shards.',
        ],
        'webbed-pulse' => [
            'label' => 'Webbed Pulse',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/579720/053b984a253e1644cc8936a337d52c774c7d86bf.png',
            'description' => 'Neon web that adds extra motion.',
        ],
    ];

    /**
     * Returns all available border presets keyed by their identifier.
     *
     * @return array<string, array{label: string, url: string, description: string}>
     */
    public static function getOptions(): array {
        return self::BORDER_OPTIONS;
    }

    /**
     * Returns the canonical preset key for a user selection.
     */
    public static function normalize(?string $key): string {
        if ($key !== null && isset(self::BORDER_OPTIONS[$key])) {
            return $key;
        }

        return self::DEFAULT_KEY;
    }

    /**
     * Returns metadata for a given preset (defaults when missing).
     *
     * @return array{label: string, url: string, description: string}
     */
    public static function getOption(?string $key): array {
        $normalized = self::normalize($key);
        return self::BORDER_OPTIONS[$normalized];
    }

    /**
     * Returns the image URL for the given preset (defaults when missing).
     */
    public static function getUrl(?string $key): string {
        $option = self::getOption($key);
        return $option["url"];
    }

    /**
     * Exposed default key for external defaults.
     */
    public static function getDefaultKey(): string {
        return self::DEFAULT_KEY;
    }
}
