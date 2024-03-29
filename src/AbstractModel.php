<?php
namespace Swango\Model;
abstract class AbstractModel extends AbstractBaseGateway {
    protected ?int $_transaction_serial = null;
    public function _setTransactionSerial(?int $transaction_serial): self {
        $this->_transaction_serial = $transaction_serial;
        return $this;
    }
    public static function select(bool $for_update, ...$index): self {
        $factory = static::getFactory();
        if ($factory->hasInstance(...$index)) {
            $instance = $factory->getInstance(...$index);
            $load = $for_update && ! $instance->isLoadAsForUpdate();
        } elseif ($for_update) {
            $load = true;
        } else {
            $instance = $factory->createObject(new \stdClass(), ...$index);
            $load = ! isset($instance);
        }
        if ($load) {
            $where = [];
            foreach (static::INDEX as $k => $v)
                $where[$v] = $index[$k];
            $profile = static::loadFromDB($where, $for_update, static::USE_MASTER_DB_FOR_INDEX_QUERY);
            if (! $profile) {
                $exception_name = $factory->getNotFoundExceptionName();
                throw new $exception_name(...$index);
            }
            $instance = $factory->createObject($profile, ...$index);
        }
        $for_update && $instance->_transaction_serial = \Gateway::getTransactionSerial();
        return $instance;
    }
    /**
     * 获取model对应的添加器
     * 如需要用到，则需要在MODEL/PATH/MODELNAME/Addor.php中定义class Addor
     */
    public static function getAddor(): \Swango\Model\Operator\Addor {
        $addor = \SysContext::hGet('addor', static::$model_name);
        if (! isset($addor)) {
            $factory = static::getFactory();
            $class_name = static::$model_name . '\\Addor';
            if (class_exists($class_name)) {
                $addor = new $class_name($factory, static::$property_map);
            } else {
                $addor = new Operator\Addor($factory, static::$property_map);
            }
            \SysContext::hSet('addor', static::$model_name, $addor);
        }
        return $addor;
    }
    /**
     * 获取model对应的删除器
     * 如需要用到，则需要在MODEL/PATH/MODELNAME/Deletor.php中定义class Deletor
     */
    public static function getDeletor(): \Swango\Model\Operator\Deletor {
        $deletor = \SysContext::hGet('deletor', static::$model_name);
        if (! isset($deletor)) {
            $class_name = static::$model_name . '\\Deletor';
            if (class_exists($class_name)) {
                $deletor = new $class_name(static::$table_name);
            } else {
                $deletor = new Operator\Deletor(static::$table_name);
            }
            \SysContext::hSet('deletor', static::$model_name, $deletor);
        }
        return $deletor;
    }
    protected static function loadFromDB(array $where, bool $for_update = false, bool $force_master_DB = false): ?object {
        $adapter = \Gateway::getAdapter($for_update || $force_master_DB ? \Gateway::MASTER_DB : \Gateway::SLAVE_DB);
        if ($for_update && ! \Gateway::inTransaction()) {
            throw new \Exception('Attempt to load for update out of transaction ' . static::class);
        }
        if (count($where) > 1 || current($where) instanceof \Sql\Expression) {
            $select = new \Sql\Select(static::$table_name);
            $select->where($where);
            $for_update && $select->tail(\Sql\Select::TAIL_FOR_UPDATE);
            $result = $adapter->selectWith($select)->current();
        } else {
            $sql = 'SELECT * FROM `' . static::$table_name . '` WHERE `' . key($where) . '`=?';
            $for_update && $sql .= ' FOR UPDATE';
            $value = current($where);
            $result = $adapter->selectWith($sql,
                match (true) {
                    $value instanceof \BackedEnum => $value->value,
                    $value instanceof IdIndexedModel => $value->getId(),
                    default => $value
                }
            )->current();
        }
        return $result;
    }
    public function isLoadAsForUpdate(): bool {
        return isset($this->_transaction_serial) && $this->_transaction_serial === \Gateway::getTransactionSerial();
    }
    public function getTransactionSerial(): ?int {
        return $this->_transaction_serial;
    }
    /**
     * 更新数据库对应行，会依据设定的类型自动转换，并自动注入新内容。
     * 若遇到Duplicate entry报错且设置了onUpdateDuplicate回调则会直接执行
     * 更新实际成功后，若设置了onUpdate回调，会直接执行
     *
     * @param array $new_profile
     *            键值对，值可以为\Sql\Expression 或 \IdIndexedModel
     */
    public function update(array $new_profile): int {
        $DB = \Gateway::getAdapter(\Gateway::MASTER_DB);
        $new = $unset = [];
        $new_to_inject = new \stdClass();
        $property_map = static::$property_map;
        foreach ($new_profile as $k => &$v) {
            if (! self::hasProperty($k)) {
                throw new Exception\ColumnNotExistsException($k);
            }
            if ($v instanceof \Sql\Expression) {
                $new[$k] = $v;
                $unset[] = $k;
            } else {
                $new_to_inject->{$k} = $new[$k] = $property_map[$k]->intoDB($v instanceof IdIndexedModel ? $v->getId() : $v);
            }
        }
        unset($v);
        $update = new \Sql\Update(static::$table_name);
        $update->set($new)->where($this->where);
        try {
            $DB->query($update);
        } catch (\Swango\Db\Exception\QueryErrorException $e) {
            $errno = $e->errno;
            if (in_array($errno, [
                    1022,
                    1062,
                    1169
                ]) && method_exists($this, 'onUpdateDuplicate')) {
                return $this->onUpdateDuplicate($new_profile);
            }
            throw $e;
        }
        foreach ($unset as $k)
            if (property_exists($this->profile, $k)) {
                unset($this->profile->{$k});
            }
        $this->injectProfile($new_to_inject);
        if ($DB->affected_rows > 0) {
            if (method_exists($this, 'onUpdate')) {
                $this->onUpdate();
            }
        } elseif (method_exists($this, 'onNotFound')) {
            $this->onNotFound('Update');
        }
        return $DB->affected_rows;
    }
    /**
     * 删除数据库对应行
     * 若对应行不存在，则会直接抛出 PATH\MODELNAME\Exception\MODELNAMENotFoundException
     * 删除实际成功后，若设置了onRemove回调，会直接执行
     */
    public function remove(): bool {
        $DB = \Gateway::getAdapter(\Gateway::MASTER_DB);
        $delete = new \Sql\Delete(static::$table_name);
        $delete->where($this->where);
        $DB->query($delete);
        if ($DB->affected_rows === 0) {
            if (method_exists($this, 'onNotFound')) {
                $this->onNotFound('Remove');
                return false;
            } else {
                $exception_name = static::getFactory()->getNotFoundExceptionName();
                throw new $exception_name(...array_values($this->where));
            }
        }
        $this->removeFromInstances();
        if (method_exists($this, 'onRemove')) {
            $this->onRemove();
        }
        return true;
    }
}