<?php
namespace Swango\Model\Traits;
trait onNotFoundTrait {
    protected function onNotFound($event): void {
        $this->{"on{$event}Miss"}();
    }
    abstract protected function onLoadMiss(): void;
    abstract protected function onRemoveMiss(): void;
    abstract protected function onUpdateMiss(): void;
}