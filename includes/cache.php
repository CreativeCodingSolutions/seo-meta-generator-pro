<?php
/**
 * SEO Meta Generator Pro — File-based Cache Layer
 *
 * Simple JSON file cache with TTL support.
 * Used to avoid re-analysing the same URL within a configurable time window.
 *
 * @package SEO Meta Generator Pro
 * @version 3.1.0
 */

namespace SEOMetaGen\Cache;

class FileCache
{
    private string $cacheDir;
    private int $defaultTtl;

    /**
     * @param string $cacheDir  Absolute path to cache directory
     * @param int    $defaultTtl Default TTL in seconds (default: 3600 = 1 hour)
     */
    public function __construct(string $cacheDir = '', int $defaultTtl = 3600)
    {
        $this->cacheDir = $cacheDir ?: (dirname(__DIR__) . '/data/cache');
        $this->defaultTtl = $defaultTtl;
        $this->ensureDir();
    }

    /**
     * Get a cached value. Returns null if not found or expired.
     */
    public function get(string $key): ?array
    {
        $file = $this->keyToFile($key);
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || !isset($data['expires_at'], $data['value'])) {
            @unlink($file);
            return null;
        }

        if ($data['expires_at'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['value'];
    }

    /**
     * Store a value in cache.
     */
    public function set(string $key, array $value, ?int $ttl = null): bool
    {
        $file = $this->keyToFile($key);
        $payload = json_encode([
            'expires_at' => time() + ($ttl ?? $this->defaultTtl),
            'value'      => $value,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return file_put_contents($file, $payload, LOCK_EX) !== false;
    }

    /**
     * Delete a single cache entry.
     */
    public function delete(string $key): bool
    {
        $file = $this->keyToFile($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    /**
     * Clear all cache entries.
     */
    public function clear(): int
    {
        $count = 0;
        foreach (glob($this->cacheDir . '/*.json') as $f) {
            if (@unlink($f)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        $files = glob($this->cacheDir . '/*.json');
        $total = count($files);
        $expired = 0;
        $now = time();

        foreach ($files as $f) {
            $data = @json_decode(file_get_contents($f), true);
            if (!is_array($data) || ($data['expires_at'] ?? 0) < $now) {
                $expired++;
            }
        }

        return [
            'total_entries'   => $total,
            'active_entries'  => $total - $expired,
            'expired_entries' => $expired,
            'cache_dir'       => $this->cacheDir,
            'default_ttl'     => $this->defaultTtl,
        ];
    }

    // ── Private ───────────────────────────────────────────────

    private function ensureDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    private function keyToFile(string $key): string
    {
        return $this->cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
    }
}
