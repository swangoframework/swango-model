<?php
namespace Swango\Model\Type;
final class Explode extends \Swango\Model\Type {
    public function intoProfile($var): array {
        if (! isset($var)) {
            return [];
        }
        if (is_array($var)) {
            return $var;
        }
        // 去除第一位的-
        if ($var[0] == '-') {
            $var = substr($var, 1);
        }
        $ret = explode('-', $var);
        if (is_array($ret)) {
            if (count($ret) == 0 && current($ret) == '') {
                return [];
            }
            foreach ($ret as &$t)
                if (is_numeric($t)) {
                    $t = (int)$t;
                }
            return $ret;
        }
        return [];
    }
    public function intoDB($var): ?string {
        if (! isset($var)) {
            return null;
        }
        return is_string($var) ? $var : '-' . implode('-', $var);
    }
}