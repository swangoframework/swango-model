<?php
namespace Swango\Model;
/**
 *
 * @author fdream
 */
class Factory {
    /**
     *
     * @var Closure $func_ProfileNecessary
     * @var Closure $func_ProfileNotNecessary
     * @var Closure $func_NotFoundExceptionExists
     * @var Closure $func_NotFoundExceptionNotExists
     */
    protected static $func_ProfileNecessary, $func_ProfileNotNecessary, $func_NotFoundExceptionExists, $func_NotFoundExceptionNotExists;
    public static function convertIntoObject(&$profile): void {
        if (is_array($profile))
            $profile = (object)$profile;
        elseif ($profile instanceof \Traversable) {
            $profile_ob = new \stdClass();
            foreach ($profile as $k=>$v)
                $profile_ob->{$k} = $v;
            $profile = $profile_ob;
        }
    }
    public static function clearAllInstances(string ...$except): void {
        $print = defined('WORKING_MODE') && defined('WORKING_MODE_CLI') && WORKING_MODE === WORKING_MODE_CLI;
        if ($print)
            echo sprintf('clear all instances: %dKb', memory_get_usage() / 1024);
        if (empty($except)) {
            foreach (\SysContext::get('factory') as $factory)
                $factory->clearInstances();
        } else {
            $map = [];
            foreach ($except as $e)
                $map[$e] = null;
            foreach (\SysContext::get('factory') as $model_name=>$factory)
                if (! array_key_exists($model_name, $map))
                    $factory->clearInstances();
        }
        if ($print)
            echo sprintf(" ==> %dKb\n", memory_get_usage() / 1024);
    }
    public static function init(\Closure $constructor): void {
        self::$func_ProfileNecessary = function (\stdClass $profile, ...$index) use ($constructor): ?\AbstractBaseGateway {
            $instancename = $this->model_name::getInstanceName($profile, ...$index);
            return isset($instancename) ? $constructor($this->model_name . '\\' . $instancename, ...$index) : null;
        };
        self::$func_ProfileNotNecessary = function (\stdClass $profile, ...$index) use ($constructor): ?\AbstractBaseGateway {
            $instancename = $this->model_name . '\\' . $this->model_name_without_path;
            return $constructor($instancename, ...$index);
        };
        self::$func_NotFoundExceptionExists = function (): string {
            return $this->not_found_exception_name;
        };
        self::$func_NotFoundExceptionNotExists = function (): string {
            Exception\ModelNotFoundException::$model_name = $this->model_name;
            return '\\ModelNotFoundException';
        };
    }
    public $table_name;
    protected $instances, $model_name, $model_name_without_path, $index, $not_found_exception_name, $create_instance_func, $exception_name_func;
    public function __construct(string $model_name, string $table_name) {
        \SysContext::hSet('factory', $model_name, $this);
        $this->instances = [];
        $this->model_name = $model_name;
        $this->table_name = $table_name;
        $pos = strrpos($model_name, '\\');
        $this->model_name_without_path = $pos === false ? $model_name : substr($model_name, $pos + 1);

        $index = $model_name::INDEX;
        $this->index = $index;
        $this->create_instance_func = (method_exists($model_name, 'getInstanceName') ? self::$func_ProfileNecessary : self::$func_ProfileNotNecessary)->bindTo(
            $this);

        $this->not_found_exception_name = $model_name . '\\Exception\\' . $this->model_name_without_path .
             'NotFoundException';
        // class_exists会调用autoloader，有文件IO。如果某个model没有定义NotFoundException就会极大的拖慢系统速度
        $this->exception_name_func = (class_exists($this->not_found_exception_name) ? self::$func_NotFoundExceptionExists : self::$func_NotFoundExceptionNotExists)->bindTo(
            $this);
    }
    public function __destruct() {
        $this->clearInstances();
        $this->create_instance_func = null;
        $this->not_found_exception_name = null;
        $this->exception_name_func = null;
    }
    public function getIndex(): array {
        return $this->index;
    }
    public function getNotFoundExceptionName(): string {
        return ($this->exception_name_func)();
    }
    /**
     * 若未指定index，则会从profile中寻找主键；若要指定index，必须按照MODEL::INDEX中定义的顺序传所有主键
     *
     * @param mixed $profile
     * @param mixed ...$index
     * @throws Exception\CannotFindIndexInProfileException
     * @throws Exception\IncorrectIndexCountException
     * @return AbstractBaseGateway|NULL
     */
    public function createObject($profile, ...$index): ?AbstractBaseGateway {
        self::convertIntoObject($profile);
        if (empty($index)) {
            $index = [];
            foreach ($this->index as $key)
                if (! property_exists($profile, $key))
                    throw new Exception\CannotFindIndexInProfileException($key);
                else
                    $index[] = $profile->$key;
        } elseif (count($index) != count($this->index))
            throw new Exception\IncorrectIndexCountException(count($index), count($this->index));
        if ($this->hasInstance(...$index))
            $instance = $this->getInstance(...$index);
        else
            $instance = ($this->create_instance_func)($profile, ...$index);
        if (! isset($instance))
            return null;
        /**
         *
         * @var $instance AbstractBaseGateway
         */
        $instance->injectProfile($profile);
        return $instance;
    }
    public function clearInstances(): self {
        $this->instances = [];
        return $this;
    }
    public function hasInstance(...$index): bool {
        return array_key_exists(implode('`', $index), $this->instances);
    }
    public function getInstance(...$index): AbstractBaseGateway {
        return $this->instances[implode('`', $index)];
    }
    public function saveInstance(AbstractBaseGateway $model, ...$index): void {
        $this->instances[implode('`', $index)] = $model;
    }
    public function deleteInstance(...$index): void {
        $k = implode('`', $index);
        if (array_key_exists($k, $this->instances))
            unset($this->instances[$k]);
    }
}