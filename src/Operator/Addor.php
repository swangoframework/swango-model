<?php
namespace Swango\Model\Operator;
use Swango\Model\Factory;
use Swango\Model\Exception;
use Swango\Model\AbstractModel;
use Swango\Model\IdIndexedModel;
class Addor {
    protected array $_insert_values = [], $_insert_index;
    // Due to an unknown bug, $property_map need to be initialized before __construct()
    protected ?array $property_map = null;
    protected Factory $factory;
    public function __construct(Factory $factory, array &$property_map) {
        $this->_insert_values = [];
        $this->factory = $factory;
        $this->property_map = &$property_map;
        $this->_insert_index = $factory->getIndex();
    }
    public function __destruct() {
        unset($this->factory);
    }
    protected function insert(array $values = null): void {
        $insert = new \Sql\Insert($this->factory->getTableName());
        $insert->values($values ?? $this->_insert_values);
        $this->getDb()->query($insert);
    }
    protected function getDb(): \Swango\Db\Adapter\master {
        return \Gateway::getAdapter(\Gateway::MASTER_DB);
    }
    public function reset(): self {
        $this->_insert_values = [];
        return $this;
    }
    public function __set($key, $value) {
        if (! array_key_exists($key, $this->property_map) && ! in_array($key, $this->_insert_index)) {
            throw new Exception\ColumnNotExistsException($key);
        }
        $this->_insert_values[$key] = $value instanceof IdIndexedModel ? $value->getId() : $value;
    }
    public function __isset($key) {
        return array_key_exists($key, $this->_insert_values);
    }
    public function __get(string $key) {
        return array_key_exists($key, $this->_insert_values) ? $this->_insert_values[$key] : null;
    }
    public function __unset(string $key) {
        if (array_key_exists($key, $this->_insert_values)) {
            unset($this->_insert_values[$key]);
        }
    }
    public function getObjectWithoutInsert(...$index): AbstractModel {
        empty($index) && $index = [-mt_rand()];
        $ob = $this->factory->createObject($this->_insert_values, ...$index);
        $ob->_save_flag = true;
        return $ob;
    }
    protected function beforeInsert(): void {
    }
    protected function afterInsert(AbstractModel $ob): void {
    }
    public function doInsert(): AbstractModel {
        $this->beforeInsert();
        $values = [];
        foreach ($this->_insert_values as $k => &$v)
            if (array_key_exists($k, $this->property_map)) {
                $values[$k] = $this->property_map[$k]->intoDB($v);
            } else {
                $values[$k] = $v;
            }
        unset($v);
        try {
            $this->insert($values);
        } catch (\Swango\Db\Exception\QueryErrorException $e) {
            $errno = $e->errno;
            if (in_array($errno, [
                    1022,
                    1062,
                    1169
                ]) && method_exists($this, 'onDuplicateEntry')) {
                return $this->onDuplicateEntry();
            }
            throw $e;
        }
        if (count($this->_insert_index) === 1) {
            $id_name = current($this->_insert_index);
            if (array_key_exists($id_name, $this->_insert_values)) {
                $ob = $this->factory->createObject($this->_insert_values, $this->_insert_values[$id_name]);
                unset($this->_insert_values[$id_name]);
            } else {
                $ob = $this->factory->createObject($this->_insert_values, $this->getDb()->insert_id);
            }
        } else {
            $index = [];
            foreach ($this->_insert_index as $key)
                $index[] = $this->_insert_values[$key];
            $ob = $this->factory->createObject($this->_insert_values, ...$index);
        }
        if ($this->getDb()->inTransaction()) {
            $ob->_setTransactionSerial($this->getDb()->getTransactionSerial());
        }
        $this->afterInsert($ob);
        return $ob;
    }
    /**
     *
     * @param string $sql
     * @param mixed ...$parameter
     * @return int new inserted id（if exists）
     */
    public function doSql(string $sql, ...$parameter) {
        $this->getDb()->query($sql, ...$parameter);
        return $this->getDb()->insert_id;
    }
}