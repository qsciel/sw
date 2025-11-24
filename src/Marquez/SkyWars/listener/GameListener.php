<?php

declare(strict_types=1);

namespace Marquez\SkyWars\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\player\Player;
use Marquez\SkyWars\SkyWars;
use Marquez\SkyWars\game\GameState;
use Marquez\SkyWars\player\PlayerState;

/**
 * Handles events during active games
 */
class GameListener implements Listener {

    public function __construct(
        private SkyWars $plugin
    ) {}

    /**
     * Handle player death in game
     */
    public function onDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getPlayerManager()->getSession($player);
        
        if ($session->getState() !== PlayerState::IN_GAME) {
            return;
        }
        
        $game = $session->getCurrentGame();
        if ($game === null) {
            return;
        }
        
        // Clear drops
        $event->setDrops([]);
        $event->setXpDropAmount(0);
        
        // Handle killer
        $cause = $player->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
            if ($damager instanceof Player && $game->hasPlayer($damager)) {
                $game->addKill($damager);
            }
        }
        
        // Eliminate player
        $game->eliminatePlayer($player);
    }

    /**
     * Handle player quit during game
     */
    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getPlayerManager()->getSession($player);
        
        $game = $session->getCurrentGame();
        if ($game !== null) {
            $game->handleQuit($player);
        }
        
        $queue = $session->getCurrentQueue();
        if ($queue !== null) {
            $queue->removePlayer($player, false);
        }
        
        // Cleanup session
        $this->plugin->getPlayerManager()->removeSession($player);
    }

    /**
     * Cancel damage during countdown
     */
    public function onDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        
        if (!$entity instanceof Player) {
            return;
        }
        
        $session = $this->plugin->getPlayerManager()->getSession($entity);
        
        if ($session->getState() !== PlayerState::IN_GAME) {
            return;
        }
        
        $game = $session->getCurrentGame();
        if ($game === null) {
            return;
        }
        
        // Cancel damage during countdown
        if ($game->getState() === GameState::COUNTDOWN) {
            $event->cancel();
            
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if ($damager instanceof Player) {
                    $msg = $this->plugin->getMessageManager();
                    $damager->sendTip($msg->getRaw('game.no_pvp_countdown'));
                }
            }
        }
    }

    /**
     * Handle block break in game
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getPlayerManager()->getSession($player);
        
        if ($session->getState() !== PlayerState::IN_GAME) {
            return;
        }
        
        // Allow breaking in active game
        // Can add whitelist/blacklist here if needed
    }

    /**
     * Handle block place in game
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getPlayerManager()->getSession($player);
        
        if ($session->getState() !== PlayerState::IN_GAME) {
            return;
        }
        
        // Allow placing in active game
        // Can add restrictions here if needed
    }
}
