<?php

declare(strict_types=1);

namespace Marquez\SkyWars;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use Marquez\SkyWars\manager\MessageManager;
use Marquez\SkyWars\arena\ArenaManager;
use Marquez\SkyWars\player\PlayerManager;
use Marquez\SkyWars\queue\QueueManager;
use Marquez\SkyWars\form\FormManager;
use Marquez\SkyWars\listener\GameListener;
use Marquez\SkyWars\listener\LobbyListener;
use Marquez\SkyWars\command\SkyWarsCommand;

/**
 * Main SkyWars plugin class
 * 
 * Enterprise-level PocketMine-MP SkyWars implementation with:
 * - Multiple game modes (Solo, Duos, Squads)
 * - Queue system with map voting
 * - Interactive arena configuration
 * - Comprehensive message customization
 * - Well-structured, maintainable code
 */
class SkyWars extends PluginBase {

    private static self $instance;
    
    private MessageManager $messageManager;
    private ArenaManager $arenaManager;
    private PlayerManager $playerManager;
    private QueueManager $queueManager;
    private FormManager $formManager;

    /**
     * Called when plugin is enabled
     */
    protected function onEnable(): void {
        self::$instance = $this;
        
        $this->getLogger()->info("§aInitializing SkyWars...");
        
        // Save default resources
        $this->saveDefaultConfig();
        $this->saveResource("messages.json");
        
        // Initialize managers
        $this->initializeManagers();
        
        // Register event listeners
        $this->registerListeners();
        
        // Register commands
        $this->registerCommands();
        
        $this->getLogger()->info("§aSkyWars enabled successfully!");
        $this->getLogger()->info("§eLoaded {$this->arenaManager->getArenaCount()} arena(s)");
    }

    /**
     * Called when plugin is disabled
     */
    protected function onDisable(): void {
        $this->getLogger()->info("§cShutting down SkyWars...");
        
        // Shutdown queue manager (ends all games, clears queues)
        if (isset($this->queueManager)) {
            $this->queueManager->shutdown();
        }
        
        $this->getLogger()->info("§cSkyWars disabled.");
    }

    /**
     * Initialize all managers
     */
    private function initializeManagers(): void {
        // Load messages
        $messagesPath = $this->getDataFolder() . "messages.json";
        $messagesConfig = new Config($messagesPath, Config::JSON);
        $this->messageManager = new MessageManager($messagesConfig);
        
        // Initialize core managers
        $this->arenaManager = new ArenaManager($this);
        $this->playerManager = new PlayerManager($this);
        $this->queueManager = new QueueManager($this);
        $this->formManager = new FormManager($this);
        
        $this->getLogger()->info("§aManagers initialized");
    }

    /**
     * Register event listeners
     */
    private function registerListeners(): void {
        $pluginManager = $this->getServer()->getPluginManager();
        
        $pluginManager->registerEvents(new GameListener($this), $this);
        $pluginManager->registerEvents(new LobbyListener($this), $this);
        
        $this->getLogger()->info("§aEvent listeners registered");
    }

    /**
     * Register commands
     */
    private function registerCommands(): void {
        $commandMap = $this->getServer()->getCommandMap();
        
        $commandMap->register("skywars", new SkyWarsCommand($this));
        
        $this->getLogger()->info("§aCommands registered");
    }

    /**
     * Get plugin instance
     */
    public static function getInstance(): self {
        return self::$instance;
    }

    /**
     * Get message manager
     */
    public function getMessageManager(): MessageManager {
        return $this->messageManager;
    }

    /**
     * Get arena manager
     */
    public function getArenaManager(): ArenaManager {
        return $this->arenaManager;
    }

    /**
     * Get player manager
     */
    public function getPlayerManager(): PlayerManager {
        return $this->playerManager;
    }

    /**
     * Get queue manager
     */
    public function getQueueManager(): QueueManager {
        return $this->queueManager;
    }

    /**
     * Get form manager
     */
    public function getFormManager(): FormManager {
        return $this->formManager;
    }

    /**
     * Reload plugin configuration
     */
    public function reloadPlugin(): void {
        $this->reloadConfig();
        $this->messageManager->reload();
        $this->arenaManager->reloadArenas();
        
        $this->getLogger()->info("§aPlugin configuration reloaded");
    }
}
