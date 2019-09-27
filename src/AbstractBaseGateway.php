<?php
namespace Swango\Model;
class AbstractBaseGatewayConstructHelper {
    private static $flag = 0;
    protected function __construct(...$index) {
        if (self::$flag)
            exit();
        self::$flag = 1;
    }
}
/**
 *
 * @author fdream
 * @property \type $property_map[]
 * @property string $model_name
 * @property string $table_name
 */
abstract class AbstractBaseGateway extends AbstractBaseGatewayConstructHelper {
    // abstract public static function onLoad();
    abstract protected static function loadFromDB($where, bool $for_update = false, bool $force_nornal_DB = false): ?\stdClass;
    /**
     * 由于构造函数是protected，只有通过闭包形式将构造权传给Factory类
     */
    public static function init(): void {
        $func_construct = (function ($model_name, ...$index): AbstractBaseGateway {
            return new $model_name(...$index);
        });
        Factory::init($func_construct->bindTo(new AbstractBaseGatewayConstructHelper()));
    }
    public static function getPropertyList(): array {
        return array_merge(array_keys(static::$property_map), static::INDEX);
    }
    protected static function hasProperty(string $key): bool {
        return array_key_exists($key, static::$property_map);
    }
    public static function getFactory(): Factory {
        $factory = \SysContext::hGet('factory', static::$model_name);
        if (! isset($factory)) {
            $factory = new Factory(static::$model_name, static::$table_name);
        }
        return $factory;
    }
    /**
     * 获取model对应的选择器
     * 如需要用到，则需要在MODEL/PATH/MODELNAME/Selector.php中定义class Selector
     */
    public static function getSelector(bool $use_slave_DB = true): \Swango\Model\Operater\Selector {
        $factory = static::getFactory();
        $name = static::$model_name . '\\Selector';
        if ($use_slave_DB) {
            if (! isset($factory->selector)) {
                if (class_exists($name))
                    $factory->selector = new $name($use_slave_DB, $factory, static::$select ?? null);
                else
                    $factory->selector = new \Swango\Model\Operater\Selector($use_slave_DB, $factory,
                        static::$select ?? null);
            }
            return $factory->selector;
        } else {
            if (! isset($factory->selector_master)) {
                if (class_exists($name))
                    $factory->selector_master = new $name($use_slave_DB, $factory, static::$select ?? null);
                else
                    $factory->selector_master = new \Swango\Model\Operater\Selector($use_slave_DB, $factory,
                        static::$select ?? null);
            }
            return $factory->selector_master;
        }
    }
    /**
     * 获取model对应的更新器
     * 如需要用到，则需要在MODEL/PATH/MODELNAME/Updator.php中定义class Updator
     */
    public static function getUpdator(): \Swango\Model\Operater\Updator {
        $factory = static::getFactory();
        if (! isset($factory->updator)) {
            $name = static::$model_name . '\\Updator';
            if (class_exists($name))
                $factory->updator = new $name(static::$table_name);
            else
                $factory->updator = new \Swango\Model\Operater\Updator(static::$table_name);
        }
        return $factory->updator;
    }
    /**
     *
     * @var $profile \stdClass
     */
    protected $profile, $where;
    public function __toString() {
        return implode('-', array_values($this->where));
    }
    public function toArray(): array {
        $ret = (array)$this->profile;
        foreach ($this->where as $k=>$v)
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
        foreach ($this::INDEX as $k=>$v) {
            $key = $index[$k];
            if (is_numeric($key))
                $key = (int)$key;
            $this->where[$v] = $key;
        }
        $this->profile = new \stdClass();
        $this->initProfile();
        static::getFactory()->saveInstance($this, ...$index);
    }
    protected function initProfile(): void {}
    /**
     * 从数据库加载本model所有成员，直接覆盖现有的所有成员（若存在）。
     * 若对应行不存在，则会直接抛出 PATH\MODELNAME\Exception\MODELNAMENotFoundException
     */
    public function load(bool $for_update = false): self {
        $result = static::loadFromDB($this->where, $for_update, $this->isLoadAsForUpdate());
        if (! $result) {
            if (method_exists($this, 'onNotFound')) {
                $this->onNotFound('Load');
                return $this;
            }
            $exception_name = static::getFactory()->getNotFoundExceptionName();
            throw new $exception_name();
        }
        if ($for_update && $this instanceof AbstractModel)
            $this->_transaction_serial = \Gateway::getTransactionSerial();
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
        if (array_key_exists($key, $this->where))
            return $this->where[$key];
        if (! self::hasProperty($key))
            throw new Exception\ColumnNotExistsException($key);
        if (! property_exists($this->profile, $key))
            $this->load();
        return $this->profile->{$key};
    }
    /**
     * 若key为属于model的成员，则转换后存入profile，否则直接绑定在对象上
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value) {
        if (self::hasProperty($key)) {
            if ($value instanceof IdIndexedModel)
                $value = $value->getId();
            $value = static::$property_map[$key]->intoProfile($value);
            $this->profile->{$key} = $value;
            if (method_exists($this, 'onInject')) {
                $ob = new \stdClass();
                $ob->{$key} = $value;
                $this->onInject($ob);
            }
        } else
            $this->{$key} = $value;
    }
    public function __isset($key) {
        if (self::hasProperty($key)) {
            if (! property_exists($this->profile, $key))
                $this->load();
            return isset($this->profile->{$key});
        } elseif (array_key_exists($key, $this->where))
            return true;
        return false;
    }
    public function __unset($key) {
        if (self::hasProperty($key) && property_exists($this->profile, $key))
            unset($this->profile->{$key});
        else
            trigger_error('Try to unset property not existed ' . static::$model_name . "($key)");
    }
    /**
     * 注入内容，依据设置转换每一项的类型，忽略不属于model的成员。
     * 若设置有onInject回调，将会执行
     */
    public function injectProfile($profile): self {
        foreach ($profile as $k=>$v)
            if (self::hasProperty($k))
                $this->profile->{$k} = static::$property_map[$k]->intoProfile($v);

        if (method_exists($this, 'onInject'))
            $this->onInject((object)$profile);
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