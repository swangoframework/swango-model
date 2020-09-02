<?php
namespace Swango\Model;
/**
 * 以单个id为index的model 每个model需对应数据库中的一行
 */
abstract class IdIndexedModel extends AbstractModel {
    const INDEX = [
        'id'
    ];
    public static function selectById($id, bool $for_update = false): self {
        $ret = self::select($for_update, $id);
        return $ret;
    }
    public function getId() {
        return current($this->where);
    }
    public function __toString() {
        return (string)$this->getId();
    }
}