<?php
namespace Swango\Model\Type;
final class StringEnum extends \Swango\Model\Type {
    public function __construct(private string $enum_class) {
    }
    public function intoProfile($var): ?\BackedEnum {
        if (! isset($var)) {
            return null;
        }
        if ($var instanceof \BackedEnum) {
            if (! is_a($var, $this->enum_class)) {
                throw new \Exception('Invalid enum:[' . get_class($var) . "] expect:[{$this->enum_class}]");
            }
            return $var;
        }
        return ($this->enum_class)::from((string)$var);
    }
    public function intoDB($var): ?string {
        if ($var instanceof \BackedEnum) {
            return $var->value;
        }
        return $var;
    }
}