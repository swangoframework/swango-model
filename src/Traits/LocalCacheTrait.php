<?php
namespace Swango\Model\Traits;
use Swango\Model\LocalCache;

/**
 * 将Model以文件的形式缓存在文件目录中，在发生remove或update时会清除缓存文件
 *
 * @author fdream
 */
trait LocalCacheTrait {
    /**
     *
     * @var \LocalCache
     */
    private static $local_cache;
    protected static $cache_lifetime = 86400;
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
        if (! isset(self::$local_cache))
            return parent::loadFromDB($where, $for_update, $force_nornal_DB);
        $key = static::makeLocalCacheKey($where);
        if ($for_update)
            $profile = parent::loadFromDB($where, true, $force_nornal_DB);
        else {
            $profile = self::$local_cache->get($key);
            if (isset($profile))
                return $profile['__f__'] === 1 ? null : (object)$profile;
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
    public static function deleteCache(...$index): bool {
        if (! isset(self::$local_cache))
            return false;
        $key = implode('`', $index);
        $result = self::$local_cache->del($key);
        \Swoole\Timer::after(1000, [
            self::$local_cache,
            'del'
        ], $key);

        InternelCmd\DeleteLocalCache::broadcast(static::class, ...$index);
        return $result;
    }
    public static function deleteCacheWithoutBroadcast(...$index): bool {
        if (! isset(self::$local_cache))
            return false;
        $key = implode('`', $index);
        \Swoole\Timer::after(1000, [
            self::$local_cache,
            'del'
        ], $key);
        return self::$local_cache->del($key);
    }
    private function _deleteCache() {
        if (! isset(self::$local_cache))
            return;

        $key = static::makeLocalCacheKey($this->where);
        self::$local_cache->del($key);
        \Swoole\Timer::after(1000, [
            self::$local_cache,
            'del'
        ], $key);
        InternelCmd\DeleteLocalCache::broadcast(static::class, $key);
    }
    public function broadcastLocalCacheDelete(): int {
        $key = static::makeLocalCacheKey($this->where);
        return InternelCmd\DeleteLocalCache::broadcast(static::class, $key);
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