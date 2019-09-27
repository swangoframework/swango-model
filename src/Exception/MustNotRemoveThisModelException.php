<?php
namespace Swango\Model\Exception;
class MustNotRemoveThisModelException extends \Exception {
    public function __construct($model_name) {
        parent::__construct('Must not remove this model: ' . $model_name);
    }
}