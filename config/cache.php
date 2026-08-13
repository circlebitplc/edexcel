<?php
// config/cache.php
// Simple file-based caching system

class Cache
{
    private $cache_dir;
    private $ttl = 3600; // Default 1 hour

    public function __construct($cache_dir = null, $ttl = 3600)
    {
        $this->cache_dir = $cache_dir ?: __DIR__ . '/../cache/';
        $this->ttl = $ttl;
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
    }

    public function get($key)
    {
        $filename = $this->getFilename($key);
        if (!file_exists($filename)) return null;
        
        $data = file_get_contents($filename);
        $cached = unserialize($data);
        
        if ($cached['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $cached['value'];
    }

    public function set($key, $value, $ttl = null)
    {
        $ttl = $ttl ?: $this->ttl;
        $data = [
            'expires' => time() + $ttl,
            'value' => $value
        ];
        $filename = $this->getFilename($key);
        file_put_contents($filename, serialize($data));
    }

    public function delete($key)
    {
        $filename = $this->getFilename($key);
        if (file_exists($filename)) {
            unlink($filename);
            return true;
        }
        return false;
    }

    public function clear()
    {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    public function exists($key)
    {
        $filename = $this->getFilename($key);
        if (!file_exists($filename)) return false;
        
        $data = file_get_contents($filename);
        $cached = unserialize($data);
        return $cached['expires'] >= time();
    }

    private function getFilename($key)
    {
        return $this->cache_dir . md5($key) . '.cache';
    }
}

// Global cache instance
$cache = new Cache();