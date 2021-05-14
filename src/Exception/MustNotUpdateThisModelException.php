<?php
namespace Swango\Model\Exception;
class MustNotUpdateThisModelException extends \Exception {
    public function __construct($model_name) {
        parent::__construct('Must not update this model: ' . $model_name);
    }
}