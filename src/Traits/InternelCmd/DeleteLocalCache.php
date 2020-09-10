<?php
namespace Swango\Model\Traits\InternelCmd;
abstract class DeleteLocalCache extends \Swango\Cache\InternelCmd implements \Swango\Cache\InternelCmdInterface {
    public static function handle(string &$cmd_data): void {
        try {
            $obj = \Json::decodeAsArray($cmd_data);
        } catch(\JsonDecodeFailException $e) {
            trigger_error('InternelCmd DeleteLocalCache error:' . $cmd_data);
            return;
        }
        if (! is_string($obj['c']) || ! is_array($obj['w'])) {
            trigger_error('InternelCmd DeleteLocalCache error:' . $cmd_data);
            return;
        }
        ($obj['c'])::deleteCache($obj['w'], false);
    }
    public static function broadcast(string $class_name, array $where): int {
        return self::sendBroadcast('Swango\\Model\\Traits\\InternelCmd\\DeleteLocalCache',
            \Json::encode([
                'c' => $class_name,
                'w' => $where
            ]));
    }
}