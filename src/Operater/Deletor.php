<?php
namespace Swango\Model\Operater;
/**
 *
 * @author fdrea
 * @property \Swango\Db\Db\master $DB
 *
 */
class Deletor {
    public $table_name;
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
        $this->DB->query($delete);
        return $this->DB->affected_rows;
    }
    /**
     *
     * @param string $sql
     * @param mixed ...$parameter
     * @return int 删除行数
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