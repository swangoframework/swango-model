<?php
namespace Swango\Model\Type;
final class JsonArray extends \Swango\Model\Type {
    public function intoProfile($var): ?array {
        if (! isset($var)) {
            return null;
        }
        if (is_object($var)) {
            return (array)$var;
        }
        if (is_array($var)) {
            return $var;
        }
        if ('null' === $var) {
            return null;
        }
        return \Json::decodeAsArray($var);
    }
    public function intoDB($var): ?string {
        if (! isset($var)) {
            return null;
        }
        return is_string($var) ? $var : \Json::encode($var);
    }
}