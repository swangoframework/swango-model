<?php
namespace Swango\Model\Type;
final class Integer extends \Swango\Model\Type {
    public function intoProfile($var): ?int {
        if (! isset($var)) {
            return null;
        }
        return (int)$var;
    }
}