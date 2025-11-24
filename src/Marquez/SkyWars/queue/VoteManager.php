<?php

declare(strict_types=1);

namespace Marquez\SkyWars\queue;

use pocketmine\player\Player;
use Marquez\SkyWars\arena\Arena;

/**
 * Manages map voting for a queue lobby
 */
class VoteManager {

    /** @var array<string, string> Player name => Arena name */
    private array $votes = [];
    
    /** @var array<Arena> */
    private array $availableMaps;

    /**
     * @param array<Arena> $availableMaps
     */
    public function __construct(array $availableMaps) {
        $this->availableMaps = $availableMaps;
    }

    /**
     * Register a vote
     */
    public function vote(Player $player, string $arenaName): bool {
        // Check if already voted
        if (isset($this->votes[$player->getName()])) {
            return false;
        }

        // Validate arena is in available maps
        $found = false;
        foreach ($this->availableMaps as $arena) {
            if ($arena->getName() === $arenaName) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

        $this->votes[$player->getName()] = $arenaName;
        return true;
    }

    /**
     * Check if player has voted
     */
    public function hasVoted(Player $player): bool {
        return isset($this->votes[$player->getName()]);
    }

    /**
     * Get player's vote
     */
    public function getVote(Player $player): ?string {
        return $this->votes[$player->getName()] ?? null;
    }

    /**
     * Get vote counts
     * 
     * @return array<string, int> Arena name => vote count
     */
    public function getVoteCounts(): array {
        $counts = [];
        
        foreach ($this->votes as $arenaName) {
            if (!isset($counts[$arenaName])) {
                $counts[$arenaName] = 0;
            }
            $counts[$arenaName]++;
        }

        return $counts;
    }

    /**
     * Get total votes
     */
    public function getTotalVotes(): int {
        return count($this->votes);
    }

    /**
     * Get the winning map (most voted or random if no votes)
     */
    public function getWinningMap(): ?Arena {
        if (empty($this->availableMaps)) {
            return null;
        }

        // If no votes, return random map
        if (empty($this->votes)) {
            return $this->availableMaps[array_rand($this->availableMaps)];
        }

        // Count votes
        $counts = $this->getVoteCounts();
        arsort($counts); // Sort by count descending

        // Get most voted arena name
        $winnerName = array_key_first($counts);

        // Find and return the arena
        foreach ($this->availableMaps as $arena) {
            if ($arena->getName() === $winnerName) {
                return $arena;
            }
        }

        // Fallback to random
        return $this->availableMaps[array_rand($this->availableMaps)];
    }

    /**
     * Get available maps
     * 
     * @return array<Arena>
     */
    public function getAvailableMaps(): array {
        return $this->availableMaps;
    }

    /**
     * Reset all votes
     */
    public function reset(): void {
        $this->votes = [];
    }
}
