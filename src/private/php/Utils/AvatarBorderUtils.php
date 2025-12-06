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
        'golden-aura' => [
            'label' => 'Golden Aura',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/212070/9b6b26c7a03046da283408d72319f9eec932c80a.gif',
            'description' => 'Animated golden energy swirl.',
        ],
        'crimson-frame' => [
            'label' => 'Crimson Frame',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/1098340/71f42ec23a7f80c365f0c3900a6e61bdc78733d7.png',
            'description' => 'Deep red ornate border frame.',
        ],
        'azure-crown' => [
            'label' => 'Azure Crown',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/917950/4cfa3e9a5456b714823c1173d7ebd0615ae57910.png',
            'description' => 'Royal blue crown with elegant design.',
        ],
        'plasma-ring' => [
            'label' => 'Plasma Ring',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/212070/e39b802b3cff406590139dba9de470f31810027c.gif',
            'description' => 'Animated electric plasma border.',
        ],
        'emerald-shield' => [
            'label' => 'Emerald Shield',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/552990/b2beaf92e42ae82e116a97b7cd8f6cb54226792f.png',
            'description' => 'Green shield with protective aura.',
        ],
        'violet-halo' => [
            'label' => 'Violet Halo',
            'url' => 'https://shared.fastly.steamstatic.com/community_assets/images/items/1263950/ebe6b674deca163b28423e3b925bd36b0f0f357b.png',
            'description' => 'Purple halo with mystical glow.',
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
