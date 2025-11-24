<?php

declare(strict_types=1);

namespace Marquez\SkyWars\player;

use pocketmine\player\Player;
use Marquez\SkyWars\SkyWars;

/**
 * Manages all player sessions
 */
class PlayerManager {

    /** @var array<string, PlayerSession> */
    private array $sessions = [];

    public function __construct(
        private SkyWars $plugin
    ) {}

    /**
     * Get or create player session
     */
    public function getSession(Player $player): PlayerSession {
        $name = $player->getName();
        
        if (!isset($this->sessions[$name])) {
            $this->sessions[$name] = new PlayerSession($player);
        }
        
        return $this->sessions[$name];
    }

    /**
     * Remove player session
     */
    public function removeSession(Player $player): void {
        unset($this->sessions[$player->getName()]);
    }

    /**
     * Check if player has a session
     */
    public function hasSession(Player $player): bool {
        return isset($this->sessions[$player->getName()]);
    }

    /**
     * Check if player is in queue
     */
    public function isInQueue(Player $player): bool {
        $session = $this->getSession($player);
        return $session->getState() === PlayerState::QUEUE;
    }

    /**
     * Check if player is in game
     */
    public function isInGame(Player $player): bool {
        $session = $this->getSession($player);
        return $session->getState() === PlayerState::IN_GAME;
    }

    /**
     * Check if player is spectating
     */
    public function isSpectating(Player $player): bool {
        $session = $this->getSession($player);
        return $session->getState() === PlayerState::SPECTATING;
    }

    /**
     * Get all sessions
     * 
     * @return array<string, PlayerSession>
     */
    public function getAllSessions(): array {
        return $this->sessions;
    }
}
