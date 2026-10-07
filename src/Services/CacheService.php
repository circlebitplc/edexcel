<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Cache;
use Throwable;

/**
 * Thin wrapper around the file Cache class in config/cache.php.
 *
 * Documented public key helpers (never use for personalized / sensitive data):
 * - subjects, teachers, rooms, homepage, public_timetable, official_exams
 */
final class CacheService
{
    private Cache $cache;

    public function __construct(?Cache $cache = null, int $defaultTtl = 3600)
    {
        if ($cache !== null) {
            $this->cache = $cache;
            return;
        }
        if (!class_exists(Cache::class, false)) {
            $path = dirname(__DIR__, 2) . '/config/cache.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        $this->cache = class_exists(Cache::class, false)
            ? new Cache(null, $defaultTtl)
            : new Cache(__DIR__ . '/../../cache/', $defaultTtl);
    }

    public static function keySubjects(): string
    {
        return 'catalog:subjects:v1';
    }

    public static function keyTeachers(): string
    {
        return 'catalog:teachers:v1';
    }

    public static function keyRooms(): string
    {
        return 'catalog:rooms:v1';
    }

    public static function keyHomepage(): string
    {
        return 'public:homepage:v1';
    }

    public static function keyPublicTimetable(string $weekStart = ''): string
    {
        $week = $weekStart !== '' ? $weekStart : date('Y-m-d', strtotime('monday this week'));
        return 'public:timetable:' . $week;
    }

    public static function keyOfficialExams(?int $seriesId = null): string
    {
        return $seriesId !== null && $seriesId > 0
            ? 'public:official_exams:series:' . $seriesId
            : 'public:official_exams:index';
    }

    public function get(string $key): mixed
    {
        try {
            return $this->cache->get($key);
        } catch (Throwable $e) {
            return null;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        try {
            $this->cache->set($key, $value, $ttl);
            $this->trackKey($key);
        } catch (Throwable $e) {
        }
    }

    public function forget(string $key): bool
    {
        try {
            $ok = (bool)$this->cache->delete($key);
            $this->untrackKey($key);
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * Best-effort prefix invalidation using a sidecar key index.
     */
    public function invalidatePrefix(string $prefix): int
    {
        $cleared = 0;
        $index = $this->loadIndex();
        foreach ($index as $key => $_) {
            if (!is_string($key)) {
                continue;
            }
            if (str_starts_with($key, $prefix)) {
                if ($this->forget($key)) {
                    $cleared++;
                }
            }
        }
        return $cleared;
    }

    /**
     * Track a key for prefix invalidation. Call after set/remember when needed.
     */
    public function trackKey(string $key): void
    {
        $index = $this->loadIndex();
        $index[$key] = time();
        $this->saveIndex($index);
    }

    private function untrackKey(string $key): void
    {
        $index = $this->loadIndex();
        unset($index[$key]);
        $this->saveIndex($index);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadIndex(): array
    {
        $indexFile = dirname(__DIR__, 2) . '/cache/_key_index.json';
        if (!is_file($indexFile)) {
            return [];
        }
        $raw = file_get_contents($indexFile);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $index
     */
    private function saveIndex(array $index): void
    {
        $indexFile = dirname(__DIR__, 2) . '/cache/_key_index.json';
        $dir = dirname($indexFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($indexFile, json_encode($index));
    }
}
