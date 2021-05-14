<?php
namespace Swango\Model\Exception;
class CannotChangeIndexException extends \Exception {
    public function __construct() {
        parent::__construct('Column not exists');
    }
}