<?php

namespace Services;

use Models\ForecastRequirement;

class ForecastService
{
    /**
     * Get all forecast requirements grouped
     */
    public function getAllGrouped(): array
    {
        return ForecastRequirement::allGrouped();
    }

    /**
     * Save forecast data from a grid submission
     *
     * Expected format: $_POST['forecast'][$hour][$dayOfWeek] = count
     */
    public function saveFromGrid(array $gridData): array
    {
        $saved = 0;
        $errors = [];

        foreach ($gridData as $hour => $days) {
            $hour = (int)$hour;
            if ($hour < 0 || $hour > 23) {
                continue;
            }

            foreach ($days as $dayOfWeek => $count) {
                $dayOfWeek = (int)$dayOfWeek;
                if ($dayOfWeek < 0 || $dayOfWeek > 6) {
                    continue;
                }

                $count = (int)$count;
                if ($count < 0) {
                    $errors[] = "Hour {$hour}, day {$dayOfWeek}: Count cannot be negative.";
                    continue;
                }

                ForecastRequirement::upsert($dayOfWeek, $hour, $count);
                $saved++;
            }
        }

        return [
            'success' => empty($errors),
            'saved'   => $saved,
            'errors'  => $errors,
        ];
    }

    /**
     * Get total requirements
     */
    public function getTotalRequirements(): int
    {
        return ForecastRequirement::getTotalRequirements();
    }
}
