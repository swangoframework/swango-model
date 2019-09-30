<?php
namespace Swango\Model\Operater;
use Swango\Model\Factory;
use Swango\Model\Exception;
use Swango\Model\AbstractModel;
use Swango\Model\IdIndexedModel;

/**
 *
 * @author fdrea
 *
 */
class Addor {
    /**
     *
     * @var $values array
     */
    public $_insert_values, $factory, $property_map, $_insert_index;
    public function __construct(Factory $factory, array &$property_map) {
        $this->_insert_values = [];
        $this->factory = $factory;
        $this->property_map = &$property_map;
        $this->_insert_index = $factory->getIndex();
    }
    public function __destruct() {
        $this->factory = null;
    }
    protected function insert(array $values = null): void {
        $insert = new \Sql\Insert($this->factory->table_name);
        $insert->values($values ?? $this->_insert_values);
        $this->getDb()->query($insert);
    }
    protected function getDb(): \Swango\Db\Db\master {
        return \Gateway::getAdapter(\Gateway::MASTER_DB);
    }
    public function reset(): self {
        $this->_insert_values = [];
        return $this;
    }
    public function __set($key, $value) {
        if (! array_key_exists($key, $this->property_map) && ! in_array($key, $this->_insert_index))
            throw new Exception\ColumnNotExistsException($key);
        $this->_insert_values[$key] = $value instanceof IdIndexedModel ? $value->getId() : $value;
    }
    public function __isset($key) {
        return array_key_exists($key, $this->_insert_values);
    }
    public function __get(string $key) {
        if (array_key_exists($key, $this->_insert_values))
            return $this->_insert_values[$key];
        return null;
    }
    public function __unset(string $key) {
        if (array_key_exists($key, $this->_insert_values))
            unset($this->_insert_values[$key]);
    }
    public function getObjectWithoutInsert(...$index): AbstractModel {
        if (empty($index))
            $index = [
                - rand()
            ];
        $ob = $this->factory->createObject($this->_insert_values, ...$index);
        $ob->_save_flag = true;
        return $ob;
    }
    protected function beforeInsert(): void {}
    protected function afterInsert(AbstractModel $ob): void {}
    public function doInsert(): AbstractModel {
        $this->beforeInsert();
        $values = [];
        foreach ($this->_insert_values as $k=>&$v)
            if (array_key_exists($k, $this->property_map))
                $values[$k] = $this->property_map[$k]->intoDB($v);
            else
                $values[$k] = $v;
        unset($v);
        try {
            $this->insert($values);
        } catch(\Swango\Db\Exception\QueryErrorException $e) {
            $errno = $e->errno;
            if (in_array($errno, [
                1022,
                1062,
                1169
            ]) && method_exists($this, 'onDuplicateEntry'))
                return $this->onDuplicateEntry();
            throw $e;
        }
        if (count($this->_insert_index) == 1) {
            $id_name = current($this->_insert_index);
            if (array_key_exists($id_name, $this->_insert_values)) {
                $ob = $this->factory->createObject($this->_insert_values, $this->_insert_values[$id_name]);
                unset($this->_insert_values[$id_name]);
            } else
                $ob = $this->factory->createObject($this->_insert_values, $this->getDb()->insert_id);
        } else {
            $index = [];
            foreach ($this->_insert_index as $key)
                $index[] = $this->_insert_values[$key];
            $ob = $this->factory->createObject($this->_insert_values, ...$index);
        }
        if ($this->getDb()->inTransaction())
            $ob->_transaction_serial = $this->getDb()->getTransactionSerial();
        $this->afterInsert($ob);
        return $ob;
    }
    /**
     *
     * @param string $sql
     * @param mixed ...$parameter
     * @return 插入的id（如果有的话）
     */
    public function doSql(string $sql, ...$parameter) {
        $this->getDb()->query($sql, ...$parameter);
        return $this->getDb()->insert_id;
    }
}