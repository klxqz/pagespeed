<?php

function smarty_block_pagespeed($params, $content, &$smarty) {
    if (!$content) {
        return '';
    }
    $pagespeed = new pagespeed();
    return $pagespeed->acceleration($content);
}
