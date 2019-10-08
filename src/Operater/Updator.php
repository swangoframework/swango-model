<?php
namespace Swango\Model\Operater;
/**
 *
 * @author fdrea
 * @property \Swango\Db\Db\master $DB
 *
 */
class Updator {
    public $table_name;
    public function __construct(string $table_name) {
        $this->table_name = $table_name;
    }
    /**
     *
     * @param array $set
     * @param array $where
     * @return int 更新的行数
     */
    public function update(array $set, array $where): int {
        $update = new \Sql\Update($this->table_name);
        $update->set($set)->where($where);
        $this->getDb()->query($update);
        return $this->getDb()->affected_rows;
    }
    protected function getDb(): \Swango\Db\Adapter\master {
        return \Gateway::getAdapter(\Gateway::MASTER_DB);
    }
    /**
     *
     * @param string $sql
     * @param mixed ...$parameter
     * @return int 更新的行数
     */
    public function doSql(string $sql, ...$parameter): int {
        $this->getDb()->query($sql, ...$parameter);
        return $this->getDb()->affected_rows;
    }
    public function __get(string $key) {
        if ($key === 'DB')
            return $this->getDb();
        return null;
    }
}