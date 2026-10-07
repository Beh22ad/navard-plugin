<?php

namespace Navard\Cron;

if (! defined('ABSPATH')) {
    exit;
}

final class Scheduler
{

    public const HOOK = 'navard_cron_update';

    private const SLOTS = [2, 10, 18];

    public function hooks(): void
    {
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [$this, 'ensure_scheduled'], 20);
    }

    public static function schedule(): void
    {
        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(self::next_slot_ts(), 'navard_three_daily', self::HOOK);
        }
    }

    public static function unschedule(): void
    {
        $ts = wp_next_scheduled(self::HOOK);
        while ($ts) {
            wp_unschedule_event($ts, self::HOOK);
            $ts = wp_next_scheduled(self::HOOK);
        }
    }

    public function ensure_scheduled(): void
    {
        self::schedule();
    }

    public function run(): void
    {
        $endpoint = new Endpoint();
        $endpoint->run_batch_silent();
    }

    public static function describe(): string
    {
        $next = wp_next_scheduled(self::HOOK);
        if (! $next) {
            return 'زمان‌بندی نشده';
        }
        try {
            $dt = new \DateTime('@' . $next);
            $dt->setTimezone(new \DateTimeZone('Asia/Tehran'));
            return $dt->format('Y-m-d H:i');
        } catch (\Exception $e) {
            return gmdate('Y-m-d H:i', $next);
        }
    }

    private static function next_slot_ts(): int
    {
        try {
            $tz = new \DateTimeZone('Asia/Tehran');
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }
        $now = new \DateTime('now', $tz);
        foreach (self::SLOTS as $h) {
            $candidate = clone $now;
            $candidate->setTime($h, 0, 0);
            if ($candidate->getTimestamp() > $now->getTimestamp()) {
                return $candidate->getTimestamp();
            }
        }
        $tomorrow = clone $now;
        $tomorrow->modify('+1 day')->setTime(self::SLOTS[0], 0, 0);
        return $tomorrow->getTimestamp();
    }
}

add_filter('cron_schedules', static function ($schedules) {
    $schedules['navard_three_daily'] = [
        'interval' => 8 * HOUR_IN_SECONDS,
        'display'  => 'نورد: سه بار در روز',
    ];
    return $schedules;
});
