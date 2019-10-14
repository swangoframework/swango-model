<?php
Swango\Model\AbstractBaseGateway::init();
spl_autoload_register(
    function (string $classname): bool {
        $file_dir = Swango\Environment::getDir()->model . str_replace('\\', '/', $classname) . '.php';
        if (file_exists($file_dir)) {
            require $file_dir;
            if (method_exists($classname, 'onLoad') && $classname::$model_name === null) {
                $classname::$model_name = $classname;
                if ($classname::$table_name === null)
                    $classname::$table_name = strtolower(str_replace('\\', '_', $classname));
                $classname::onLoad();
                if (method_exists($classname, 'initCacheTable'))
                    $classname::initCacheTable();
            }
            return true;
        }
        return false;
    });