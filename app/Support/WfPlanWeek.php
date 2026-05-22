<?php

namespace App\Support;

class WfPlanWeek
{
    public static function matchesWeekNumber(?string $value, int $weekNumber): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', '', $value) ?? '';
        if ($normalized === '') {
            return false;
        }

        $parts = preg_split('/-/', $normalized) ?: [];
        if (count($parts) === 0) {
            return false;
        }

        $startWeek = self::extractWeek($parts[0] ?? null);
        $endWeek = self::extractWeek($parts[1] ?? null);

        if ($startWeek === null && $endWeek === null) {
            return false;
        }

        if ($startWeek !== null && $endWeek === null) {
            return $startWeek === $weekNumber;
        }

        if ($startWeek === null && $endWeek !== null) {
            return $endWeek === $weekNumber;
        }

        if ($startWeek <= $endWeek) {
            return $weekNumber >= $startWeek && $weekNumber <= $endWeek;
        }

        return $weekNumber >= $startWeek || $weekNumber <= $endWeek;
    }

    private static function extractWeek(?string $token): ?int
    {
        if ($token === null || $token === '') {
            return null;
        }

        if (! preg_match('/(?P<week>\d{1,2})(?:\.\d)?/', $token, $matches)) {
            return null;
        }

        $week = (int) $matches['week'];

        return $week >= 1 && $week <= 53 ? $week : null;
    }
}

