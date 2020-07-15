<?php
namespace Swango\Model;
abstract class LocalCache {
    private static $instances = [];
    protected $size = 8192, $table, $column = [];
    public static function init() {
        foreach (self::recursiveModelCacheFolder() as $file) {
            require \Swango\Environment::getDir()->model_cache . $file;
            $model_class_name = str_replace([
                '/',
                '.php'
            ], [
                '\\',
                ''
            ], $file);
            $class_name = 'ModelCache\\' . $model_class_name;
            self::$instances[$model_class_name] = new $class_name();
        }
        self::class;
    }
    protected static function recursiveModelCacheFolder() {
        $base_dir = \Swango\Environment::getDir()->model_cache;
        if (! is_dir($base_dir))
            return;
        $queue = new \SplQueue();
        $queue->enqueue('');
        do {
            $dirName = $queue->dequeue();
            $handle = opendir($base_dir . $dirName);
            for($file = readdir($handle); $file; $file = readdir($handle))
                if ($file !== '.' && $file !== '..') {
                    $path = $dirName . $file;
                    $dir = $base_dir . $dirName . $file;
                    if (is_dir($dir)) {
                        $queue->enqueue($path . '/');
                    } elseif (explode('.', $file)[1] === 'php')
                        yield $path;
                }
            closedir($handle);
        } while ( ! $queue->isEmpty() );
    }
    public static function getInstance(string $class_name): ?self {
        if (array_key_exists($class_name, self::$instances))
            return self::$instances[$class_name];
        return null;
    }
    public static function getAllInstanceSizes(): array {
        $ret = [];
        foreach (self::$instances as $key=>$instance)
            $ret[$key] = $instance->table->memorySize;
        return $ret;
    }
    protected function __construct() {
        $this->createSwooleTable($this->size);
    }
    protected function createSwooleTable(int $size) {
        $table = new \Swoole\Table($this->size);
        foreach ($this->column as $name=>$type) {
            if (is_array($type)) {
                [
                    $type,
                    $size
                ] = $type;
                $table->column($name, $type, $size);
            } else
                $table->column($name, $type);
        }
        // 更新时间
        $table->column('__t1__', \Swoole\Table::TYPE_INT);
        // 过期时间
        $table->column('__t2__', \Swoole\Table::TYPE_INT);
        // 空标记，为1时表示该条记录not found
        $table->column('__f__', \Swoole\Table::TYPE_INT, 1);
        if (! $table->create())
            throw new \Exception('Create swoole table fail');
        $this->table = $table;
    }
    public function set(string $key, array $value, int $expired = 86400): bool {
        $now = \Time\now();
        $value['__t1__'] = $now;
        $value['__t2__'] = $now + $expired;
        if (! array_key_exists('__f__', $value))
            $value['__f__'] = 0;
        return $this->table->set($key, $value);
    }
    public function get(string $key): ?array {
        $data = $this->table->get($key);
        if ($data === false)
            return null;
        if ($data['__t2__'] < \Time\now()) {
            $this->table->del($key);
            return null;
        }
        unset($data['__t1__']);
        unset($data['__t2__']);
        return $data;
    }
    /**
     *
     * @param string $key
     * @return bool 记录是否存在
     */
    public function del(string $key): bool {
        return $this->table->del($key);
    }
    public function exist(string $key): bool {
        return $this->table->exist($key);
    }
}