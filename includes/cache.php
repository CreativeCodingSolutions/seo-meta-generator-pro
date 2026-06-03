<?php
/**
 * SEO Meta Generator Pro — File-based Cache Layer
 *
 * Simple JSON file cache with TTL support, size limits, and statistics.
 * Used to avoid re-analysing the same URL within a configurable time window.
 *
 * @package SEO Meta Generator Pro
 * @version 3.2.0
 */

namespace SEOMetaGen\Cache;

class FileCache
{
    private string $cacheDir;
    private int $defaultTtl;
    private int $maxEntries;
    private int $maxSizeBytes;

    /**
     * @param string $cacheDir       Absolute path to cache directory
     * @param int    $defaultTtl     Default TTL in seconds (default: 3600 = 1 hour)
     * @param int    $maxEntries     Maximum number of cache entries (default: 1000)
     * @param int    $maxSizeBytes   Maximum total cache size in bytes (default: 50 MB)
     */
    public function __construct(
        string $cacheDir = '',
        int $defaultTtl = 3600,
        int $maxEntries = 1000,
        int $maxSizeBytes = 52428800
    ) {
        $this->cacheDir     = $cacheDir ?: (dirname(__DIR__) . '/data/cache');
        $this->defaultTtl   = $defaultTtl;
        $this->maxEntries   = $maxEntries;
        $this->maxSizeBytes = $maxSizeBytes;
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
     * Enforces size limits by evicting oldest entries if needed.
     */
    public function set(string $key, array $value, ?int $ttl = null): bool
    {
        $file = $this->keyToFile($key);

        // Ensure directory exists
        $this->ensureDir();

        $payload = json_encode([
            'expires_at' => time() + ($ttl ?? $this->defaultTtl),
            'value'      => $value,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $result = file_put_contents($file, $payload, LOCK_EX);

        if ($result === false) {
            return false;
        }

        // Enforce size limits after write
        $this->enforceLimits();

        return true;
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
     * Returns the number of entries deleted.
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
     * Clear only expired cache entries.
     * Returns the number of entries purged.
     */
    public function purgeExpired(): int
    {
        $count = 0;
        $now   = time();
        foreach (glob($this->cacheDir . '/*.json') as $f) {
            $data = @json_decode(file_get_contents($f), true);
            if (!is_array($data) || ($data['expires_at'] ?? 0) < $now) {
                if (@unlink($f)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        $files  = glob($this->cacheDir . '/*.json');
        $total  = is_array($files) ? count($files) : 0;
        $expired = 0;
        $now    = time();
        $totalSize = 0;

        if (is_array($files)) {
            foreach ($files as $f) {
                $totalSize += filesize($f);
                $data = @json_decode(file_get_contents($f), true);
                if (!is_array($data) || ($data['expires_at'] ?? 0) < $now) {
                    $expired++;
                }
            }
        }

        return [
            'total_entries'   => $total,
            'active_entries'  => $total - $expired,
            'expired_entries' => $expired,
            'total_size_bytes'=> $totalSize,
            'total_size_human'=> $this->formatBytes($totalSize),
            'max_entries'     => $this->maxEntries,
            'max_size_bytes'  => $this->maxSizeBytes,
            'max_size_human'  => $this->formatBytes($this->maxSizeBytes),
            'cache_dir'       => $this->cacheDir,
            'default_ttl'     => $this->defaultTtl,
            'usage_percent'   => $this->maxEntries > 0
                ? round(($total / $this->maxEntries) * 100, 1)
                : 0,
        ];
    }

    /**
     * Check if cache is within size limits.
     */
    public function isWithinLimits(): bool
    {
        $stats = $this->getStats();
        return $stats['total_entries'] <= $this->maxEntries
            && $stats['total_size_bytes'] <= $this->maxSizeBytes;
    }

    /**
     * Get the cache directory path.
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Get the default TTL.
     */
    public function getDefaultTtl(): int
    {
        return $this->defaultTtl;
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

    /**
     * Enforce cache size limits by evicting oldest entries.
     */
    private function enforceLimits(): void
    {
        $files = glob($this->cacheDir . '/*.json');
        if (!is_array($files)) {
            return;
        }

        $total = count($files);

        // Check entry count limit
        if ($total <= $this->maxEntries) {
            // Check size limit
            $totalSize = 0;
            foreach ($files as $f) {
                $totalSize += filesize($f);
            }
            if ($totalSize <= $this->maxSizeBytes) {
                return; // Within both limits
            }
        }

        // Sort by modification time ascending (oldest first)
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));

        // Evict oldest entries until within limits
        foreach ($files as $f) {
            // Re-check limits
            $currentFiles = glob($this->cacheDir . '/*.json');
            if (!is_array($currentFiles)) {
                break;
            }
            if (count($currentFiles) <= $this->maxEntries * 0.8) {
                // Check size
                $currentSize = 0;
                foreach ($currentFiles as $cf) {
                    $currentSize += filesize($cf);
                }
                if ($currentSize <= $this->maxSizeBytes * 0.8) {
                    break; // Within 80% of limits, stop evicting
                }
            }
            @unlink($f);
        }
    }

    /**
     * Format bytes to human-readable string.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
