<?php

declare(strict_types=1);

namespace Marquez\SkyWars\queue;

use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\Server;
use pocketmine\item\VanillaItems;
use pocketmine\scheduler\ClosureTask;
use Marquez\SkyWars\SkyWars;
use Marquez\SkyWars\game\GameMode;
use Marquez\SkyWars\game\GameSession;
use Marquez\SkyWars\player\PlayerState;
use Marquez\SkyWars\arena\Arena;
use Marquez\SkyWars\utils\ItemBuilder;
use Marquez\SkyWars\utils\Countdown;

/**
 * Represents a queue lobby for a specific game mode
 */
class QueueLobby {

    /** @var array<string, Player> */
    private array $players = [];
    
    private ?VoteManager $voteManager = null;
    private ?Countdown $countdown = null;
    private bool $countdownStarted = false;
    
    private int $waitingTipTask = -1;

    public function __construct(
        private SkyWars $plugin,
        private GameMode $mode,
        private Position $lobbySpawn
    ) {}

    /**
     * Add player to queue
     */
    public function addPlayer(Player $player): void {
        $this->players[$player->getName()] = $player;
        
        $session = $this->plugin->getPlayerManager()->getSession($player);
        $session->setState(PlayerState::QUEUE);
        $session->setCurrentQueue($this);
        $session->saveOriginalState();
        
        // Teleport to lobby
        $player->teleport($this->lobbySpawn);
        
        // Give lobby items
        $this->giveLobbyItems($player);
        
        // Send join message
        $msg = $this->plugin->getMessageManager();
        $player->sendMessage($msg->getMessage('queue.joined', [
            'mode' => $this->mode->getDisplayName()
        ]));
        
        // Initialize voting if enough maps
        $this->initializeVoting();
        
        // Check if we should start countdown
        $this->checkStartConditions();
    }

    /**
     * Remove player from queue
     */
    public function removePlayer(Player $player, bool $restore = true): void {
        unset($this->players[$player->getName()]);
        
        $session = $this->plugin->getPlayerManager()->getSession($player);
        $session->setState(PlayerState::LOBBY);
        $session->setCurrentQueue(null);
        
        if ($restore) {
            $session->restoreOriginalState();
        }
        
        // Cancel countdown if not enough players
        if ($this->countdownStarted && count($this->players) < $this->getMinPlayers()) {
            $this->cancelCountdown();
        }
    }

    /**
     * Get all players in queue
     * 
     * @return array<Player>
     */
    public function getPlayers(): array {
        return array_values($this->players);
    }

    /**
     * Get player count
     */
    public function getPlayerCount(): int {
        return count($this->players);
    }

    /**
     * Give lobby items to player
     */
    private function giveLobbyItems(Player $player): void {
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        
        $msg = $this->plugin->getMessageManager();
        
        // Vote item (if voting initialized)
        if ($this->voteManager !== null) {
            $voteItem = ItemBuilder::fromVanilla(VanillaItems::PAPER())
                ->setName($msg->getRaw('items.vote_name'))
                ->setLore($msg->getArray('items.vote_lore'))
                ->addStringTag('skywars', 'vote_item')
                ->build();
            $player->getInventory()->setItem(0, $voteItem);
        }
        
        // Leave item
        $leaveItem = ItemBuilder::fromVanilla(VanillaItems::RED_BED())
            ->setName($msg->getRaw('items.leave_name'))
            ->setLore($msg->getArray('items.leave_lore'))
            ->addStringTag('skywars', 'leave_item')
            ->build();
        $player->getInventory()->setItem(8, $leaveItem);
    }

    /**
     * Initialize voting system
     */
    private function initializeVoting(): void {
        if ($this->voteManager !== null) {
            return; // Already initialized
        }

        $availableArenas = $this->plugin->getArenaManager()->getArenasForMode($this->mode);
        
        if (count($availableArenas) === 0) {
            return; // No arenas available
        }

        // Limit to configured number of maps
        $maxMaps = $this->plugin->getConfig()->getNested('queue.maps-to-vote', 3);
        $availableArenas = array_slice($availableArenas, 0, $maxMaps);
        
        $this->voteManager = new VoteManager($availableArenas);
        
        // Notify players
        $msg = $this->plugin->getMessageManager();
        foreach ($this->players as $player) {
            $player->sendMessage($msg->getMessage('queue.map_voting_started'));
            $this->giveLobbyItems($player); // Refresh items to include vote item
        }
    }

    /**
     * Get vote manager
     */
    public function getVoteManager(): ?VoteManager {
        return $this->voteManager;
    }

    /**
     * Check start conditions and begin countdown if met
     */
    private function checkStartConditions(): void {
        $minPlayers = $this->getMinPlayers();
        $currentPlayers = count($this->players);
        
        if ($currentPlayers < $minPlayers) {
            // Start waiting tip task
            $this->startWaitingTips();
            return;
        }
        
        // Enough players, start countdown if not already started
        if (!$this->countdownStarted) {
            $this->startCountdown();
        }
    }

    /**
     * Start countdown
     */
    private function startCountdown(): void {
        $this->countdownStarted = true;
        $this->stopWaitingTips();
        
        $duration = $this->plugin->getConfig()->getNested('game.lobby-countdown', 30);
        $msg = $this->plugin->getMessageManager();
        
        $this->countdown = new Countdown(
            $duration,
            function(int $time) use ($msg) {
                // Send countdown message at certain intervals
                if (in_array($time, [30, 20, 10, 5, 4, 3, 2, 1])) {
                    foreach ($this->players as $player) {
                        $player->sendTip($msg->getRaw('queue.game_starting', ['time' => $time]));
                    }
                }
            },
            function() {
                $this->startGame();
            }
        );
        
        $this->plugin->getScheduler()->scheduleRepeatingTask($this->countdown, 20);
    }

    /**
     * Cancel countdown
     */
    private function cancelCountdown(): void {
        if ($this->countdown !== null) {
            $this->countdown->cancel();
            $this->countdown = null;
        }
        $this->countdownStarted = false;
        $this->startWaitingTips();
    }

    /**
     * Start game
     */
    private function startGame(): void {
        // Get winning map
        $arena = $this->voteManager?->getWinningMap();
        
        if ($arena === null) {
            $this->plugin->getLogger()->error("No arena available for game mode: {$this->mode->value}");
            foreach ($this->players as $player) {
                $this->removePlayer($player);
                $player->sendMessage($this->plugin->getMessageManager()->getMessage('errors.no_arenas_available'));
            }
            return;
        }

        // Announce winning map
        $msg = $this->plugin->getMessageManager();
        $voteCounts = $this->voteManager?->getVoteCounts() ?? [];
        
        if (empty($voteCounts)) {
            $msgKey = 'queue.random_map_selected';
        } else {
            $msgKey = 'queue.most_voted_map';
        }
        
        foreach ($this->players as $player) {
            $player->sendMessage($msg->getMessage($msgKey, ['map' => $arena->getDisplayName()]));
        }

        // Create game session
        $game = new GameSession($this->plugin, $arena, $this->mode, $this->getPlayers());
        
        // Register game with QueueManager
        $this->plugin->getQueueManager()->startGame($game);
        
        // Clear this lobby
        $this->players = [];
        $this->voteManager = null;
        $this->countdown = null;
        $this->countdownStarted = false;
        $this->stopWaitingTips();
    }

    /**
     * Start waiting tips
     */
    private function startWaitingTips(): void {
        if ($this->waitingTipTask !== -1) {
            return; // Already running
        }

        $interval = $this->plugin->getConfig()->getNested('queue.waiting-tip-interval', 5) * 20;
        $msg = $this->plugin->getMessageManager();
        $minPlayers = $this->getMinPlayers();
        
        $this->waitingTipTask = $this->plugin->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() use ($msg, $minPlayers) {
                foreach ($this->players as $player) {
                    $player->sendTip($msg->getRaw('queue.waiting_for_players', [
                        'current' => count($this->players),
                        'min' => $minPlayers
                    ]));
                }
            }),
            $interval
        )->getTaskId();
    }

    /**
     * Stop waiting tips
     */
    private function stopWaitingTips(): void {
        if ($this->waitingTipTask !== -1) {
            $this->plugin->getScheduler()->cancelTask($this->waitingTipTask);
            $this->waitingTipTask = -1;
        }
    }

    /**
     * Get minimum players
     */
    private function getMinPlayers(): int {
        return $this->plugin->getConfig()->getNested("game-modes.{$this->mode->value}.min-players", 3);
    }

    /**
     * Cleanup
     */
    public function shutdown(): void {
        $this->stopWaitingTips();
        if ($this->countdown !== null) {
            $this->countdown->cancel();
        }
    }
}
