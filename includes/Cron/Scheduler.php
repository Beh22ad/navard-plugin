<?php

namespace Navard\Cron;

if (! defined('ABSPATH')) {
    exit;
}

final class Scheduler
{

    public const HOOK = 'navard_cron_update';

    /** Three runs per 24h: 02:00, 10:00, 18:00 Tehran. */
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
        // Kick off one batch; the endpoint engine continues via locks/state.
        $endpoint = new Endpoint();
        $endpoint->run_batch_silent();
    }

    public static function describe(): string
    {
        $next = wp_next_scheduled(self::HOOK);
        if (! $next) {
            return 'زمان‌بندی نشده';
        }
        return 'اجرای بعدی: ' . gmdate('Y-m-d H:i:s', $next) . ' (UTC)';
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
        // All slots passed today → first slot tomorrow.
        $tomorrow = clone $now;
        $tomorrow->modify('+1 day')->setTime(self::SLOTS[0], 0, 0);
        return $tomorrow->getTimestamp();
    }
}

// Register the custom interval.
add_filter('cron_schedules', static function ($schedules) {
    $schedules['navard_three_daily'] = [
        'interval' => 8 * HOUR_IN_SECONDS,
        'display'  => 'نورد: سه بار در روز',
    ];
    return $schedules;
});
