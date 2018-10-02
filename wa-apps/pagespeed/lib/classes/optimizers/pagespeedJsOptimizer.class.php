<?php

class pagespeedJsOptimizer extends pagespeedOptimizer {

    protected $search_pattern = "/<script[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>.*?<\/script>|<script[^>]*>(.*?)<\/script>/si";
    protected $move_list = array();

    protected function searchFilter($match) {
        if (preg_match("/data-disabled-minify\s*=\s*[\"']true[\"']/si", $match)) {
            return false;
        }

        return true;
    }

    protected function buildReplacements($matches) {
        $this->replacements = array();
        foreach ($matches[0] as $index => $search) {
            $this->replacements[$search] = array(
                'replace' => $search,
                'data' => array(
                    'url' => $matches[1][$index],
                    'content' => $matches[2][$index],
                ),
            );
        }
    }

    protected function updateReplacement(&$replacement, $replace = '', $url = '', $content = '') {
        $replacement['replace'] = $replace;
        $replacement['data']['url'] = $url;
        $replacement['data']['content'] = $content;
    }

    public function execute() {
        if (!$this->replacements) {
            return false;
        }

        if ($this->settings['minify']) {
            foreach ($this->replacements as &$replacement) {
                if (!$replacement['replace']) {
                    continue;
                }
                if ($src = $replacement['data']['url']) {
                    if ($url = $this->getMinifyUrl($src, 'js')) {
                        $this->updateReplacement($replacement, str_replace($src, $url, $replacement['replace']), $url);
                    }
                } elseif ($this->settings['inline'] && ($content = $replacement['data']['content'])) {
                    if ($url = $this->getMinifyUrl($content, 'js', true)) {
                        $this->updateReplacement($replacement, '<script type="text/javascript" src="' . $url . '"></script>', $url);
                    }
                }
            }
            foreach (self::$appends as &$append) {
                if ($append['tag'] != 'script') {
                    continue;
                }
                if ($src = $append['url']) {
                    if ($url = $this->getMinifyUrl($src, 'js')) {
                        $append['html'] = str_replace($append['url'], $url, $append['html']);
                        $append['url'] = $url;
                    }
                } elseif ($this->settings['inline'] && ($content = $append['content'])) {
                    if ($url = $this->getMinifyUrl($content, 'js', true)) {
                        $append['html'] = '<script type="text/javascript" src="' . $url . '"></script>';
                        $append['url'] = $url;
                        $append['content'] = '';
                    }
                }
            }
        }

        if ($this->settings['merge']) {
            $this->merge();
            $pagespeed_url = str_replace('/', '\/', wa()->getRouteUrl('pagespeed/frontend'));
            foreach (self::$appends as $index => $append) {
                if ($append['tag'] != 'script' || !$append['url']) {
                    continue;
                }
                if (!preg_match("/" . $pagespeed_url . "\?url=([^&]*)&type=([^&]*)/si", $append['url'], $params)) {
                    continue;
                }
                list($link, $url, $type) = $params;

                $url = urldecode($url);

                if (self::isLocalFile($url)) {
                    if ($param_ofset = strpos($url, '?')) {
                        $url = substr($url, 0, $param_ofset);
                    }
                }

                $minify_path = self::getMinifyPath($url, $type);
                if (file_exists($minify_path)) {
                    $hash = self::fileHash($minify_path);
                    $this->merge_list[$hash] = $minify_path;
                    unset(self::$appends[$index]);
                }
            }

            if ($combine_url = $this->combine()) {
                $this->append(self::HEAD_CLOSE, 'script', $combine_url);
            }
        }


        if ($this->settings['move']) {
            foreach (self::$appends as &$append) {
                if ($append['tag'] != 'script') {
                    continue;
                }
                $append['place'] = self::BODY_CLOSE;
            }
        }


        return true;
    }

}
