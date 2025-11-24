<?php

declare(strict_types=1);

namespace Marquez\SkyWars\utils;

use pocketmine\scheduler\Task;
use Closure;

/**
 * Reusable countdown timer with callbacks
 */
class Countdown extends Task {

    private int $currentTime;

    /**
     * @param int $startTime Starting time in seconds
     * @param Closure $onTick Called each second with remaining time: function(int $time): void
     * @param Closure $onComplete Called when countdown reaches 0: function(): void
     * @param Closure|null $onCancel Optional callback when cancelled: function(): void
     */
    public function __construct(
        private int $startTime,
        private Closure $onTick,
        private Closure $onComplete,
        private ?Closure $onCancel = null
    ) {
        $this->currentTime = $startTime;
    }

    /**
     * Called every tick (need to schedule at 20 ticks interval for 1 second)
     */
    public function onRun(): void {
        if ($this->currentTime <= 0) {
            ($this->onComplete)();
            $this->getHandler()?->cancel();
            return;
        }

        ($this->onTick)($this->currentTime);
        $this->currentTime--;
    }

    /**
     * Get current time remaining
     */
    public function getTimeRemaining(): int {
        return $this->currentTime;
    }

    /**
     * Cancel the countdown
     */
    public function cancel(): void {
        if ($this->onCancel !== null) {
            ($this->onCancel)();
        }
        $this->getHandler()?->cancel();
    }

    /**
     * Reset countdown to start time
     */
    public function reset(): void {
        $this->currentTime = $this->startTime;
    }
}
