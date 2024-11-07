<?php
namespace Swango\Model;
/**
 *
 * @author fdream
 */
class Factory implements \Countable {
    protected static \Closure $constructor;
    public static function convertIntoObject(&$profile): void {
        if (is_array($profile)) {
            $profile = (object)$profile;
        } elseif ($profile instanceof \Traversable) {
            $profile_ob = new \stdClass();
            foreach ($profile as $k => $v)
                $profile_ob->{$k} = $v;
            $profile = $profile_ob;
        }
    }
    public static function clearAllInstances(string ...$except): void {
        $print = \Swango\Environment::getWorkingMode()->isInCliScript();
        $print && printf('clear all instances: %dKb', memory_get_usage() / 1024);
        $factories = \SysContext::get('factory');
        if (isset($factories)) {
            if (empty($except)) {
                foreach (\SysContext::get('factory') as $factory)
                    $factory->clearInstances();
            } else {
                $map = array_fill_keys($except, null);
                foreach (\SysContext::get('factory') as $model_name => $factory)
                    ! array_key_exists($model_name, $map) && $factory->clearInstances();
            }
        }
        $print && printf(" ==> %dKb\n", memory_get_usage() / 1024);
    }
    public static function init(\Closure $constructor): void {
        self::$constructor = $constructor;
    }
    protected array $instances = [];
    protected array $index;
    protected string $model_name_without_path, $not_found_exception_name;
    protected int $instance_counter = 0;
    protected bool $has_method_get_instance_name;
    public function __construct(protected readonly string $model_name,
                                protected readonly string $table_name,
                                protected int             $instance_size = 1024) {
        \SysContext::hSet('factory', $model_name, $this);
        $pos = strrpos($model_name, '\\');
        $this->model_name_without_path = $pos === false ? $model_name : substr($model_name, $pos + 1);
        $index = $model_name::INDEX;
        $this->index = $index;
        $this->has_method_get_instance_name = method_exists($model_name, 'getInstanceName');
        $this->not_found_exception_name = $model_name . '\\Exception\\' . $this->model_name_without_path .
            'NotFoundException';
    }
    public function __destruct() {
        $this->clearInstances();
    }
    public function getIndex(): array {
        return $this->index;
    }
    public function getTableName(): string {
        return $this->table_name;
    }
    public function getNotFoundExceptionName(): string {
        if (class_exists($this->not_found_exception_name)) {
            return $this->not_found_exception_name;
        }
        Exception\ModelNotFoundException::$model_name = $this->model_name;
        return Exception\ModelNotFoundException::class;
    }
    /**
     * 若未指定index，则会从profile中寻找主键；若要指定index，必须按照MODEL::INDEX中定义的顺序传所有主键
     *
     * @param mixed $profile
     * @param mixed ...$index
     * @return AbstractBaseGateway|NULL
     * @throws Exception\IncorrectIndexCountException
     * @throws Exception\CannotFindIndexInProfileException
     */
    public function createObject($profile, ...$index): ?AbstractBaseGateway {
        self::convertIntoObject($profile);
        if (empty($index)) {
            foreach ($this->index as $key)
                if (! property_exists($profile, $key)) {
                    throw new Exception\CannotFindIndexInProfileException($key);
                } else {
                    $index[] = $profile->$key;
                }
        } elseif (count($index) != count($this->index)) {
            throw new Exception\IncorrectIndexCountException(count($index), count($this->index));
        }
        if ($this->hasInstance(...$index)) {
            $instance = $this->getInstance(...$index);
        } elseif ($this->has_method_get_instance_name) {
            $instance_name = $this->model_name::getInstanceName($profile, ...$index);
            $instance = isset($instance_name) ? (self::$constructor)($this->model_name . '\\' . $instance_name, ...$index) : null;
        } else {
            $instance_name = $this->model_name . '\\' . $this->model_name_without_path;
            $instance = (self::$constructor)($instance_name, ...$index);
        }
        $instance?->injectProfile($profile);
        return $instance;
    }
    public function clearInstances(): self {
        $this->instances = [];
        $this->instance_counter = 0;
        return $this;
    }
    protected function makeIndexKey(...$index): string {
        $arr = [];
        foreach ($index as $i)
            $arr[] = match (true) {
                $i instanceof \BackedEnum => $i->value,
                $i instanceof IdIndexedModel => $i->getId(),
                default => $i
            };
        return implode('`', $arr);
    }
    public function hasInstance(...$index): bool {
        return array_key_exists($this->makeIndexKey(...$index), $this->instances);
    }
    public function getInstance(...$index): AbstractBaseGateway {
        $key = $this->makeIndexKey(...$index);
        //刷新array位置
        $instance = $this->instances[$key];
        if ($this->instance_counter > $this->instance_size / 2) {
            unset($this->instances[$key]);
            $this->instances[$key] = $instance;
        }
        return $instance;
    }
    public function saveInstance(AbstractBaseGateway $model, ...$index): void {
        $key = $this->makeIndexKey(...$index);
        if (! $this->hasInstance(...$index)) {
            if ($this->instance_counter < $this->instance_size) {
                $this->instance_counter++;
            } else {
                unset($this->instances[array_key_first($this->instances)]);
            }
        } else {
            // 刷新array位置
            if ($this->instance_counter > $this->instance_size / 2) {
                unset($this->instances[$key]);
            }
        }
        $this->instances[$key] = $model;
    }
    public function deleteInstance(...$index): void {
        $key = $this->makeIndexKey(...$index);
        if (array_key_exists($key, $this->instances)) {
            $this->instance_counter--;
            unset($this->instances[$key]);
        }
    }
    public function count(): int {
        return $this->instance_counter;
    }
}
