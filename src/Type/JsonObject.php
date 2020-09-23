<?php
namespace Swango\Model\Type;
final class JsonObject extends \Swango\Model\Type {
    public function intoProfile($var) {
        if (! isset($var)) {
            return null;
        }
        if (is_object($var)) {
            return $var;
        }
        if (is_array($var)) {
            if (empty($var)) {
                return [];
            }
            $i = 0;
            foreach ($var as $k => &$v) {
                if ($k !== $i) {
                    return (object)$var;
                }
                ++$i;
            }
            unset($v);
            return $var;
        }
        try {
            return \Json::decodeAsObject($var);
        } catch (\JsonDecodeFailException $e) {
            return $var;
        }
    }
    public function intoDB($var): ?string {
        if (! isset($var)) {
            return null;
        }
        if (true === $var) {
            return 'true';
        }
        if (is_string($var) && is_numeric($var)) {
            return '"' . $var . '"';
        }
        return $this->isJsonString($var) ? $var : \Json::encode($var);
    }
    private function isJsonString(&$string): bool {
        if (is_array($string) || is_object($string)) {
            return false;
        }
        json_decode($string, false);
        return json_last_error() === JSON_ERROR_NONE;
    }
}