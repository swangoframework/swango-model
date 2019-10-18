<?php
namespace Swango\Model\Traits;
/**
 *
 * @author fdrea
 * @property func $update
 */
trait MustNotUpdateTrait {
    public function update(array $new_profile): int {
        throw new \Swango\Model\Exception\MustNotUpdateThisModelException(static::$model_name);
    }
    protected function onUpdateMiss(): void {}
}
