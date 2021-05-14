<?php
namespace Swango\Model\Exception;
class ColumnNotExistsException extends \Exception {
    public function __construct(string $key) {
        parent::__construct('Column not exists: ' . $key);
    }
}