<?php

class pagespeedFrontendController extends waController {

    public function execute() {
        $url = waRequest::get('url');
        $type = waRequest::get('type');
        if (!$url || !$type) {
            throw new waException("File not found", 404);
        }
        if ($type == 'css') {
            $gzip = wa('pagespeed')->getConfig()->getSettings('css_gzip');
        } elseif ($type == 'js') {
            $gzip = wa('pagespeed')->getConfig()->getSettings('js_gzip');
        } else {
            throw new waException('Указан неверный тип файла: ' . $type);
        }

        $helper = new pagespeedHelper();

        if ($helper->isLocalFile($url)) {
            if ($param_ofset = strpos($url, '?')) {
                $url = substr($url, 0, $param_ofset);
            }
        }

        $minify_path = $helper->getMinifyPath($url, $type, $gzip);

        if (!is_readable($minify_path)) {
            throw new waException("File not found", 404);
        }

        if ($gzip) {
            $response = wa()->getResponse();
            $response->addHeader("Content-Encoding", "gzip");
        }

        waFiles::readFile($minify_path);
    }

}
