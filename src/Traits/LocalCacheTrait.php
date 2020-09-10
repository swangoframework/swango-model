<?php
namespace Swango\Model\Traits;
use Swango\Model\LocalCache;
trait LocalCacheTrait {
    private static LocalCache $local_cache;
    protected static int $cache_lifetime = 86400;
    public static function initCacheTable() {
        self::$local_cache = LocalCache::getInstance(static::class);
    }
    protected static function makeLocalCacheKey(array $where): string {
        $ids = [];
        foreach (self::INDEX as $keyname)
            $ids[] = $where[$keyname];
        return implode('`', $ids);
    }
    protected static function loadFromDB($where, bool $for_update = false, bool $force_nornal_DB = false): ?\stdClass {
        if (null === self::$local_cache) {
            return parent::loadFromDB($where, $for_update, $force_nornal_DB);
        }
        $key = static::makeLocalCacheKey($where);
        if ($for_update) {
            $profile = parent::loadFromDB($where, true, $force_nornal_DB);
        } else {
            $profile = self::$local_cache->get($key);
            if (isset($profile)) {
                return 1 === $profile['__f__'] ? null : (object)$profile;
            }
            $profile = parent::loadFromDB($where, false, $force_nornal_DB);
        }
        if (isset($profile)) {
            self::$local_cache->set($key, (array)$profile, static::$cache_lifetime);
            return $profile;
        } else {
            self::$local_cache->set($key, [
                '__f__' => 1
            ], static::$cache_lifetime);
            return null;
        }
    }
    public static function deleteCache(array $where, bool $broadcast = true): bool {
        if (null === self::$local_cache) {
            $result = false;
        } else {
            $key = static::makeLocalCacheKey($where);
            $result = self::$local_cache->del($key);
            \Swoole\Timer::after(1000, [
                self::$local_cache,
                'del'
            ], $key);
        }
        if ($broadcast) {
            InternelCmd\DeleteLocalCache::broadcast(static::class, $where);
        }
        return $result;
    }
    private function _deleteCache() {
        if (null !== self::$local_cache) {
            $key = static::makeLocalCacheKey($this->where);
            self::$local_cache->del($key);
            \Swoole\Timer::after(1000, [
                self::$local_cache,
                'del'
            ], $key);
        }
        $this->broadcastLocalCacheDelete();
    }
    private function broadcastLocalCacheDelete(): int {
        return InternelCmd\DeleteLocalCache::broadcast(static::class, $this->where);
    }
    public function remove(): bool {
        $ret = parent::remove();
        $this->_deleteCache();
        return $ret;
    }
    public function update(array $new_profile): int {
        $ret = parent::update($new_profile);
        $this->_deleteCache();
        return $ret;
    }
}