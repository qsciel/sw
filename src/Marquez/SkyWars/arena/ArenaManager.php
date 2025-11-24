<?php

declare(strict_types=1);

namespace Marquez\SkyWars\arena;

use Marquez\SkyWars\SkyWars;
use Marquez\SkyWars\game\GameMode;
use pocketmine\utils\Config;
use function array_filter;
use function file_exists;
use function mkdir;

/**
 * Manages all arenas - loading, saving, and CRUD operations
 */
class ArenaManager {

    /** @var array<string, Arena> */
    private array $arenas = [];

    private string $dataPath;

    public function __construct(
        private SkyWars $plugin
    ) {
        $this->dataPath = $plugin->getDataFolder() . "arenas/";
        
        if (!file_exists($this->dataPath)) {
            mkdir($this->dataPath, 0777, true);
        }

        $this->loadArenas();
    }

    /**
     * Load all arenas from disk
     */
    private function loadArenas(): void {
        $files = glob($this->dataPath . "*.yml");
        
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $config = new Config($file, Config::YAML);
            $data = $config->getAll();
            
            $arena = Arena::fromArray($data);
            $this->arenas[$arena->getName()] = $arena;
            
            $this->plugin->getLogger()->info("Loaded arena: {$arena->getName()}");
        }
    }

    /**
     * Save arena to disk
     */
    public function saveArena(Arena $arena): void {
        $config = new Config($this->dataPath . $arena->getName() . ".yml", Config::YAML);
        $config->setAll($arena->toArray());
        $config->save();
    }

    /**
     * Get arena by name
     */
    public function getArena(string $name): ?Arena {
        return $this->arenas[$name] ?? null;
    }

    /**
     * Get all arenas
     * 
     * @return array<string, Arena>
     */
    public function getAllArenas(): array {
        return $this->arenas;
    }

    /**
     * Get arenas that support a specific game mode and are available
     * 
     * @return array<Arena>
     */
    public function getArenasForMode(GameMode $mode): array {
        return array_filter($this->arenas, function(Arena $arena) use ($mode) {
            return $arena->supportsMode($mode) && $arena->isEnabled() && $arena->isValid();
        });
    }

    /**
     * Add/register a new arena
     */
    public function addArena(Arena $arena): void {
        $this->arenas[$arena->getName()] = $arena;
        $this->saveArena($arena);
    }

    /**
     * Delete an arena
     */
    public function deleteArena(string $name): bool {
        if (!isset($this->arenas[$name])) {
            return false;
        }

        unset($this->arenas[$name]);
        
        $file = $this->dataPath . $name . ".yml";
        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    /**
     * Check if arena exists
     */
    public function exists(string $name): bool {
        return isset($this->arenas[$name]);
    }

    /**
     * Get arena count
     */
    public function getArenaCount(): int {
        return count($this->arenas);
    }

    /**
     * Reload all arenas
     */
    public function reloadArenas(): void {
        $this->arenas = [];
        $this->loadArenas();
    }
}
