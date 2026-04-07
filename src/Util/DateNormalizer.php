<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Util;

use DateTimeImmutable;
use Throwable;
use DateInterval;

final class DateNormalizer
{
    private const array FORMATS = [
        'd/M/Y:H:i:s O',      // CLF (Apache/Nginx)
        'Y/m/d H:i:s',        // Nginx error log
        'd-M-Y H:i:s e',      // PHP error log
        'Y-m-d H:i:s,u',      // Supervisord
        'M d H:i:s',          // Syslog (Standard)
        'Y-m-d H:i:s',        // Standard
        'Y-m-d\TH:i:s.uP',    // Monolog / ISO
        'Y-m-d\TH:i:sP',      // ISO
        'Y-m-d\TH:i:s.u\Z',   // ISO UTC
        'Y-m-d\TH:i:s\Z',     // ISO UTC
    ];

    public static function normalize(string $timestamp): string
    {
        $timestamp = mb_trim($timestamp);
        if ($timestamp === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($timestamp);
            $normalized = $date->format('Y-m-d H:i:s');

            return $normalized === '0000-00-00 00:00:00' ? '' : $normalized;
        } catch (Throwable) {
            // Continue to custom formats
        }

        foreach (self::FORMATS as $format) {
            try {
                $date = DateTimeImmutable::createFromFormat($format, $timestamp);
                if ($date !== false) {
                    $normalized = $date->format('Y-m-d H:i:s');

                    return $normalized === '0000-00-00 00:00:00' ? '' : $normalized;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $timestamp;
    }

    public static function getTimelineKey(string $timestamp, string $format): string
    {
        $normalizedTs = self::normalize($timestamp);
        if ($normalizedTs === '' || str_starts_with($normalizedTs, '0000') || mb_strlen($normalizedTs) < 10) {
            return $timestamp;
        }

        return match ($format) {
            'minute' => mb_substr($normalizedTs, 0, 16) . ':00',
            'day' => mb_substr($normalizedTs, 0, 10) . ' 00:00:00',
            'month' => mb_substr($normalizedTs, 0, 7) . '-01 00:00:00',
            'year' => mb_substr($normalizedTs, 0, 4) . '-01-01 00:00:00',
            default => mb_substr($normalizedTs, 0, 13) . ':00:00', // hour
        };
    }

    /**
     * @param array<array-key, int> $timeline
     * @return array<array-key, int>
     */
    public static function fillTimelineGaps(array $timeline, string $format, ?DateTimeImmutable $startRange = null, ?DateTimeImmutable $endRange = null): array
    {
        if (empty($timeline) && $startRange === null && $endRange === null) {
            return [];
        }

        $normalizedTimeline = [];
        foreach ($timeline as $key => $value) {
            $newKey = self::getTimelineKey((string) $key, $format);
            $normalizedTimeline[$newKey] = ($normalizedTimeline[$newKey] ?? 0) + $value;
        }
        $timeline = $normalizedTimeline;

        $keys = array_keys($timeline);
        if (empty($keys) && $startRange === null && $endRange === null) {
            return [];
        }

        if (!empty($keys)) {
            sort($keys);
        }

        try {
            /** @var string[] $keys */
            $start = $startRange;
            if ($start === null && !empty($keys)) {
                $start = new DateTimeImmutable((string) min($keys));
            }

            $end = $endRange;
            if ($end === null && !empty($keys)) {
                $end = new DateTimeImmutable((string) max($keys));
            }

            if ($start === null || $end === null) {
                return $timeline;
            }
        } catch (Throwable) {
            return $timeline;
        }

        $interval = match ($format) {
            'minute' => 'PT1M',
            'day' => 'P1D',
            'month' => 'P1M',
            'year' => 'P1Y',
            default => 'PT1H',
        };

        $dateFormat = match ($format) {
            'minute' => 'Y-m-d H:i:00',
            'day' => 'Y-m-d 00:00:00',
            'month' => 'Y-m-01 00:00:00',
            'year' => 'Y-01-01 00:00:00',
            default => 'Y-m-d H:00:00',
        };

        $startKey = self::getTimelineKey($start->format('Y-m-d H:i:s'), $format);
        $endKey = self::getTimelineKey($end->format('Y-m-d H:i:s'), $format);

        $current = new DateTimeImmutable($startKey);
        $limit = new DateTimeImmutable($endKey);

        while ($current <= $limit) {
            $key = $current->format($dateFormat);
            if (!isset($timeline[$key])) {
                $timeline[$key] = 0;
            }
            $current = $current->add(new DateInterval($interval));
        }

        ksort($timeline);

        return $timeline;
    }
}
