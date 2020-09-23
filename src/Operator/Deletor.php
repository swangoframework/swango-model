<?php
namespace Swango\Model\Operator;
/**
 *
 * @author fdrea
 * @property \Swango\Db\Db\master $DB
 *
 */
class Deletor {
    protected string $table_name;
    public function __construct(string $table_name) {
        $this->table_name = $table_name;
    }
    /**
     *
     * @param array $where
     * @return int 删除行数
     */
    public function delete(array $where): int {
        $delete = new \Sql\Delete($this->table_name);
        $delete->where($where);
        $this->getDb()->query($delete);
        return $this->getDb()->affected_rows;
    }
    protected function getDb(): \Swango\Db\Adapter\master {
        return \Gateway::getAdapter(\Gateway::MASTER_DB);
    }
    /**
     *
     * @param string $sql
     * @param mixed ...$parameter
     * @return int 删除行数
     */
    public function doSql(string $sql, ...$parameter): int {
        $this->getDb()->query($sql, ...$parameter);
        return $this->getDb()->affected_rows;
    }
    public function __get(string $key) {
        if ($key === 'DB') {
            return $this->getDb();
        }
        return null;
    }
}