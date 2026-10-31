<?php
namespace App\Services;

class RateLimiter {
    public static function hit(string $key, int $limit, int $windowSeconds): array {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/rl_' . hash('sha256', $key) . '.json';
        $fh = @fopen($file, 'c+');
        if (!$fh) {
            return ['allowed' => true, 'count' => 0, 'retry_after' => 0];
        }

        $allowed = true;
        $count = 0;
        $retryAfter = 0;

        if (flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            $state = json_decode((string)$raw, true);
            $now = time();

            if (!is_array($state) || empty($state['start']) || ($now - (int)$state['start']) >= $windowSeconds) {
                $state = ['start' => $now, 'count' => 0];
            }

            $state['count'] = (int)$state['count'] + 1;
            $count = $state['count'];
            $allowed = $count <= $limit;

            if (!$allowed) {
                $retryAfter = max(1, $windowSeconds - ($now - (int)$state['start']));
            }

            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, json_encode($state));
            fflush($fh);
            flock($fh, LOCK_UN);
        }

        fclose($fh);

        return ['allowed' => $allowed, 'count' => $count, 'retry_after' => $retryAfter];
    }
}
