<?php

/**
 * Bootstrap for Swango\Model .
 */
SysContext::setClearFunc(
    function (stdClass &$ob) {
        if (property_exists($ob, 'factory'))
            $factories = $ob->factory;
        $ob = null;
        if (isset($factories))
            foreach ($factories as &$factory) {
                $factory->clear();
                $factory = null;
            }
    });
Swango\Model\AbstractBaseGateway::init();