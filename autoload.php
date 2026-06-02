<?php

spl_autoload_register(function (string $className) {
    if (strpos($className, ONION_WORDPRESS_DEVELOPER_TOOLBOX_NAMESPACE) !== 0) {
        return;
    }

    $pathParts = explode('\\', $className);
    $pathParts[0] = 'src';
    
    include ONION_WORDPRESS_DEVELOPER_TOOLBOX_PLUGIN_FOLDER . '/' . implode('/', $pathParts) . '.php';
});
