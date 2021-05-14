<?php
namespace Swango\Model\Exception;
class IncorrectIndexCountException extends \Exception {
    public function __construct($given_count, $should_count) {
        parent::__construct('Incorrect index count' . " ($given_count/$should_count)");
    }
}