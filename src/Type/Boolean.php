<?php
namespace Swango\Model\Type;
final class Boolean extends \Swango\Model\Type {
    public function intoProfile(?string $var): ?bool {
        if (! isset($var))
            return null;
        return (boolean)$var;
    }
    public function intoDB($var): ?int {
        if (! isset($var))
            return null;
        return (int)$var;
    }
}