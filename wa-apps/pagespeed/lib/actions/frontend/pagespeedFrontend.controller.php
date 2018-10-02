<?php

class pagespeedFrontendController extends waController {

    public function execute() {
        $url = waRequest::get('url');
        $type = waRequest::get('type');
        if (!$url || !$type) {
            throw new waException("File not found", 404);
        }
        $settings = wa('pagespeed')->getConfig()->getSettings();
        $gzip = 0;
        $browser_cache = 0;
        $browser_cache_time = 0;
        if ($type == 'css') {
            $gzip = $settings['css_gzip'];
            $browser_cache = $settings['css_browser_cache'];
            $browser_cache_time = $settings['css_browser_cache_time'];
        } elseif ($type == 'js') {
            $gzip = $settings['js_gzip'];
            $browser_cache = $settings['js_browser_cache'];
            $browser_cache_time = $settings['js_browser_cache_time'];
        } elseif ($type == 'img') {
            $browser_cache = $settings['img_browser_cache'];
            $browser_cache_time = $settings['img_browser_cache_time'];
        } else {
            throw new waException('Указан неверный тип файла: ' . $type);
        }

        if ($local_path = pagespeedOptimizer::isLocalFile($url)) {
            if ($param_ofset = strpos($url, '?')) {
                $url = substr($url, 0, $param_ofset);
            }
        }

        if ($type == 'img') {
            $path = $local_path;
        } else {
            $path = pagespeedOptimizer::getMinifyPath($url, $type, $gzip);
        }

        if (!file_exists($path)) {
            throw new waException("File not found", 404);
        }

        if ($gzip) {
            $response = wa()->getResponse();
            $response->addHeader("Content-Encoding", "gzip");
        }

        if ($browser_cache && $browser_cache_time) {
            $response = wa()->getResponse();
            $response->addHeader("Cache-control", "public");
            $response->addHeader("Expires", gmdate("D, d M Y H:i:s", time() + $browser_cache_time) . " GMT");
        }

        waFiles::readFile($path);
    }

}
