<?php

namespace Helpers;

class TimeHelper
{
    /**
     * Day names mapping
     */
    public static array $dayNames = [
        0 => 'Monday',
        1 => 'Tuesday',
        2 => 'Wednesday',
        3 => 'Thursday',
        4 => 'Friday',
        5 => 'Saturday',
        6 => 'Sunday',
    ];

    /**
     * Short day names
     */
    public static array $dayShortNames = [
        0 => 'Mon',
        1 => 'Tue',
        2 => 'Wed',
        3 => 'Thu',
        4 => 'Fri',
        5 => 'Sat',
        6 => 'Sun',
    ];

    /**
     * Get day name from numeric index
     */
    public static function getDayName(int $dayOfWeek): string
    {
        return self::$dayNames[$dayOfWeek] ?? 'Unknown';
    }

    /**
     * Get short day name
     */
    public static function getShortDayName(int $dayOfWeek): string
    {
        return self::$dayShortNames[$dayOfWeek] ?? 'Unknown';
    }

    /**
     * Format time for display
     */
    public static function formatTime(string $time): string
    {
        return date('H:i', strtotime($time));
    }

    /**
     * Get shift category badge class
     */
    public static function getCategoryBadge(string $category): string
    {
        return match ($category) {
            'Morning' => 'bg-success',
            'Swing'   => 'bg-warning text-dark',
            'Night'   => 'bg-info text-dark',
            default   => 'bg-secondary',
        };
    }

    /**
     * Get Monday of a given week (relative to a date)
     */
    public static function getMondayOfWeek(string $date = 'now'): string
    {
        $timestamp = strtotime($date);
        $dayOfWeek = date('N', $timestamp); // 1=Monday .. 7=Sunday
        $monday = strtotime('-' . ($dayOfWeek - 1) . ' days', $timestamp);
        return date('Y-m-d', $monday);
    }

    /**
     * Get all dates in a week starting from Monday
     */
    public static function getWeekDates(string $monday): array
    {
        $dates = [];
        $ts = strtotime($monday);
        for ($i = 0; $i < 7; $i++) {
            $dates[$i] = date('Y-m-d', strtotime("+{$i} days", $ts));
        }
        return $dates;
    }
}
