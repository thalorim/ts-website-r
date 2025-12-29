<?php

namespace Wruczek\TSWebsite\Utils;

class NicknameStyleUtils {
    
    use SingletonTait;

    private const DEFAULT_KEY = "none";

    public static function getDefaultKey(): string {
        return self::DEFAULT_KEY;
    }

    public static function normalize(?string $key): string {
        if ($key === null || $key === "") {
            return self::DEFAULT_KEY;
        }

        $key = strtolower(trim($key));
        $valid = self::getOptions();

        return isset($valid[$key]) ? $key : self::DEFAULT_KEY;
    }

    public static function getClass(string $key): string {
        $key = self::normalize($key);
        return $key === "none" ? "" : "nickname-style-{$key}";
    }

    public static function getOptions(): array {
        return [
            "none" => [
                "label" => "No Style",
                "description" => "Default appearance",
            ],
            "gradient-fire" => [
                "label" => "Fire Gradient",
                "description" => "Vibrant red to orange gradient",
            ],
            "gradient-ocean" => [
                "label" => "Ocean Gradient",
                "description" => "Cool blue ocean gradient",
            ],
            "gradient-sunset" => [
                "label" => "Sunset Gradient",
                "description" => "Purple to pink sunset",
            ],
            "gradient-gold" => [
                "label" => "Gold Gradient",
                "description" => "Luxurious golden shine",
            ],
            "gradient-purple" => [
                "label" => "Purple Gradient",
                "description" => "Deep purple to violet",
            ],
            "gradient-rainbow" => [
                "label" => "Rainbow Gradient",
                "description" => "Full rainbow spectrum",
            ],
            "animated-sparkle" => [
                "label" => "Animated Sparkle",
                "description" => "Sparkling animated background",
            ],
            "cyan-crystal" => [
                "label" => "Cyan Crystal",
                "description" => "Cyan with crystal effect",
            ],
            "green-glow" => [
                "label" => "Green Glow",
                "description" => "Neon green glow effect",
            ],
        ];
    }
}
