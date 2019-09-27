<?php
namespace Swango\Model;
/**
 *
 * @deprecated 尚未完成
 *
 * @author fdream
 * @property $table_join_map
 * @property $main_table_columns
 * @property $property_location
 * @property $select
 */
abstract class AbstractViewModel extends AbstractBaseGateway {
    /**
     *
     * @deprecated 尚未完成
     *             举个例子，假设 INDEX为['index1', 'index2']
     *             public static function onLoad(): void {
     *             self::$property_map = [
     *             'p1' => \type::INTEGER(), // 来自于 表table1的 ppp 列
     *             'p2' => \type::VARCHAR(), // 来自于 表table1（b表）的 abc 列
     *             'p3' => \type::INTEGER() // 来自于 表table2的 abc 列
     *             ];
     *             self::$main_table_columns = [
     *             'p1' => 'ppp'
     *             ];
     *             self::$table_join_map = [
     *             'table1b' => [
     *             'table1',
     *             'table1.table1=table1b.id',
     *             [
     *             'index1' => 'id',
     *             'p2' => 'abc'
     *             ],
     *             \Sql\Join::JOIN_LEFT
     *             ],
     *             [
     *             'table2',
     *             'table1b.table2=table2.id',
     *             [
     *             'index2' => 'id',
     *             'p3' => 'abc'
     *             ],
     *             \Sql\Join::JOIN_LEFT
     *             ]
     *             ];
     *             self::init();
     *             }
     */
    public static function init(): void {
        $DB = static::getFactory()->slave_DB;
        $select = $DB->getSql()->select();
        $select->columns(static::$main_table_columns);
        static::$property_location = [];
        foreach (static::$main_table_columns as $name_as=>$name) {
            if (! is_string($name_as))
                $name_as = $name;
            static::$property_location[$name_as] = $DB->table . ".$name";
        }
        foreach (static::$table_join_map as $table_as_name=>[
            $table_name,
            $on,
            $columns,
            $mode
        ]) {
            $select->join(
                is_string($table_as_name) ? [
                    $table_as_name => $table_name
                ] : $table_name, $on, $columns, $mode);
            foreach ($columns as $name_as=>$name) {
                if (! is_string($name_as))
                    $name_as = $name;
                static::$property_location[$name_as] = "$table_name.$name";
            }
        }
        static::$select = $select;
    }
    protected static function loadFromDB($where, bool $for_update = false, bool $force_normal_DB = false): ?\stdClass {
        if ($for_update || $force_normal_DB)
            $DB = \Gateway::getAdapter(\Gateway::MASTER_DB);
        else
            $DB = \Gateway::getAdapter(\Gateway::SLAVE_DB);
        $result = $DB->selectWith((clone static::$select)->where($where))->current();
        if (! $result)
            return null;
        $ret = new \stdClass();
        foreach ($result as $k=>$v)
            $ret->{$k} = $v;

        return $ret;
    }
    protected $where_in_db;
    /**
     *
     * @deprecated 尚未完成
     *             更新数据库对应行，会依据设定的类型自动转换，并自动注入新内容。
     *             若遇到Duplicate entry报错且设置了onUpdateDuplicate回调则会直接执行
     *             更新实际成功后，若设置了onUpdate回调，会直接执行
     *
     * @param array $new_profile
     *            键值对，值可以为\Sql\Expression
     */
    public function update(array $new_profile): int {
        $DB = static::getFactory()->master_adapter;
        $new = [];
        $unset = [];
        $new_to_inject = new \stdClass();
        foreach ($new_profile as $k=>$v) {
            if ($v instanceof \Sql\Expression) {
                $new[static::$property_location[$k]] = $v;
                $unset[] = $k;
                continue;
            }
            if (! self::hasProperty($k))
                throw new Exception\ColumnNotExistsException();
            $v = static::$property_map[$k]->intoDB($v);
            $new[static::$property_location[$k]] = $v;
            $new_to_inject->{$k} = $v;
        }
        $update = $DB->getSql()->update();
        foreach (static::$table_join_map as $table_as_name=>[
            $table_name,
            $on,
            $columns,
            $mode
        ]) {
            $update->join(
                is_string($table_as_name) ? [
                    $table_as_name => $table_name
                ] : $table_name, $on, $columns, $mode);
        }
        $where = [];
        foreach ($this->where as $k=>$v)
            $where[static::$property_map[$k]] = $v;
        $update->set($new)->where($where);
        try {
            $result = $DB->updateWith($update);
        } catch(\Sql\Adapter\Exception\InvalidQueryException $e) {
            if (stripos($e->getMessage(), 'Duplicate entry') !== false && method_exists($this, 'onUpdateDuplicate'))
                return $this->onUpdateDuplicate($new_profile);
            throw $e;
        }
        foreach ($unset as $k)
            if (property_exists($this->profile, $k))
                unset($this->profile->{$k});
        $this->injectProfile($new_to_inject);
        if ($result) {
            if (method_exists($this, 'onUpdate'))
                $this->onUpdate();
        } elseif (method_exists($this, 'onNotFound'))
            $this->onNotFound('Update');
        return $result;
    }
}