<?php

declare(strict_types=1);

namespace Marquez\SkyWars\player;

use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\item\Item;
use Marquez\SkyWars\game\GameSession;
use Marquez\SkyWars\queue\QueueLobby;

/**
 * Tracks player data and state during plugin usage
 */
class PlayerSession {

    private PlayerState $state = PlayerState::LOBBY;
    private ?GameSession $currentGame = null;
    private ?QueueLobby $currentQueue = null;
    
    private ?Position $originalPosition = null;
    /** @var array<Item> */
    private array $originalInventory = [];
    /** @var array<Item> */
    private array $originalArmor = [];
    
    private int $kills = 0;
    private int $deaths = 0;
    private int $wins = 0;
    private int $gamesPlayed = 0;
    
    private ?int $teamId = null;

    public function __construct(
        private Player $player
    ) {}

    /**
     * Get the player
     */
    public function getPlayer(): Player {
        return $this->player;
    }

    /**
     * Get current state
     */
    public function getState(): PlayerState {
        return $this->state;
    }

    /**
     * Set state
     */
    public function setState(PlayerState $state): void {
        $this->state = $state;
    }

    /**
     * Get current game
     */
    public function getCurrentGame(): ?GameSession {
        return $this->currentGame;
    }

    /**
     * Set current game
     */
    public function setCurrentGame(?GameSession $game): void {
        $this->currentGame = $game;
    }

    /**
     * Get current queue
     */
    public function getCurrentQueue(): ?QueueLobby {
        return $this->currentQueue;
    }

    /**
     * Set current queue
     */
    public function setCurrentQueue(?QueueLobby $queue): void {
        $this->currentQueue = $queue;
    }

    /**
     * Save player's original state before joining game
     */
    public function saveOriginalState(): void {
        $this->originalPosition = $this->player->getPosition();
        
        $this->originalInventory = [];
        foreach ($this->player->getInventory()->getContents() as $slot => $item) {
            $this->originalInventory[$slot] = clone $item;
        }
        
        $this->originalArmor = [];
        foreach ($this->player->getArmorInventory()->getContents() as $slot => $item) {
            $this->originalArmor[$slot] = clone $item;
        }
    }

    /**
     * Restore player's original state
     */
    public function restoreOriginalState(): void {
        if ($this->originalPosition !== null) {
            $this->player->teleport($this->originalPosition);
        }
        
        $this->player->getInventory()->clearAll();
        foreach ($this->originalInventory as $slot => $item) {
            $this->player->getInventory()->setItem($slot, $item);
        }
        
        $this->player->getArmorInventory()->clearAll();
        foreach ($this->originalArmor as $slot => $item) {
            $this->player->getArmorInventory()->setItem($slot, $item);
        }
        
        $this->player->setHealth($this->player->getMaxHealth());
        $this->player->getHungerManager()->setFood($this->player->getHungerManager()->getMaxFood());
        $this->player->getXpManager()->setXpLevel(0);
        $this->player->getXpManager()->setXpProgress(0.0);
        $this->player->getEffects()->clear();
        
        // Clear saved data
        $this->originalPosition = null;
        $this->originalInventory = [];
        $this->originalArmor = [];
    }

    /**
     * Get team ID
     */
    public function getTeamId(): ?int {
        return $this->teamId;
    }

    /**
     * Set team ID
     */
    public function setTeamId(?int $teamId): void {
        $this->teamId = $teamId;
    }

    /**
     * Add kill
     */
    public function addKill(): void {
        $this->kills++;
    }

    /**
     * Get kills for current game
     */
    public function getKills(): int {
        return $this->kills;
    }

    /**
     * Reset kills (for new game)
     */
    public function resetKills(): void {
        $this->kills = 0;
    }

    /**
     * Add death
     */
    public function addDeath(): void {
        $this->deaths++;
    }

    /**
     * Get total deaths
     */
    public function getDeaths(): int {
        return $this->deaths;
    }

    /**
     * Add win
     */
    public function addWin(): void {
        $this->wins++;
    }

    /**
     * Get total wins
     */
    public function getWins(): int {
        return $this->wins;
    }

    /**
     * Increment games played
     */
    public function incrementGamesPlayed(): void {
        $this->gamesPlayed++;
    }

    /**
     * Get games played
     */
    public function getGamesPlayed(): int {
        return $this->gamesPlayed;
    }

    /**
     * Reset session for new game
     */
    public function resetForNewGame(): void {
        $this->kills = 0;
        $this->teamId = null;
        $this->currentGame = null;
        $this->currentQueue = null;
    }
}
