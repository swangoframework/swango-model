<?php
namespace Swango\Model;
class AbstractBaseGatewayConstructHelper {
    private static bool $flag = false;
    protected function __construct(...$index) {
        if (self::$flag) {
            exit();
        }
        self::$flag = true;
    }
    protected static function swangoFrameworkModelInitialized(): bool {
        return self::$flag;
    }
}
/**
 *
 * @author fdream
 * @property Type[] $property_map
 * @property string $model_name
 * @property string $table_name
 */
abstract class AbstractBaseGateway extends AbstractBaseGatewayConstructHelper {
    protected const USE_MASTER_DB_FOR_INDEX_QUERY = false;
    protected const INSTANCE_SIZE = 1024;
    abstract protected static function loadFromDB(array $where,
                                                  bool  $for_update = false,
                                                  bool  $force_master_DB = false): ?object;
    /**
     * 由于构造函数是protected，只有通过闭包形式将构造权传给Factory类
     */
    private static function init(): void {
        $func_construct = fn(string $model_name, ...$index): AbstractBaseGateway => new $model_name(...$index);
        Factory::init($func_construct->bindTo(new AbstractBaseGatewayConstructHelper()));
    }
    protected static function initCacheTable() {
        // do nothing
    }
    abstract protected static function initModel();
    public static function onLoad(): void {
        if (! self::swangoFrameworkModelInitialized()) {
            self::init();
        }
        static::$model_name = static::class;
        static::$table_name ??= strtolower(str_replace('\\', '_', static::class));
        static::initModel();
        static::initCacheTable();
    }
    public static function getPropertyList(): array {
        return array_keys(static::$property_map);
    }
    protected static function hasProperty(string $key): bool {
        return array_key_exists($key, static::$property_map) && ! in_array($key, static::INDEX);
    }
    public static function getFactory(): Factory {
        return \SysContext::hGet('factory', static::$model_name) ??
            new Factory(static::$model_name, static::$table_name, static::INSTANCE_SIZE);
    }
    /**
     * 获取model对应的选择器
     * 如需要用到，则需要在MODEL/PATH/MODELNAME/Selector.php中定义class Selector
     */
    public static function getSelector(bool $use_slave_DB = true): \Swango\Model\Operator\Selector {
        $context_key = 'selector-' . ($use_slave_DB ? 's' : 'm');
        $selector = \SysContext::hGet($context_key, static::$model_name);
        if (! isset($selector)) {
            $factory = static::getFactory();
            $class_name = static::$model_name . '\\Selector';
            if (class_exists($class_name)) {
                $selector = new $class_name($use_slave_DB, $factory, static::$select ?? null);
            } else {
                $selector = new Operator\Selector($use_slave_DB, $factory, static::$select ?? null);
            }
            \SysContext::hSet($context_key, static::$model_name, $selector);
        }
        return $selector;
    }
    /**
     * 获取model对应的更新器
     * 如需要用到，则需要在MODEL/PATH/MODELNAME/Updator.php中定义class Updator
     */
    public static function getUpdator(): \Swango\Model\Operator\Updator {
        $updator = \SysContext::hGet('updator', static::$model_name);
        if (! isset($updator)) {
            $class_name = static::$model_name . '\\Updator';
            if (class_exists($class_name)) {
                $updator = new $class_name(static::$table_name);
            } else {
                $updator = new Operator\Updator(static::$table_name);
            }
            \SysContext::hSet('updator', static::$model_name, $updator);
        }
        return $updator;
    }
    protected object $profile;
    protected array $where;
    public function toArray(): array {
        $ret = (array)$this->profile;
        foreach ($this->where as $k => $v)
            $ret[$k] = $v;
        return $ret;
    }
    public function getProfileForClient(): array {
        return $this->toArray();
    }
    /**
     *
     * @param mixed ...$index
     */
    protected function __construct(...$index) {
        $this->where = [];
        foreach (static::INDEX as $k => $v) {
            $key = &$index[$k];
            if (array_key_exists($v, static::$property_map)) {
                $key = static::$property_map[$v]->intoProfile($key);
            } elseif (is_numeric($key)) {
                $key = (int)$key;
            }
            $this->where[$v] = $key;
        }
        $this->profile = new \stdClass();
        static::getFactory()->saveInstance($this, ...$index);
        $this->initProfile();
    }
    protected function initProfile(): void {
    }
    /**
     * 从数据库加载本model所有成员，直接覆盖现有的所有成员（若存在）。
     * 若对应行不存在，则会直接抛出 PATH\MODELNAME\Exception\MODELNAMENotFoundException
     */
    public function load(bool $for_update = false): self {
        $result = static::loadFromDB($this->where,
            $for_update,
            $this->isLoadAsForUpdate() || static::USE_MASTER_DB_FOR_INDEX_QUERY
        );
        if (! $result) {
            if (method_exists($this, 'onNotFound')) {
                $this->onNotFound('Load');
                return $this;
            }
            $exception_name = static::getFactory()->getNotFoundExceptionName();
            throw new $exception_name(...array_values($this->where));
        }
        $for_update && $this instanceof AbstractModel && $this->_transaction_serial = \Gateway::getTransactionSerial();
        $this->injectProfile($result);
        return $this;
    }
    public function isLoadAsForUpdate(): bool {
        return false;
    }
    /**
     * 若key不为model的成员，会抛出异常。
     * 若key为model的成员但未加载，则读取数据库
     */
    public function __get($key) {
        if (array_key_exists($key, $this->where)) {
            return $this->where[$key];
        }
        if (! self::hasProperty($key)) {
            throw new Exception\ColumnNotExistsException($key);
        }
        if (! property_exists($this->profile, $key)) {
            $this->load();
        }
        return $this->profile->{$key};
    }
    /**
     * 若key为属于model的成员，则转换后存入profile，否则直接绑定在对象上
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value) {
        if (array_key_exists($key, $this->where)) {
            throw new Exception\CannotChangeIndexException();
        } elseif (self::hasProperty($key)) {
            if ($value instanceof IdIndexedModel) {
                $value = $value->getId();
            }
            $value = static::$property_map[$key]->intoProfile($value);
            $this->profile->{$key} = $value;
            if (method_exists($this, 'onInject')) {
                $ob = new \stdClass();
                $ob->{$key} = $value;
                $this->onInject($ob);
            }
        } else {
            $this->{$key} = $value;
        }
    }
    public function __isset($key) {
        if (array_key_exists($key, $this->where)) {
            return true;
        } elseif (self::hasProperty($key)) {
            ! property_exists($this->profile, $key) && $this->load();
            return isset($this->profile->{$key});
        }
        return false;
    }
    public function __unset($key) {
        if (array_key_exists($key, $this->where)) {
            throw new Exception\CannotChangeIndexException();
        } elseif (self::hasProperty($key) && property_exists($this->profile, $key)) {
            unset($this->profile->{$key});
        } else {
            trigger_error('Try to unset property not existed ' . static::$model_name . "($key)");
        }
    }
    /**
     * 注入内容，依据设置转换每一项的类型，忽略不属于model的成员。
     * 若设置有onInject回调，将会执行
     */
    public function injectProfile($profile): self {
        foreach ($profile as $k => $v)
            self::hasProperty($k) && $this->profile->{$k} = static::$property_map[$k]->intoProfile($v);
        method_exists($this, 'onInject') && $this->onInject((object)$profile);
        return $this;
    }
    protected function removeFromInstances(): self {
        $indexes = [];
        foreach (static::INDEX as $key_name)
            $indexes[] = $this->where[$key_name];
        static::getFactory()->deleteInstance(...$indexes);
        return $this;
    }
}