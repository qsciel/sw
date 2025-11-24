<?php

declare(strict_types=1);

namespace Marquez\SkyWars\game;

/**
 * Enum for game modes
 */
enum GameMode: string {
    case SOLO = "solo";
    case DUOS = "duos";
    case SQUADS = "squads";

    /**
     * Get team size for this game mode
     */
    public function getTeamSize(): int {
        return match($this) {
            self::SOLO => 1,
            self::DUOS => 2,
            self::SQUADS => 4,
        };
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string {
        return match($this) {
            self::SOLO => "§aSolo",
            self::DUOS => "§bDuos",
            self::SQUADS => "§6Squads",
        };
    }

    /**
     * Get minimum players from config
     */
    public function getMinPlayers(): int {
        return 3; // Will be loaded from config in actual usage
    }

    /**
     * Get maximum players
     */
    public function getMaxPlayers(): int {
        return match($this) {
            self::SOLO => 12,
            self::DUOS => 16,
            self::SQUADS => 16,
        };
    }

    /**
     * Get from string value
     */
    public static function fromString(string $value): ?self {
        return match(strtolower($value)) {
            "solo" => self::SOLO,
            "duos" => self::DUOS,
            "squads" => self::SQUADS,
            default => null,
        };
    }
}
