<?php

$app_id = 'pagespeed';

$target_path = wa()->getDataPath('', true, $app_id);
$source_path = wa()->getAppPath('lib/config/data', $app_id);

$target = $target_path . '/index.php';
if (!file_exists($target)) {
    $php_file = '<?php
                
$file = dirname(__FILE__) . "/../../../" . "/wa-apps/pagespeed/lib/config/data/request.php";

if (file_exists($file)) {
    include($file);
} else {
    header("HTTP/1.0 404 Not Found");
}
';
    waFiles::write($target, $php_file);
}

$target = $target_path . '/.htaccess';
if (!file_exists($target)) {
    waFiles::copy($source_path . '/.htaccess', $target);
}