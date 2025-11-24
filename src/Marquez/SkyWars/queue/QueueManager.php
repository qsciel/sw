<?php

declare(strict_types=1);

namespace Marquez\SkyWars\queue;

use pocketmine\player\Player;
use pocketmine\world\Position;
use Marquez\SkyWars\SkyWars;
use Marquez\SkyWars\game\GameMode;
use Marquez\SkyWars\game\GameSession;

/**
 * Manages all queue lobbies and active games
 */
class QueueManager {

    /** @var array<string, QueueLobby> Game mode => Queue lobby */
    private array $queues = [];
    
    /** @var array<int, GameSession> */
    private array $activeGames = [];
    
    private int $nextGameId = 1;

    public function __construct(
        private SkyWars $plugin
    ) {
        $this->initializeQueues();
    }

    /**
     * Initialize queue lobbies for each enabled game mode
     */
    private function initializeQueues(): void {
        $lobbySpawn = $this->getLobbySpawn();
        
        foreach ([GameMode::SOLO, GameMode::DUOS, GameMode::SQUADS] as $mode) {
            $enabled = $this->plugin->getConfig()->getNested("game-modes.{$mode->value}.enabled", true);
            
            if ($enabled) {
                $this->queues[$mode->value] = new QueueLobby($this->plugin, $mode, $lobbySpawn);
            }
        }
    }

    /**
     * Get lobby spawn position
     */
    private function getLobbySpawn(): Position {
        $worldName = $this->plugin->getConfig()->getNested('lobby.world', 'world');
        $x = $this->plugin->getConfig()->getNested('lobby.spawn.x', 0);
        $y = $this->plugin->getConfig()->getNested('lobby.spawn.y', 100);
        $z = $this->plugin->getConfig()->getNested('lobby.spawn.z', 0);
        
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
        
        if ($world === null) {
            $world = $this->plugin->getServer()->getWorldManager()->getDefaultWorld();
        }
        
        return new Position($x, $y, $z, $world);
    }

    /**
     * Join queue for a game mode
     */
    public function joinQueue(Player $player, GameMode $mode): bool {
        // Check if player is already in queue or game
        $session = $this->plugin->getPlayerManager()->getSession($player);
        
        if ($session->getCurrentQueue() !== null) {
            $player->sendMessage($this->plugin->getMessageManager()->getMessage('queue.already_in_queue'));
            return false;
        }
        
        if ($session->getCurrentGame() !== null) {
            $player->sendMessage($this->plugin->getMessageManager()->getMessage('queue.already_in_game'));
            return false;
        }
        
        // Get queue for mode
        $queue = $this->queues[$mode->value] ?? null;
        
        if ($queue === null) {
            $player->sendMessage($this->plugin->getMessageManager()->getMessage('errors.no_arenas_available'));
            return false;
        }
        
        $queue->addPlayer($player);
        return true;
    }

    /**
     * Leave queue
     */
    public function leaveQueue(Player $player): bool {
        $session = $this->plugin->getPlayerManager()->getSession($player);
        $queue = $session->getCurrentQueue();
        
        if ($queue === null) {
            $player->sendMessage($this->plugin->getMessageManager()->getMessage('errors.not_in_queue'));
            return false;
        }
        
        $queue->removePlayer($player);
        $player->sendMessage($this->plugin->getMessageManager()->getMessage('queue.left'));
        return true;
    }

    /**
     * Get queue for game mode
     */
    public function getQueue(GameMode $mode): ?QueueLobby {
        return $this->queues[$mode->value] ?? null;
    }

    /**
     * Start a game (called by QueueLobby)
     */
    public function startGame(GameSession $game): void {
        $gameId = $this->nextGameId++;
        $this->activeGames[$gameId] = $game;
        
        $game->start();
    }

    /**
     * End a game (called by GameSession)
     */
    public function endGame(GameSession $game): void {
        foreach ($this->activeGames as $id => $activeGame) {
            if ($activeGame === $game) {
                unset($this->activeGames[$id]);
                break;
            }
        }
    }

    /**
     * Get active games
     * 
     * @return array<GameSession>
     */
    public function getActiveGames(): array {
        return array_values($this->activeGames);
    }

    /**
     * Get game by player
     */
    public function getGameByPlayer(Player $player): ?GameSession {
        $session = $this->plugin->getPlayerManager()->getSession($player);
        return $session->getCurrentGame();
    }

    /**
     * Get player count across all queues
     */
    public function getTotalQueuedPlayers(): int {
        $total = 0;
        foreach ($this->queues as $queue) {
            $total += $queue->getPlayerCount();
        }
        return $total;
    }

    /**
     * Shutdown all queues and games
     */
    public function shutdown(): void {
        foreach ($this->queues as $queue) {
            $queue->shutdown();
        }
        
        foreach ($this->activeGames as $game) {
            $game->forceEnd();
        }
    }
}
