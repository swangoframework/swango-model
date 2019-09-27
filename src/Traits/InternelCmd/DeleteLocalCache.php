<?php
namespace Swango\Model\Traits\InternelCmd;
abstract class DeleteLocalCache extends \Swango\Cache\InternelCmd implements \Swango\Cache\InternelCmdInterface {
    public static function handle(string &$cmd_data): void {
        try {
            $obj = \Json::decodeAsObject($cmd_data);
        } catch(\JsonDecodeFailException $e) {
            trigger_error('InternelCmd DeleteLocalCache error:' . $cmd_data);
            return;
        }
        if (! is_string($obj->c) || ! is_array($obj->i)) {
            trigger_error('InternelCmd DeleteLocalCache error:' . $cmd_data);
            return;
        }
        ($obj->c)::deleteCacheWithoutBroadcast(...$obj->i);
    }
    public static function broadcast(string $class_name, ...$index): int {
        return self::sendBroadcast(1,
            \Json::encode([
                'c' => $class_name,
                'i' => $index
            ]));
    }
}