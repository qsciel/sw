<?php

declare(strict_types=1);

namespace Marquez\SkyWars\arena;

use pocketmine\world\Position;
use pocketmine\Server;
use Marquez\SkyWars\game\GameMode;

/**
 * Represents a SkyWars arena with all configuration
 */
class Arena {

    /**
     * @param string $name Unique arena identifier
     * @param string $displayName Display name for UI
     * @param string $worldName World folder name
     * @param array<int, array{x: float, y: float, z: float}> $cages Spawn cages/positions
     * @param array<int, array{x: float, y: float, z: float}> $chestLocations Chest spawn locations
     * @param array{x: float, y: float, z: float} $spectatorSpawn Spectator spawn point
     * @param array<string> $supportedModes Supported game modes (solo, duos, squads)
     * @param bool $isEnabled Whether the arena is enabled
     */
    public function __construct(
        private string $name,
        private string $displayName,
        private string $worldName,
        private array $cages = [],
        private array $chestLocations = [],
        private array $spectatorSpawn = ['x' => 0, 'y' => 100, 'z' => 0],
        private array $supportedModes = [],
        private bool $isEnabled = true
    ) {}

    /**
     * Get arena name
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string {
        return $this->displayName;
    }

    /**
     * Set display name
     */
    public function setDisplayName(string $displayName): void {
        $this->displayName = $displayName;
    }

    /**
     * Get world name
     */
    public function getWorldName(): string {
        return $this->worldName;
    }

    /**
     * Get all cage positions
     * 
     * @return array<int, array{x: float, y: float, z: float}>
     */
    public function getCages(): array {
        return $this->cages;
    }

    /**
     * Add a cage position
     */
    public function addCage(float $x, float $y, float $z): void {
        $this->cages[] = ['x' => $x, 'y' => $y, 'z' => $z];
    }

    /**
     * Get cage as Position object
     */
    public function getCagePosition(int $index): ?Position {
        if (!isset($this->cages[$index])) {
            return null;
        }

        $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
        if ($world === null) {
            return null;
        }

        $cage = $this->cages[$index];
        return new Position($cage['x'], $cage['y'], $cage['z'], $world);
    }

    /**
     * Get number of cages
     */
    public function getCageCount(): int {
        return count($this->cages);
    }

    /**
     * Get chest locations
     * 
     * @return array<int, array{x: float, y: float, z: float}>
     */
    public function getChestLocations(): array {
        return $this->chestLocations;
    }

    /**
     * Add chest location
     */
    public function addChestLocation(float $x, float $y, float $z): void {
        $this->chestLocations[] = ['x' => $x, 'y' => $y, 'z' => $z];
    }

    /**
     * Get spectator spawn position
     */
    public function getSpectatorSpawn(): Position {
        $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
        if ($world === null) {
            throw new \RuntimeException("World {$this->worldName} not loaded");
        }

        return new Position(
            $this->spectatorSpawn['x'],
            $this->spectatorSpawn['y'],
            $this->spectatorSpawn['z'],
            $world
        );
    }

    /**
     * Set spectator spawn
     */
    public function setSpectatorSpawn(float $x, float $y, float $z): void {
        $this->spectatorSpawn = ['x' => $x, 'y' => $y, 'z' => $z];
    }

    /**
     * Get supported game modes
     * 
     * @return array<string>
     */
    public function getSupportedModes(): array {
        return $this->supportedModes;
    }

    /**
     * Set supported game modes
     * 
     * @param array<string> $modes
     */
    public function setSupportedModes(array $modes): void {
        $this->supportedModes = $modes;
    }

    /**
     * Check if arena supports a game mode
     */
    public function supportsMode(GameMode $mode): bool {
        return in_array($mode->value, $this->supportedModes, true);
    }

    /**
     * Add supported mode
     */
    public function addSupportedMode(string $mode): void {
        if (!in_array($mode, $this->supportedModes, true)) {
            $this->supportedModes[] = $mode;
        }
    }

    /**
     * Check if arena is enabled
     */
    public function isEnabled(): bool {
        return $this->isEnabled;
    }

    /**
     * Set enabled state
     */
    public function setEnabled(bool $enabled): void {
        $this->isEnabled = $enabled;
    }

    /**
     * Validate arena configuration
     */
    public function isValid(): bool {
        // Must have at least 4 cages
        if (count($this->cages) < 4) {
            return false;
        }

        // Must support at least one game mode
        if (empty($this->supportedModes)) {
            return false;
        }

        // World must be loaded
        $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
        if ($world === null) {
            return false;
        }

        return true;
    }

    /**
     * Serialize to array for saving
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'displayName' => $this->displayName,
            'worldName' => $this->worldName,
            'cages' => $this->cages,
            'chestLocations' => $this->chestLocations,
            'spectatorSpawn' => $this->spectatorSpawn,
            'supportedModes' => $this->supportedModes,
            'isEnabled' => $this->isEnabled,
        ];
    }

    /**
     * Deserialize from array
     * 
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        return new self(
            $data['name'] ?? '',
            $data['displayName'] ?? '',
            $data['worldName'] ?? '',
            $data['cages'] ?? [],
            $data['chestLocations'] ?? [],
            $data['spectatorSpawn'] ?? ['x' => 0, 'y' => 100, 'z' => 0],
            $data['supportedModes'] ?? [],
            $data['isEnabled'] ?? true
        );
    }
}
