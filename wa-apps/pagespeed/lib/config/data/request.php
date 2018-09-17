<?php

$path = realpath(dirname(__FILE__) . "/../../../../../");
$config_path = $path . "/wa-config/SystemConfig.class.php";
if (!file_exists($config_path)) {
    header("Location: ../../../wa-apps/shop/img/image-not-found.png");
    exit;
}

require_once($config_path);
$config = new SystemConfig();
waSystem::getInstance(null, $config);


$app_config = wa('pagespeed')->getConfig();
$request_file = $app_config->getRequestUrl($without_root = true, $without_params = false);

print_r($request_file);
