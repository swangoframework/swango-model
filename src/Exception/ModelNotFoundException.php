<?php
namespace Swango\Model\Exception;
class ModelNotFoundException extends \Exception {
    public static string $model_name;
    private array $index;
    public function __construct(...$index) {
        parent::__construct(static::$model_name . ' not found');
        $this->index = $index;
    }
    public function getIndex(): array {
        return $this->index;
    }
}