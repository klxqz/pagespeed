<?php

class pagespeedJsOptimizer extends pagespeedOptimizer {

    protected $move_list = array(
        'links' => array(),
        'urls' => array(),
    );

    public function execute($html) {
        if ($this->settings['minify']) {
            $html = $this->setRegexCallback("/<script[^>]*>(.*?)<\/script>/si", $html, 'jsRegexScript');
        }
        if ($this->settings['merge']) {
            $html = $this->setRegexCallback("/<script[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>(.*?)<\/script>/si", $html, 'regexMerge');
            if ($combine_url = $this->combine()) {
                $append_html = '<script type="text/javascript" src="' . $combine_url . '"></script>';
                $html = preg_replace("/(<\/head>)/is", "{$append_html}\n$1", $html);
            }
        }

        if ($this->settings['move']) {
            $html = $this->setRegexCallback("/<script[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>(.*?)<\/script>/si", $html, 'regexMove', 'js');
            if ($this->move_list['scripts']) {
                $html = preg_replace("/(<\/body>)/is", implode("\n", $this->move_list['scripts']) . "\n$1", $html);
            }
        }
        return $html;
    }

    protected function jsRegexScript($match) {
        try {
            if (preg_match("/<script[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>(.*?)<\/script>/si", $match[0][0], $match2)) {
                $src = $match2[1];
                if (($url = $this->getMinifyUrl($src, 'js'))) {
                    return str_replace($src, $url, $match[0][0]);
                }
            } elseif ($this->settings['inline'] && ($content = trim($match[1][0]))) {
                if (($url = $this->getMinifyUrl($content, 'js', true))) {
                    return '<script type="text/javascript" src="' . $url . '"></script>';
                }
            }
            return false;
        } catch (Exception $ex) {
            self::log($ex->getMessage());
            return false;
        }
    }

    protected function regexMove($match, $type) {
        $this->move_list['scripts'][] = $match[0][0];
        $this->move_list['urls'][] = $match[1][0];
        return '';
    }

}
