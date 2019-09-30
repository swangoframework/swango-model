<?php
namespace Swango\Model\Operater;
use Swango\Model\Factory;
use Swango\Model\AbstractBaseGateway;

/**
 *
 * @property \Swango\Db\Adapter $DB
 * @author fdream
 */
class Selector {
    /**
     *
     * @var $DB_type int
     * @var $factory \Factory
     * @var $select \Sql\Select
     */
    public $DB_type, $factory, $select;
    public function __construct(bool $use_slave_DB, Factory $factory, ?\Sql\Select $select = null) {
        $this->DB_type = $use_slave_DB ? \Gateway::SLAVE_DB : \Gateway::MASTER_DB;
        $this->factory = $factory;
        $this->select = $select;
    }
    public function __destruct() {
        $this->factory = null;
        $this->select = null;
    }
    protected function getSelect(): \Sql\Select {
        if (isset($this->select))
            return clone $this->select;
        return new \Sql\Select($this->factory->table_name);
    }
    /**
     * 在生成起析构时，会清理掉函数中的所有对象。所以不用担心未遍历完就报错导致的协程泄露
     *
     * @param \Traversable $resultset
     * @param bool $expect_model
     * @return \Generator
     */
    protected function yieldResult(\Traversable $resultset): \Generator {
        foreach ($resultset as $row)
            yield $this->factory->createObject($row);
    }
    public function exists($where): bool {
        $select = $this->getSelect();
        $select->where($where)->limit(1);
        $resultset = $this->DB->query($select);
        if (! is_array($resultset) || count($resultset) === 0)
            return false;
        $this->factory->createObject(current($resultset));
        return true;
    }
    public function getSum($where, string $column): int {
        $select = $this->getSelect();
        $select->columns([
            's' => new \Sql\Expression("SUM(`$column`)")
        ])->where($where);
        return $this->DB->selectWith($select)->current()->s ?? 0;
    }
    public function getCount($where, string $column = '*'): int {
        if ($column == '*')
            $expression = 'COUNT(*)';
        else
            $expression = "COUNT(`$column`)";
        $select = $this->getSelect();
        $select->columns([
            'c' => new \Sql\Expression($expression)
        ])->where($where);
        return $this->DB->selectWith($select)->current()->c ?? 0;
    }
    public function selectOne($where, $order = null, ?int $offset = null): AbstractBaseGateway {
        $select = $this->getSelect();
        $select->where($where);
        if (isset($order))
            $select->order($order);
        if (isset($offset))
            $select->offset($offset);
        $select->limit(1);
        $result = $this->DB->selectWith($select)->current();
        if (! $result) {
            $name = $this->factory->getNotFoundExceptionName();
            throw new $name();
        }
        return $this->factory->createObject($result);
    }
    public function selectMulti($where, $order = null, ?int $limit = null, ?int $offset = null): \Generator {
        $select = $this->getSelect();
        $select->where($where);
        if (isset($order))
            $select->order($order);
        if (isset($offset))
            $select->offset($offset);
        if (isset($limit))
            $select->limit($limit);
        return $this->yieldResult($this->DB->selectWith($select));
    }
    /**
     *
     * @param string|\Sql\Select $sql
     * @param null|number|string ...$parameter
     * @return \AbstractBaseGateway
     */
    public function selectOneWithSql($sql, ...$parameter): AbstractBaseGateway {
        $result = $this->DB->selectWith($sql, ...$parameter)->current();
        if (! $result) {
            $name = $this->factory->getNotFoundExceptionName();
            throw new $name();
        }
        return $this->factory->createObject($result);
    }
    /**
     *
     * @param string|\Sql\Select $sql
     * @param null|number|string ...$parameter
     * @return \AbstractBaseGateway[]
     */
    public function selectMultiWithSql($sql, ...$parameter): \Generator {
        $resultset = $this->DB->selectWith($sql, ...$parameter);
        return $this->yieldResult($resultset);
    }
    public function __get(string $key) {
        if ($key === 'DB')
            return \Gateway::getAdapter($this->DB_type);
        return null;
    }
}