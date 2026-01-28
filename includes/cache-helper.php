<?php
// Basit Önbellekleme Sınıfı (Redis veya Memcached)
class CacheSystem {
    private $handler = null;
    private $active = false;
    private $type = 'none';

    public function __construct() {
        // 1. Redis Öncelikli
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                // Timeout 0.5 sn (Hızlı bağlantı)
                if (@$redis->connect('127.0.0.1', 6379, 0.5)) {
                    $this->handler = $redis;
                    $this->active = true;
                    $this->type = 'redis';
                    return;
                }
            } catch (Exception $e) {
                // Redis bağlantı hatası, sessizce geç
            }
        }

        // 2. Memcached Alternatif
        if (class_exists('Memcached')) {
            try {
                $memcached = new Memcached();
                $memcached->addServer('127.0.0.1', 11211);
                // Bağlantı kontrolü
                if ($memcached->getStats()) {
                    $this->handler = $memcached;
                    $this->active = true;
                    $this->type = 'memcached';
                }
            } catch (Exception $e) {
                // Memcached hatası
            }
        }
    }

    public function get($key) {
        if (!$this->active) return null;

        if ($this->type === 'redis') {
            $data = $this->handler->get($key);
            return $data !== false ? unserialize($data) : null;
        } elseif ($this->type === 'memcached') {
            $data = $this->handler->get($key);
            return $this->handler->getResultCode() === Memcached::RES_SUCCESS ? $data : null;
        }
        return null;
    }

    public function set($key, $value, $ttl = 3600) {
        if (!$this->active) return false;

        if ($this->type === 'redis') {
            return $this->handler->set($key, serialize($value), $ttl);
        } elseif ($this->type === 'memcached') {
            return $this->handler->set($key, $value, $ttl);
        }
        return false;
    }

    public function delete($key) {
        if (!$this->active) return false;

        if ($this->type === 'redis') {
            return $this->handler->del($key);
        } elseif ($this->type === 'memcached') {
            return $this->handler->delete($key);
        }
        return false;
    }

    public function flush() {
        if (!$this->active) return false;

        if ($this->type === 'redis') {
            return $this->handler->flushAll();
        } elseif ($this->type === 'memcached') {
            return $this->handler->flush();
        }
        return false;
    }
    
    public function isEnabled() {
        return $this->active;
    }
}

global $cache;
$cache = new CacheSystem();