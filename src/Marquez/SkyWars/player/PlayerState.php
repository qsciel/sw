<?php

declare(strict_types=1);

namespace Marquez\SkyWars\player;

/**
 * Enum for player states
 */
enum PlayerState: string {
    case LOBBY = "lobby";          // In main lobby
    case QUEUE = "queue";          // In queue waiting
    case IN_GAME = "in_game";      // Playing in game
    case SPECTATING = "spectating"; // Spectating a game
}
