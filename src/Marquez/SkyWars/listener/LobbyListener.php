<?php

declare(strict_types=1);

namespace Marquez\SkyWars\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\player\Player;
use Marquez\SkyWars\SkyWars;
use Marquez\SkyWars\player\PlayerState;

/**
 * Handles events in queue lobbies
 */
class LobbyListener implements Listener {

    public function __construct(
        private SkyWars $plugin
    ) {}

    /**
     * Handle item use in lobby (vote/leave items)
     */
    public function onItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        
        $session = $this->plugin->getPlayerManager()->getSession($player);
        
        if ($session->getState() !== PlayerState::QUEUE) {
            return;
        }
        
        $nbt = $item->getNamedTag();
        
        if (!$nbt->getTag('skywars')) {
            return;
        }
        
        $itemType = $nbt->getString('skywars', '');
        
        match($itemType) {
            'vote_item' => $this->handleVoteItem($player),
            'leave_item' => $this->handleLeaveItem($player),
            default => null
        };
    }

    /**
     * Handle vote item click
     */
    private function handleVoteItem(Player $player): void {
        $this->plugin->getFormManager()->sendVotingForm($player);
    }

    /**
     * Handle leave item click
     */
    private function handleLeaveItem(Player $player): void {
        $this->plugin->getQueueManager()->leaveQueue($player);
    }

    /**
     * Cancel damage in queue lobby
     */
    public function onDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        
        if (!$entity instanceof Player) {
            return;
        }
        
        $session = $this->plugin->getPlayerManager()->getSession($entity);
        
        if ($session->getState() === PlayerState::QUEUE) {
            $event->cancel();
        }
    }

    /**
     * Cancel block break in queue lobby
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getPlayerManager()->getSession($player);
        
        if ($session->getState() === PlayerState::QUEUE) {
            $event->cancel();
        }
    }

    /**
     * Cancel block place in queue lobby
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getPlayerManager()->getSession($player);
        
        if ($session->getState() === PlayerState::QUEUE) {
            $event->cancel();
        }
    }
}
