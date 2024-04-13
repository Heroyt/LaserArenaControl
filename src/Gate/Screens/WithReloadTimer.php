<?php

namespace App\Gate\Screens;

trait WithReloadTimer
{

    public function getReloadTimer() : ?int {
        $settings = $this->getSettings();
        if (!isset($settings->time)) {
            return null;
        }

        $startTime = $this->getReloadStartTime();

        if ($startTime === -1) {
            return null;
        }

        return $settings->time - (time() - $startTime) + 2;
    }

    public function getReloadStartTime() : int {
        $trigger = $this->getTrigger();
        if (isset($trigger) && $trigger->isReloadTimeSettable()) {
            return $trigger->getReloadTimeFrom($this->getGame());
        }
        return ($this->getGame()->end ?? $this->getGame()->start ?? $this->getGame()->fileTime)?->getTimestamp() ?? -1;
    }

}