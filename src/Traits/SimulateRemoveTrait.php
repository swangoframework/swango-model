<?php
namespace Swango\Model\Traits;
use Swango\Model\AbstractModel;
use Swango\Model\AbstractBaseGateway;
use Swango\Model\Operator\Deletor;

/**
 * 模拟删除，在删除时不真删除，而是将标记置为“已删除”
 * 以 _simulate_remove_flag 这一列为标记位，记录该行是否被删除，为 1 时将会触发“未找到”逻辑
 * 数据库中需要建出一列 _simulate_remove_flag ，但Model定义时不要出现这一项
 *
 * @author fdream
 */
trait SimulateRemoveTrait {
    public static function selectRegardlessOfFlag(bool $for_update, ...$index): AbstractModel {
        $ob = parent::select($for_update, ...$index);
        $result = static::loadFromDB($ob->where, $for_update);
        if (! $result) {
            if (method_exists($ob, 'onNotFound')) {
                $ob->onNotFound('load');
                return $ob;
            }
            $exception_name = static::getFactory()->getNotFoundExceptionName();
            throw new $exception_name();
        }
        $ob->injectProfile($result);
        if (property_exists($result, '_simulate_remove_flag'))
            $ob->_simulate_remove_flag = (int)$result->_simulate_remove_flag;
        return $ob;
    }
    public static function select(bool $for_update, ...$index): AbstractModel {
        $ret = parent::select($for_update, ...$index);
        if (property_exists($ret->profile, '_simulate_remove_flag') && (int)$ret->profile->_simulate_remove_flag === 1) {
            $exception_name = static::getFactory()->getNotFoundExceptionName();
            throw new $exception_name();
        }
        return $ret;
    }
    public function load(bool $for_update = false): AbstractBaseGateway {
        $result = static::loadFromDB($this->where, $for_update);
        if (! $result) {
            if (method_exists($this, 'onNotFound')) {
                $this->onNotFound('load');
                return $this;
            }
            $exception_name = static::getFactory()->getNotFoundExceptionName();
            throw new $exception_name();
        }
        if (property_exists($result, '_simulate_remove_flag') && (int)$result->_simulate_remove_flag === 1) {
            $exception_name = static::getFactory()->getNotFoundExceptionName();
            throw new $exception_name();
        }
        $this->injectProfile($result);
        return $this;
    }
    public function remove(): bool {
        $update = new \Sql\Update(static::$table_name);
        $update->set([
            '_simulate_remove_flag' => 1
        ])->where($this->where);
        $adapter = \Gateway::getAdapter(\Gateway::MASTER_DB);
        $adapter->query($update);
        $this::getFactory()->deleteInstance(...static::INDEX);
        if ($adapter->affected_rows > 0 && method_exists($this, 'onRemove'))
            $this->onRemove();
        return true;
    }
    public static function getDeletor(): Deletor {
        $factory = static::getFactory();
        if (! isset($factory->deletor)) {
            $name = static::$model_name . '\\Deletor';
            if (class_exists($name))
                $factory->deletor = new $name(static::$table_name);
            else
                $factory->deletor = new class(static::$table_name) extends Deletor {
                    public function delete(array $where): int {
                        $update = new \Sql\Update($this->table_name);
                        $update->set(
                            [
                                '_simulate_remove_flag' => 1
                            ])->where($where);
                        return $this->DB->query($update);
                    }
                };
        }
        return $factory->deletor;
    }
}