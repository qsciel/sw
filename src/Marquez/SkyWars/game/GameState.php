<?php

declare(strict_types=1);

namespace Marquez\SkyWars\game;

/**
 * Enum for game states
 */
enum GameState: string {
    case WAITING = "waiting";      // Waiting in queue lobby
    case STARTING = "starting";    // Countdown started (30s)
    case COUNTDOWN = "countdown";  // In cages, waiting for countdown (5s)
    case ACTIVE = "active";        // Game is active
    case ENDING = "ending";        // Game ending, cleanup
}
