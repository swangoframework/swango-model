<?php
namespace Swango\Model;
/**
 *
 * @author fdream
 */
class Factory {
    protected const buildForProfileNecessary = 'buildForProfileNecessary';
    protected const buildForProfileNotNecessary = 'buildForProfileNotNecessary';
    protected const getExceptionNameWhenExists = 'getExceptionNameWhenExists';
    protected const getExceptionNameWhenNotExists = 'getExceptionNameWhenNotExists';
    protected static $constructor;
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
        $print = \Swango\Environment::getWorkingMode()->isInCliScript();
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
        self::$constructor = $constructor;
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
        $this->create_instance_func = method_exists($model_name, 'getInstanceName') ? self::buildForProfileNecessary : self::buildForProfileNotNecessary;

        $this->not_found_exception_name = $model_name . '\\Exception\\' . $this->model_name_without_path .
             'NotFoundException';
        // class_exists会调用autoloader，有文件IO。如果某个model没有定义NotFoundException就会极大的拖慢系统速度
        $this->exception_name_func = class_exists($this->not_found_exception_name) ? self::getExceptionNameWhenExists : self::getExceptionNameWhenNotExists;
    }
    protected function buildForProfileNecessary(\stdClass $profile, ...$index): ?AbstractBaseGateway {
        $instancename = $this->model_name::getInstanceName($profile, ...$index);
        return isset($instancename) ? (self::$constructor)($this->model_name . '\\' . $instancename, ...$index) : null;
    }
    protected function buildForProfileNotNecessary(\stdClass $profile, ...$index): ?AbstractBaseGateway {
        $instancename = $this->model_name . '\\' . $this->model_name_without_path;
        return (self::$constructor)($instancename, ...$index);
    }
    protected function getExceptionNameWhenExists(): string {
        return $this->not_found_exception_name;
    }
    protected function getExceptionNameWhenNotExists(): string {
        Exception\ModelNotFoundException::$model_name = $this->model_name;
        return '\\Swango\\Model\\Exception\\ModelNotFoundException';
    }
    public function __destruct() {
        $this->clearInstances();
    }
    public function getIndex(): array {
        return $this->index;
    }
    public function getNotFoundExceptionName(): string {
        return $this->{$this->exception_name_func}();
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
            $instance = $this->{($this->create_instance_func)}($profile, ...$index);
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