<?php
namespace Swango\Model\Type;
final class Double extends \Swango\Model\Type {
    public function intoProfile($var): ?float {
        if (! isset($var)) {
            return null;
        }
        return (double)$var;
    }
}