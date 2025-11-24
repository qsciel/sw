<?php

declare(strict_types=1);

namespace Marquez\SkyWars\game;

use pocketmine\player\Player;
use pocketmine\player\GameMode as PMGameMode;
use pocketmine\scheduler\ClosureTask;
use Marquez\SkyWars\SkyWars;
use Marquez\SkyWars\arena\Arena;
use Marquez\SkyWars\player\PlayerState;
use Marquez\SkyWars\utils\Countdown;
use function array_chunk;
use function count;
use function shuffle;

/**
 * Represents a single active game session
 */
class GameSession {

    private GameState $state = GameState::COUNTDOWN;
    
    /** @var array<string, Player> */
    private array $alivePlayers = [];
    
    /** @var array<string, Player> */
    private array $spectators = [];
    
    /** @var array<int, array<string>> Team ID => Player names */
    private array $teams = [];
    
    private ?Countdown $cageCountdown = null;
    private ?int $gameTimeTask = null;
    private int $gameTime = 0;

    /**
     * @param array<Player> $players
     */
    public function __construct(
        private SkyWars $plugin,
        private Arena $arena,
        private GameMode $mode,
        array $players
    ) {
        // Assign players
        foreach ($players as $player) {
            $this->alivePlayers[$player->getName()] = $player;
        }
        
        // Assign teams
        $this->assignTeams();
    }

    /**
     * Assign players to teams based on game mode
     */
    private function assignTeams(): void {
        $teamSize = $this->mode->getTeamSize();
        $playerNames = array_keys($this->alivePlayers);
        shuffle($playerNames);
        
        if ($teamSize === 1) {
            // Solo - each player is their own team
            $teamId = 0;
            foreach ($playerNames as $name) {
                $this->teams[$teamId] = [$name];
                
                $session = $this->plugin->getPlayerManager()->getSession($this->alivePlayers[$name]);
                $session->setTeamId($teamId);
                $teamId++;
            }
        } else {
            // Team mode - group players
            $teamId = 0;
            $chunks = array_chunk($playerNames, $teamSize);
            
            foreach ($chunks as $chunk) {
                $this->teams[$teamId] = $chunk;
                
                foreach ($chunk as $name) {
                    $session = $this->plugin->getPlayerManager()->getSession($this->alivePlayers[$name]);
                    $session->setTeamId($teamId);
                }
                $teamId++;
            }
        }
    }

    /**
     * Start the game
     */
    public function start(): void {
        // Teleport players to cages
        $cageIndex = 0;
        foreach ($this->alivePlayers as $player) {
            $cagePos = $this->arena->getCagePosition($cageIndex);
            
            if ($cagePos === null) {
                $this->plugin->getLogger()->warning("Not enough cages in arena {$this->arena->getName()}");
                continue;
            }
            
            $player->teleport($cagePos);
            
            // Set game mode to survival
            $player->setGamemode(PMGameMode::SURVIVAL());
            
            // Clear inventory and reset stats
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setHealth($player->getMaxHealth());
            $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
            $player->getXpManager()->setXpLevel(0);
            $player->getXpManager()->setXpProgress(0.0);
            $player->getEffects()->clear();
            
            // Update session
            $session = $this->plugin->getPlayerManager()->getSession($player);
            $session->setState(PlayerState::IN_GAME);
            $session->setCurrentGame($this);
            $session->setCurrentQueue(null);
            $session->resetKills();
            $session->incrementGamesPlayed();
            
            $cageIndex++;
        }
        
        // Send start message
        $msg = $this->plugin->getMessageManager();
        foreach ($this->alivePlayers as $player) {
            $player->sendMessage($msg->getMessage('game.started'));
        }
        
        // Start cage countdown
        $this->startCageCountdown();
    }

    /**
     * Start cage countdown (5 seconds before game starts)
     */
    private function startCageCountdown(): void {
        $duration = $this->plugin->getConfig()->getNested('game.cage-countdown', 5);
        $msg = $this->plugin->getMessageManager();
        
        $this->cageCountdown = new Countdown(
            $duration,
            function(int $time) use ($msg) {
                foreach ($this->alivePlayers as $player) {
                    $player->sendTip($msg->getRaw('game.cage_opening', ['time' => $time]));
                }
            },
            function() {
                $this->openCages();
            }
        );
        
        $this->plugin->getScheduler()->scheduleRepeatingTask($this->cageCountdown, 20);
    }

    /**
     * Open cages and start active gameplay
     */
    private function openCages(): void {
        $this->state = GameState::ACTIVE;
        
        $msg = $this->plugin->getMessageManager();
        foreach ($this->alivePlayers as $player) {
            $player->sendMessage($msg->getMessage('game.cages_opened'));
        }
        
        // Start game timer
        $maxTime = $this->plugin->getConfig()->getNested('game.max-game-time', 600);
        
        if ($maxTime > 0) {
            $this->gameTimeTask = $this->plugin->getScheduler()->scheduleRepeatingTask(
                new ClosureTask(function() use ($maxTime) {
                    $this->gameTime++;
                    
                    if ($this->gameTime >= $maxTime) {
                        // Time limit reached - end game
                        $this->endGame(null);
                    }
                }),
                20
            )->getTaskId();
        }
    }

    /**
     * Handle player elimination
     */
    public function eliminatePlayer(Player $player, bool $sendMessage = true): void {
        $playerName = $player->getName();
        
        if (!isset($this->alivePlayers[$playerName])) {
            return; // Not in game
        }
        
        unset($this->alivePlayers[$playerName]);
        $this->spectators[$playerName] = $player;
        
        // Update session
        $session = $this->plugin->getPlayerManager()->getSession($player);
        $session->setState(PlayerState::SPECTATING);
        $session->addDeath();
        
        // Teleport to spectator spawn
        $player->teleport($this->arena->getSpectatorSpawn());
        $player->setGamemode(PMGameMode::SPECTATOR());
        
        if ($sendMessage) {
            $msg = $this->plugin->getMessageManager();
            
            // Send elimination message to all
            $remaining = count($this->alivePlayers);
            foreach ($this->getAllPlayers() as $p) {
                $p->sendMessage($msg->getMessage('game.player_eliminated', [
                    'player' => $playerName,
                    'remaining' => $remaining
                ]));
            }
            
            // Send spectator message to eliminated player
            $player->sendMessage($msg->getMessage('game.you_eliminated'));
            $player->sendMessage($msg->getMessage('game.spectator_mode'));
        }
        
        // Check win condition
        $this->checkWinCondition();
    }

    /**
     * Handle player quit
     */
    public function handleQuit(Player $player): void {
        $playerName = $player->getName();
        
        if (isset($this->alivePlayers[$playerName])) {
            unset($this->alivePlayers[$playerName]);
            
            $msg = $this->plugin->getMessageManager();
            $remaining = count($this->alivePlayers);
            
            foreach ($this->getAllPlayers() as $p) {
                $p->sendMessage($msg->getMessage('game.player_quit', [
                    'player' => $playerName,
                    'remaining' => $remaining
                ]));
            }
            
            $this->checkWinCondition();
        } elseif (isset($this->spectators[$playerName])) {
            unset($this->spectators[$playerName]);
        }
    }

    /**
     * Add kill to player
     */
    public function addKill(Player $player): void {
        $session = $this->plugin->getPlayerManager()->getSession($player);
        $session->addKill();
        
        $msg = $this->plugin->getMessageManager();
        $player->sendMessage($msg->getMessage('game.kill', [
            'kills' => $session->getKills()
        ]));
    }

    /**
     * Check win condition
     */
    private function checkWinCondition(): void {
        if ($this->state !== GameState::ACTIVE) {
            return;
        }

        if ($this->mode->getTeamSize() === 1) {
            // Solo mode - check if only 1 player left
            if (count($this->alivePlayers) === 1) {
                $winner = array_values($this->alivePlayers)[0];
                $this->endGame($winner);
            } elseif (count($this->alivePlayers) === 0) {
                $this->endGame(null);
            }
        } else {
            // Team mode - check if only 1 team left
            $aliveTeams = [];
            
            foreach ($this->alivePlayers as $player) {
                $session = $this->plugin->getPlayerManager()->getSession($player);
                $teamId = $session->getTeamId();
                
                if ($teamId !== null && !in_array($teamId, $aliveTeams, true)) {
                    $aliveTeams[] = $teamId;
                }
            }
            
            if (count($aliveTeams) === 1) {
                // One team wins
                $winningTeamId = $aliveTeams[0];
                $this->endGame(null, $winningTeamId);
            } elseif (count($aliveTeams) === 0) {
                $this->endGame(null);
            }
        }
    }

    /**
     * End the game
     */
    public function endGame(?Player $winner = null, ?int $winningTeamId = null): void {
        $this->state = GameState::ENDING;
        
        // Cancel tasks
        if ($this->cageCountdown !== null) {
            $this->cageCountdown->cancel();
        }
        
        if ($this->gameTimeTask !== null) {
            $this->plugin->getScheduler()->cancelTask($this->gameTimeTask);
        }
        
        $msg = $this->plugin->getMessageManager();
        
        // Announce winner
        if ($winner !== null) {
            // Solo winner
            foreach ($this->getAllPlayers() as $player) {
                $player->sendMessage($msg->getMessage('game.winner', [
                    'player' => $winner->getName()
                ]));
            }
            
            $session = $this->plugin->getPlayerManager()->getSession($winner);
            $session->addWin();
        } elseif ($winningTeamId !== null) {
            // Team winner
            foreach ($this->getAllPlayers() as $player) {
                $player->sendMessage($msg->getMessage('game.team_winner', [
                    'team' => $winningTeamId + 1
                ]));
            }
            
            // Add wins to team members
            if (isset($this->teams[$winningTeamId])) {
                foreach ($this->teams[$winningTeamId] as $playerName) {
                    if (isset($this->alivePlayers[$playerName]) || isset($this->spectators[$playerName])) {
                        $p = $this->alivePlayers[$playerName] ?? $this->spectators[$playerName];
                        $session = $this->plugin->getPlayerManager()->getSession($p);
                        $session->addWin();
                    }
                }
            }
        }
        
        // Send game ended message
        foreach ($this->getAllPlayers() as $player) {
            $player->sendMessage($msg->getMessage('game.game_ended'));
        }
        
        // Teleport all players back and restore state
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() {
                foreach ($this->getAllPlayers() as $player) {
                    $session = $this->plugin->getPlayerManager()->getSession($player);
                    $session->setState(PlayerState::LOBBY);
                    $session->setCurrentGame(null);
                    $session->restoreOriginalState();
                }
                
                // Unregister game from QueueManager
                $this->plugin->getQueueManager()->endGame($this);
            }),
            20 * 3 // 3 seconds delay
        );
    }

    /**
     * Force end the game (server shutdown, etc.)
     */
    public function forceEnd(): void {
        foreach ($this->getAllPlayers() as $player) {
            $session = $this->plugin->getPlayerManager()->getSession($player);
            $session->setState(PlayerState::LOBBY);
            $session->setCurrentGame(null);
            $session->restoreOriginalState();
        }
        
        if ($this->cageCountdown !== null) {
            $this->cageCountdown->cancel();
        }
        
        if ($this->gameTimeTask !== null) {
            $this->plugin->getScheduler()->cancelTask($this->gameTimeTask);
        }
    }

    /**
     * Get all players (alive + spectators)
     * 
     * @return array<Player>
     */
    public function getAllPlayers(): array {
        return array_merge(array_values($this->alivePlayers), array_values($this->spectators));
    }

    /**
     * Get alive players
     * 
     * @return array<Player>
     */
    public function getAlivePlayers(): array {
        return array_values($this->alivePlayers);
    }

    /**
     * Get game state
     */
    public function getState(): GameState {
        return $this->state;
    }

    /**
     * Get arena
     */
    public function getArena(): Arena {
        return $this->arena;
    }

    /**
     * Get game mode
     */
    public function getMode(): GameMode {
        return $this->mode;
    }

    /**
     * Check if player is in this game
     */
    public function hasPlayer(Player $player): bool {
        $name = $player->getName();
        return isset($this->alivePlayers[$name]) || isset($this->spectators[$name]);
    }

    /**
     * Check if player is alive
     */
    public function isAlive(Player $player): bool {
        return isset($this->alivePlayers[$player->getName()]);
    }
}
