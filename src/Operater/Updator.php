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
        $this->DB->query($update);
        return $this->DB->affected_rows;
    }
    /**
     *
     * @param string $sql
     * @param mixed ...$parameter
     * @return int 更新的行数
     */
    public function doSql(string $sql, ...$parameter): int {
        $this->DB->query($sql, ...$parameter);
        return $this->DB->affected_rows;
    }
    public function __get(string $key) {
        if ($key === 'DB')
            return \Gateway::getAdapter(\Gateway::MASTER_DB);
        return null;
    }
}