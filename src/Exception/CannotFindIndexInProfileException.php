<?php
namespace Swango\Model\Exception;
class CannotFindIndexInProfileException extends \Exception {
    public function __construct($index) {
        parent::__construct('Cannot find index in profile' . " ($index)");
    }
}