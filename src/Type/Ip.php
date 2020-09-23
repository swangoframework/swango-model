<?php
namespace Swango\Model\Type;
final class Ip extends \Swango\Model\Type {
    public function intoProfile($var): ?string {
        if (! isset($var)) {
            return null;
        }
        if (is_numeric($var)) {
            return long2ip($var);
        }
        return $var;
    }
    public function intoDB($var) {
        if (! is_numeric($var)) {
            return ip2long($var);
        }
        return $var;
    }
}