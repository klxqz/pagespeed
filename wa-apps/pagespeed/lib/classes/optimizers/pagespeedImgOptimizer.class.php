<?php

class pagespeedImgOptimizer extends pagespeedOptimizer {

    protected $lazyload_img = '';
    protected $lazyload_js = '';

    public function __construct($settings) {
        parent::__construct($settings);
        $this->lazyload_img = wa()->getAppStaticUrl('pagespeed') . 'img/loading.png';
        $this->lazyload_js = wa()->getAppStaticUrl('pagespeed') . 'js/jquery.lazyload.js?' . wa('pagespeed')->getConfig()->getInfo('version');
    }

    public function execute($html) {
        if ($this->settings['lazyload']) {
            $html = $this->setRegexCallback("/<img[^>]*(src\s*=\s*[\"']([^\"']+)[\"'])[^>]*>/si", $html, 'imgRegexLazyLoad');
            $append_html = '<script type="text/javascript" src="' . $this->lazyload_js . '"></script>';
            $html = preg_replace("/(<\/body>)/is", "{$append_html}\n$1", $html);
        }

        if ($this->settings['browser_cache'] && !$this->settings['lazyload']) {
            $html = $this->setRegexCallback("/<img[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'imgRegexBrowserCache');
        }

        if ($this->settings['browser_cache']) {
            $html = $this->setRegexCallback("/url\([\"']?(.*?)[\"']?\)/si", $html, 'imgRegexUrl');
        }

        return $html;
    }

    protected function imgRegexLazyLoad($match) {
        $img = $match[0][0];
        $src_attr = $match[1][0];
        $original_url = $match[2][0];

        if ($this->settings['browser_cache']) {
            $new_src = 'src="' . self::makeUrl($this->lazyload_img, 'img') . '" data-original="' . self::makeUrl($original_url, 'img') . '"';
        } else {
            $new_src = 'src="' . $this->lazyload_img . '" data-original="' . $original_url . '"';
        }
        return str_replace($src_attr, $new_src, $img);
    }

    protected function imgRegexBrowserCache($match) {

        $src = $match[1][0];
        $url = self::makeUrl($src, 'img');
        return str_replace($src, $url, $match[0][0]);
    }

    protected function imgRegexUrl($match) {
        $src = $match[1][0];
        if (strpos($src, 'data:image') !== false) {
            return false;
        }
        $url = self::makeUrl($src, 'img');
        return str_replace($src, $url, $match[0][0]);
    }

}
