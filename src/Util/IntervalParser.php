<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Util;

final class IntervalParser
{
    public static function parse(string $interval): int
    {
        if (str_ends_with($interval, ' ms')) {
            $ms = (int) $interval;

            return max(1, (int) ($ms / 1_000));
        }

        if (preg_match('~^(\d+)\s*([smhd])$~', $interval, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            $seconds = match ($unit) {
                'm' => $value * 60,
                'h' => $value * 3_600,
                'd' => $value * 86_400,
                default => $value,
            };

            return max(1, $seconds);
        }

        $seconds = strtotime('+' . $interval);
        if ($seconds === false) {
            return 5;
        }

        $diff = $seconds - time();

        return $diff > 0 ? $diff : 5;
    }
}
