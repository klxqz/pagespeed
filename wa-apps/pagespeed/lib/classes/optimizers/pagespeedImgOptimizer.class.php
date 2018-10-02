<?php

class pagespeedImgOptimizer extends pagespeedOptimizer {

    protected $lazyload_img = '';
    protected $lazyload_js = '';

    public function __construct($settings) {
        parent::__construct($settings);
        $this->lazyload_img = wa()->getAppStaticUrl('pagespeed') . 'img/loading.png';
        $this->lazyload_js = wa()->getAppStaticUrl('pagespeed') . 'js/jquery.lazyload.js?' . wa('pagespeed')->getConfig()->getInfo('version');
    }

    protected function buildReplacements($matches) {
        $this->replacements = array();
        foreach ($matches[0] as $index => $search) {
            $this->replacements[$search] = array(
                'replace' => $search,
                'data' => array(
                    'src' => $matches[1][$index],
                    'url' => $matches[2][$index],
                ),
            );
        }
    }

    protected function updateReplacement(&$replacement, $replace) {
        $replacement['replace'] = $replace;
    }

    public function execute($html) {
        $matches = $this->search($html, "/<img[^>]*(src\s*=\s*[\"']([^\"']+)[\"'])[^>]*>/si");

        if ($this->settings['lazyload']) {
            foreach ($this->replacements as &$replacement) {
                if ($this->settings['browser_cache']) {
                    $new_src = 'src="' . self::makeUrl($this->lazyload_img, 'img') . '" data-original="' . self::makeUrl($replacement['data']['url'], 'img') . '"';
                } else {
                    $new_src = 'src="' . $this->lazyload_img . '" data-original="' . $replacement['data']['url'] . '"';
                }
                $replace = str_replace($replacement['data']['src'], $new_src, $replacement['replace']);
                $this->updateReplacement($replacement, $replace);
            }
            $this->append(self::BODY_CLOSE, 'script', $this->lazyload_js);
        }

        if ($this->settings['browser_cache'] && !$this->settings['lazyload']) {
            foreach ($this->replacements as &$replacement) {
                $url = $replacement['data']['url'];
                $new_url = self::makeUrl($url, 'img');
                $this->updateReplacement($replacement, str_replace($url, $new_url, $replacement['replace']));
            }
        }
    }

}
