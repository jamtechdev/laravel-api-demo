<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class SleepModeService
{
    /**
     * This class is responsible for the updating the alert performance of the user
     */
    public $setting = null;

    public function __construct(public Model $user)
    {
        $this->setting = $this->user?->setting;
    }

    public function trunOffSleepMode()
    {
        if ($this->setting && $this->setting?->sleep_mode) {
            // $time = now();
            // $hoursOfSleep = $this->setting->hours_of_sleep ?? 8;
            // $sleepTime = now()->subHours($hoursOfSleep);
            // $timeDifference = $time->diffInHours($sleepTime);
            // if ($timeDifference >= $hoursOfSleep) {
            // Get the time when sleep mode was activated (created_at)
            $sleepModeStart = $this->setting->updated_at;

            // Get the user's sleep duration (default to 8 hours if not set)
            $hoursOfSleep = $this->setting->hours_of_sleep ?? 8;

            // Check the difference in hours between now and when sleep mode was activated
            $hoursPassed = $sleepModeStart->diffInHours(now());

            // If the time passed is greater than or equal to the user's sleep duration
            if ($hoursPassed >= $hoursOfSleep) {
                $this->user->setting->sleep_mode = false;
                $this->user->setting->save();
            }
            return true;
        }
        return false;
    }

    public function outgoingPermission(): bool
    {
        // Check if `mute_outgoing_alerts` is set to true
        if ($this->setting && $this->setting->mute_outgoing_alerts) {
            return false; // Outgoing activities are not allowed
        }

        // Check if `outgoing_snooze_time` is set to a future timestamp
        if ($this->setting && $this->setting->outgoing_snooze_time) {
            $snoozeUntil = $this->setting->outgoing_snooze_time; // Future timestamp
            $currentTime = now();

            // If the current time is before the snooze time, restrict outgoing activities
            if ($currentTime->lessThan($snoozeUntil)) {
                return false;
            }
        }

        // If none of the conditions restrict outgoing activities, allow them
        return true;
    }


}
