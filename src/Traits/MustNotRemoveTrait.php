<?php
namespace Swango\Model\Traits;
/**
 *
 * @author fdrea
 * @property func $remove
 */
trait MustNotRemoveTrait {
    public function remove(): bool {
        throw new \MustNotRemoveThisModelException(self::$model_name);
    }
    protected function onRemoveMiss(): void {}
}
