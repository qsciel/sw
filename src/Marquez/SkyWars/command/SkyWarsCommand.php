<?php

declare(strict_types=1);

namespace Marquez\SkyWars\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;
use Marquez\SkyWars\SkyWars;
use Marquez\SkyWars\arena\Arena;

/**
 * Main SkyWars command handler
 */
class SkyWarsCommand extends Command implements PluginOwned {

    public function __construct(
        private SkyWars $plugin
    ) {
        parent::__construct(
            "sw",
            "SkyWars main command",
            "/sw <join|leave|create|edit|delete|list|setlobby|forcestart>",
            ["skywars"]
        );
        
        $this->setPermission("skywars.command");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return false;
        }

        if (empty($args)) {
            $sender->sendMessage("§e--- SkyWars Commands ---");
            $sender->sendMessage("§a/sw join §7- Join a game");
            $sender->sendMessage("§a/sw leave §7- Leave queue/game");
            
            if ($sender->hasPermission("skywars.admin")) {
                $sender->sendMessage("§c/sw create <name> §7- Create arena");
                $sender->sendMessage("§c/sw edit <name> §7- Edit arena");
                $sender->sendMessage("§c/sw delete <name> §7- Delete arena");
                $sender->sendMessage("§c/sw list §7- List arenas");
                $sender->sendMessage("§c/sw setlobby §7- Set lobby spawn");
                $sender->sendMessage("§c/sw forcestart §7- Force start game");
            }
            
            return true;
        }

        $subCommand = strtolower($args[0]);

        match($subCommand) {
            "join" => $this->handleJoin($sender),
            "leave" => $this->handleLeave($sender),
            "create" => $this->handleCreate($sender, $args),
            "edit" => $this->handleEdit($sender, $args),
            "delete" => $this->handleDelete($sender, $args),
            "list" => $this->handleList($sender),
            "setlobby" => $this->handleSetLobby($sender),
            "forcestart" => $this->handleForceStart($sender),
            default => $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.command_usage', [
                'usage' => $this->getUsage()
            ]))
        };

        return true;
    }

    /**
     * Handle /sw join
     */
    private function handleJoin(CommandSender $sender): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.must_be_player'));
            return;
        }

        $this->plugin->getFormManager()->sendGameModeForm($sender);
    }

    /**
     * Handle /sw leave
     */
    private function handleLeave(CommandSender $sender): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.must_be_player'));
            return;
        }

        $session = $this->plugin->getPlayerManager()->getSession($sender);
        
        // Try to leave queue
        if ($session->getCurrentQueue() !== null) {
            $this->plugin->getQueueManager()->leaveQueue($sender);
            return;
        }
        
        // Try to leave game
        $game = $session->getCurrentGame();
        if ($game !== null) {
            $game->eliminatePlayer($sender, false);
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('queue.left'));
            return;
        }
        
        $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.not_in_queue'));
    }

    /**
     * Handle /sw create <name>
     */
    private function handleCreate(CommandSender $sender, array $args): void {
        if (!$sender->hasPermission("skywars.admin")) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.no_permission'));
            return;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.must_be_player'));
            return;
        }

        if (!isset($args[1])) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.command_usage', [
                'usage' => '/sw create <name>'
            ]));
            return;
        }

        $name = $args[1];
        
        if ($this->plugin->getArenaManager()->exists($name)) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.arena_exists', [
                'arena' => $name
            ]));
            return;
        }

        $worldName = $sender->getWorld()->getFolderName();
        $arena = new Arena($name, $name, $worldName);
        
        $this->plugin->getArenaManager()->addArena($arena);
        
        $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.arena_created', [
            'arena' => $name
        ]));
        
        // Open setup menu
        $this->plugin->getFormManager()->sendSetupMenu($sender, $arena);
    }

    /**
     * Handle /sw edit <name>
     */
    private function handleEdit(CommandSender $sender, array $args): void {
        if (!$sender->hasPermission("skywars.admin")) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.no_permission'));
            return;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.must_be_player'));
            return;
        }

        if (!isset($args[1])) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.command_usage', [
                'usage' => '/sw edit <name>'
            ]));
            return;
        }

        $name = $args[1];
        $arena = $this->plugin->getArenaManager()->getArena($name);
        
        if ($arena === null) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.arena_not_found', [
                'arena' => $name
            ]));
            return;
        }

        $this->plugin->getFormManager()->sendSetupMenu($sender, $arena);
    }

    /**
     * Handle /sw delete <name>
     */
    private function handleDelete(CommandSender $sender, array $args): void {
        if (!$sender->hasPermission("skywars.admin")) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.no_permission'));
            return;
        }

        if (!isset($args[1])) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.command_usage', [
                'usage' => '/sw delete <name>'
            ]));
            return;
        }

        $name = $args[1];
        
        if ($this->plugin->getArenaManager()->deleteArena($name)) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.arena_deleted', [
                'arena' => $name
            ]));
        } else {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.arena_not_found', [
                'arena' => $name
            ]));
        }
    }

    /**
     * Handle /sw list
     */
    private function handleList(CommandSender $sender): void {
        $arenas = $this->plugin->getArenaManager()->getAllArenas();
        
        if (empty($arenas)) {
            $sender->sendMessage("§cNo arenas found.");
            return;
        }

        $sender->sendMessage("§e--- SkyWars Arenas ---");
        
        foreach ($arenas as $arena) {
            $status = $arena->isValid() ? "§aReady" : "§cIncomplete";
            $modes = implode(", ", $arena->getSupportedModes());
            
            $sender->sendMessage("§7- §e{$arena->getName()} {$status}");
            $sender->sendMessage("  §7World: §f{$arena->getWorldName()} §7| Cages: §f{$arena->getCageCount()} §7| Modes: §f{$modes}");
        }
    }

    /**
     * Handle /sw setlobby
     */
    private function handleSetLobby(CommandSender $sender): void {
        if (!$sender->hasPermission("skywars.admin")) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.no_permission'));
            return;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.must_be_player'));
            return;
        }

        $pos = $sender->getPosition();
        
        $this->plugin->getConfig()->setNested('lobby.world', $sender->getWorld()->getFolderName());
        $this->plugin->getConfig()->setNested('lobby.spawn.x', $pos->getX());
        $this->plugin->getConfig()->setNested('lobby.spawn.y', $pos->getY());
        $this->plugin->getConfig()->setNested('lobby.spawn.z', $pos->getZ());
        $this->plugin->getConfig()->save();
        
        $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.lobby_set'));
    }

    /**
     * Handle /sw forcestart
     */
    private function handleForceStart(CommandSender $sender): void {
        if (!$sender->hasPermission("skywars.admin")) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('admin.no_permission'));
            return;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getMessage('errors.must_be_player'));
            return;
        }

        // Not implemented yet - would need queue access to force start
        $sender->sendMessage("§cForce start not yet implemented.");
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
