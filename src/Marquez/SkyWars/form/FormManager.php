<?php

declare(strict_types=1);

namespace Marquez\SkyWars\form;

use pocketmine\player\Player;
use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use Marquez\SkyWars\SkyWars;
use Marquez\SkyWars\game\GameMode;
use Marquez\SkyWars\arena\Arena;

/**
 * Manages all forms in the plugin
 */
class FormManager {

    public function __construct(
        private SkyWars $plugin
    ) {}

    /**
     * Send game mode selection form
     */
    public function sendGameModeForm(Player $player): void {
        $msg = $this->plugin->getMessageManager();
        $queueManager = $this->plugin->getQueueManager();
        
        $form = new class($msg, $queueManager, $this->plugin) implements Form {
            public function __construct(
                private $msg,
                private $queueManager,
                private SkyWars $plugin
            ) {}

            public function handleResponse(Player $player, $data): void {
                if ($data === null) {
                    return; // Cancelled
                }

                $mode = match($data) {
                    0 => GameMode::SOLO,
                    1 => GameMode::DUOS,
                    2 => GameMode::SQUADS,
                    default => null
                };

                if ($mode !== null) {
                    $this->queueManager->joinQueue($player, $mode);
                }
            }

            public function jsonSerialize(): array {
                $solo = $this->queueManager->getQueue(GameMode::SOLO);
                $duos = $this->queueManager->getQueue(GameMode::DUOS);
                $squads = $this->queueManager->getQueue(GameMode::SQUADS);
                
                $buttons = [];
                
                if ($solo !== null) {
                    $buttons[] = [
                        'text' => $this->msg->getRaw('forms.solo_button', [
                            'players' => $solo->getPlayerCount()
                        ])
                    ];
                }
                
                if ($duos !== null) {
                    $buttons[] = [
                        'text' => $this->msg->getRaw('forms.duos_button', [
                            'players' => $duos->getPlayerCount()
                        ])
                    ];
                }
                
                if ($squads !== null) {
                    $buttons[] = [
                        'text' => $this->msg->getRaw('forms.squads_button', [
                            'players' => $squads->getPlayerCount()
                        ])
                    ];
                }

                return [
                    'type' => 'form',
                    'title' => $this->msg->getRaw('forms.game_mode_title'),
                    'content' => $this->msg->getRaw('forms.game_mode_content'),
                    'buttons' => $buttons
                ];
            }
        };

        $player->sendForm($form);
    }

    /**
     * Send map voting form
     */
    public function sendVotingForm(Player $player): void {
        $session = $this->plugin->getPlayerManager()->getSession($player);
        $queue = $session->getCurrentQueue();
        
        if ($queue === null) {
            return;
        }
        
        $voteManager = $queue->getVoteManager();
        if ($voteManager === null) {
            return;
        }
        
        $msg = $this->plugin->getMessageManager();
        
        $form = new class($msg, $voteManager, $player, $this->plugin) implements Form {
            public function __construct(
                private $msg,
                private $voteManager,
                private Player $player,
                private SkyWars $plugin
            ) {}

            public function handleResponse(Player $player, $data): void {
                if ($data === null) {
                    return; // Cancelled
                }

                $maps = $this->voteManager->getAvailableMaps();
                
                if (!isset($maps[$data])) {
                    return;
                }

                $arena = $maps[$data];
                
                if ($this->voteManager->vote($player, $arena->getName())) {
                    $player->sendMessage($this->msg->getMessage('queue.voted_for_map', [
                        'map' => $arena->getDisplayName()
                    ]));
                } else {
                    $player->sendMessage($this->msg->getMessage('queue.already_voted'));
                }
            }

            public function jsonSerialize(): array {
                $buttons = [];
                
                foreach ($this->voteManager->getAvailableMaps() as $arena) {
                    $buttons[] = [
                        'text' => "§e" . $arena->getDisplayName()
                    ];
                }

                return [
                    'type' => 'form',
                    'title' => $this->msg->getRaw('forms.vote_title'),
                    'content' => $this->msg->getRaw('forms.vote_content'),
                    'buttons' => $buttons
                ];
            }
        };

        $player->sendForm($form);
    }

    /**
     * Send arena setup menu
     */
    public function sendSetupMenu(Player $player, Arena $arena): void {
        $msg = $this->plugin->getMessageManager();
        
        $form = new class($msg, $arena, $player, $this->plugin) implements Form {
            public function __construct(
                private $msg,
                private Arena $arena,
                private Player $player,
                private SkyWars $plugin
            ) {}

            public function handleResponse(Player $player, $data): void {
                if ($data === null) {
                    return;
                }

                match($data) {
                    0 => $this->addCage(),
                    1 => $this->addChest(),
                    2 => $this->setSpectator(),
                    3 => $this->configureModes(),
                    4 => $this->finish(),
                    5 => $this->cancel(),
                    default => null
                };
            }

            private function addCage(): void {
                $pos = $this->player->getPosition();
                $this->arena->addCage($pos->getX(), $pos->getY(), $pos->getZ());
                
                $count = $this->arena->getCageCount();
                $this->player->sendMessage($this->msg->getMessage('admin.cage_added', [
                    'number' => $count
                ]));
                
                // Reopen menu
                $this->plugin->getFormManager()->sendSetupMenu($this->player, $this->arena);
            }

            private function addChest(): void {
                $pos = $this->player->getPosition();
                $this->arena->addChestLocation($pos->getX(), $pos->getY(), $pos->getZ());
                
                $this->player->sendMessage($this->msg->getMessage('admin.chest_added'));
                
                // Reopen menu
                $this->plugin->getFormManager()->sendSetupMenu($this->player, $this->arena);
            }

            private function setSpectator(): void {
                $pos = $this->player->getPosition();
                $this->arena->setSpectatorSpawn($pos->getX(), $pos->getY(), $pos->getZ());
                
                $this->player->sendMessage($this->msg->getMessage('admin.spectator_spawn_set'));
                
                // Reopen menu
                $this->plugin->getFormManager()->sendSetupMenu($this->player, $this->arena);
            }

            private function configureModes(): void {
                $this->plugin->getFormManager()->sendModesForm($this->player, $this->arena);
            }

            private function finish(): void {
                if (!$this->arena->isValid()) {
                    $this->player->sendMessage($this->msg->getMessage('admin.invalid_arena'));
                    return;
                }
                
                $this->plugin->getArenaManager()->saveArena($this->arena);
                $this->player->sendMessage($this->msg->getMessage('admin.setup_complete'));
            }

            private function cancel(): void {
                $this->player->sendMessage($this->msg->getMessage('admin.setup_cancelled'));
            }

            public function jsonSerialize(): array {
                return [
                    'type' => 'form',
                    'title' => $this->msg->getRaw('forms.setup_title', [
                        'arena' => $this->arena->getDisplayName()
                    ]),
                    'content' => $this->msg->getRaw('forms.setup_content') . "\n\n§7Cages: §e{$this->arena->getCageCount()}\n§7Chests: §e" . count($this->arena->getChestLocations()),
                    'buttons' => [
                        ['text' => $this->msg->getRaw('forms.setup_add_cage')],
                        ['text' => $this->msg->getRaw('forms.setup_add_chest')],
                        ['text' => $this->msg->getRaw('forms.setup_set_spectator')],
                        ['text' => $this->msg->getRaw('forms.setup_set_modes')],
                        ['text' => $this->msg->getRaw('forms.setup_finish')],
                        ['text' => $this->msg->getRaw('forms.setup_cancel')]
                    ]
                ];
            }
        };

        $player->sendForm($form);
    }

    /**
     * Send mode configuration form
     */
    public function sendModesForm(Player $player, Arena $arena): void {
        $msg = $this->plugin->getMessageManager();
        
        $form = new class($msg, $arena, $player, $this->plugin) implements Form {
            public function __construct(
                private $msg,
                private Arena $arena,
                private Player $player,
                private SkyWars $plugin
            ) {}

            public function handleResponse(Player $player, $data): void {
                if ($data === null) {
                    // Reopen setup menu
                    $this->plugin->getFormManager()->sendSetupMenu($player, $this->arena);
                    return;
                }

                $modes = [];
                if ($data[0]) $modes[] = GameMode::SOLO->value;
                if ($data[1]) $modes[] = GameMode::DUOS->value;
                if ($data[2]) $modes[] = GameMode::SQUADS->value;
                
                $this->arena->setSupportedModes($modes);
                
                // Reopen setup menu
                $this->plugin->getFormManager()->sendSetupMenu($player, $this->arena);
            }

            public function jsonSerialize(): array {
                $supported = $this->arena->getSupportedModes();
                
                return [
                    'type' => 'custom_form',
                    'title' => $this->msg->getRaw('forms.modes_title'),
                    'content' => [
                        [
                            'type' => 'toggle',
                            'text' => $this->msg->getRaw('forms.mode_solo'),
                            'default' => in_array(GameMode::SOLO->value, $supported, true)
                        ],
                        [
                            'type' => 'toggle',
                            'text' => $this->msg->getRaw('forms.mode_duos'),
                            'default' => in_array(GameMode::DUOS->value, $supported, true)
                        ],
                        [
                            'type' => 'toggle',
                            'text' => $this->msg->getRaw('forms.mode_squads'),
                            'default' => in_array(GameMode::SQUADS->value, $supported, true)
                        ]
                    ]
                ];
            }
        };

        $player->sendForm($form);
    }
}
