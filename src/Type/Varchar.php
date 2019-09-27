<?php
namespace Swango\Model\Type;
final class Varchar extends \Swango\Model\Type {
    public function intoProfile($var): ?string {
        if (! isset($var))
            return null;
        return (string)$var;
    }
}