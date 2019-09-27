<?php
namespace Swango\Model\Exception;
class ModelNotFoundException extends \Exception {
    public static $model_name;
    public function __construct() {
        parent::__construct(self::$model_name . ' not found');
    }
}