<?php
namespace Swango\Model\Traits;
/**
 * @property func $remove
 */
trait MustNotRemoveTrait {
    public function remove(): bool {
        throw new \Swango\Model\Exception\MustNotRemoveThisModelException(self::$model_name);
    }
    protected function onRemoveMiss(): void {}
}
