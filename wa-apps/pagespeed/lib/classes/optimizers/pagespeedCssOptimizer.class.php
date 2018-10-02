<?php

require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Minify.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/CSS.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exception.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/BasicException.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/FileImportException.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/IOException.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/ConverterInterface.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Converter.php');

use MatthiasMullie\Minify;

class pagespeedCssOptimizer extends pagespeedOptimizer {

    protected $search_pattern = "/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>|<style[^>]*>(.*?)<\/style>/si";
    protected $critical_list = array();
    protected $move_list = array();
    protected $delayed_loading_js = '';

    public function __construct($settings) {
        parent::__construct($settings);
        $this->delayed_loading_js = wa()->getAppStaticUrl('pagespeed') . 'js/delayed-loading.js?' . wa('pagespeed')->getConfig()->getInfo('version');
    }

    protected function searchFilter($match) {
        if (strpos($match, '<link') !== false) {
            if (
                    !preg_match("/rel\s*=\s*[\"']stylesheet[\"']|type\s*=\s*[\"']text\/css[\"']/si", $match) ||
                    preg_match("/data-disabled-minify\s*=\s*[\"']true[\"']/si", $match)
            ) {
                return false;
            }
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
                if (preg_match("/data-critical-css\s*=\s*[\"']true[\"']/si", $replacement['replace'])) {
                    if (!empty($replacement['data']['content'])) {
                        $this->critical_list[] = array('content' => $replacement['data'][2]);
                        $this->updateReplacement($replacement);
                    } elseif (self::isLocalFile($replacement['data']['url'])) {
                        $url = $replacement['data']['url'];
                        if ($param_ofset = strpos($url, '?')) {
                            $url = substr($url, 0, $param_ofset);
                        }
                        $this->critical_list[] = array('url' => $url);
                        $this->updateReplacement($replacement);
                    }
                } elseif (strpos($replacement['replace'], '<link') !== false) {
                    $href = $replacement['data']['url'];
                    if ($url = $this->getMinifyUrl($href, 'css')) {
                        $this->updateReplacement($replacement, str_replace($href, $url, $replacement['replace']), $url);
                    }
                } elseif ($this->settings['inline'] && ($content = $replacement['data']['content'])) {
                    if ($url = $this->getMinifyUrl($content, 'css', true)) {
                        $this->updateReplacement($replacement, '<link href="' . $url . '" rel="stylesheet" type="text/css"/>', $url);
                    }
                }
            }
        }

        if ($this->settings['merge']) {
            $this->merge();

            if ($combine_url = $this->combine()) {
                $this->append(self::HEAD_CLOSE, 'link', $combine_url);
            }
        }

        if ($this->settings['move']) {
            foreach (self::$appends as $index => &$append) {
                if ($append['tag'] == 'link') {
                    $this->move_list[] = $append['url'];
                    unset(self::$appends[$index]);
                }
            }

            if ($this->move_list) {
                $this->append(self::BODY_CLOSE, 'script', null, "window.pagespeedDelayCss=['" . implode("','", $this->move_list) . "']");
                $this->append(self::BODY_CLOSE, 'script', $this->delayed_loading_js);
            }
        }

        if ($this->critical_list) {
            $hash = self::stringHash(serialize($this->critical_list));
            $name = $hash . '.' . $this->type;
            $minify_path = self::getMinifyPath($name, $this->type);
            if (!file_exists($minify_path)) {
                $critical_css = '';
                foreach ($this->critical_list as $critical) {
                    if (!empty($critical['url'])) {
                        if ($local_path = self::isLocalFile($critical['url'])) {
                            $minifier = new Minify\CSS($local_path);
                            $critical_css .= $minifier->minify(wa()->getConfig()->getRootPath() . '/index.css', $no_save = true, $is_root_dir = true);
                        }
                    } elseif (!empty($critical['content'])) {
                        $minifier = new Minify\CSS($critical['content']);
                        $critical_css .= $minifier->minify();
                    }
                }
                if (($handler = @fopen($minify_path, 'w')) === false) {
                    throw new waException('Ошибка создания файла: ' . $minify_path);
                }
                if (($result = @fwrite($handler, $critical_css)) === false || ($result < strlen($critical_css))) {
                    throw new waException('Ошибка записи в файл: ' . $minify_path);
                }
                @fclose($handler);
            } else {
                $critical_css = file_get_contents($minify_path);
            }
            $this->append(self::HEAD_CLOSE, 'style', null, $critical_css);
        }


        if ($this->settings['preloader']) {
            $preloader_css = '#pagespeed-preloader{text-align:center;width:100%;height:100%;position:fixed;background:#fff;z-index:9999999}#pagespeed-preloader span{top:50%;position:relative;display:inline-block;vertical-align:middle;width:10px;height:10px;margin:0 auto;background:black;border-radius:50px;-webkit-animation:loader 0.9s infinite alternate;-moz-animation:loader 0.9s infinite alternate}#pagespeed-preloader span:nth-of-type(2){-webkit-animation-delay:0.3s;-moz-animation-delay:0.3s}#pagespeed-preloader span:nth-of-type(3){-webkit-animation-delay:0.6s;-moz-animation-delay:0.6s}@-webkit-keyframes loader{0%{width:10px;height:10px;opacity:0.9;-webkit-transform:translateY(0)}100%{width:24px;height:24px;opacity:0.1;-webkit-transform:translateY(-21px)}}@-moz-keyframes loader{0%{width:10px;height:10px;opacity:0.9;-moz-transform:translateY(0)}100%{width:24px;height:24px;opacity:0.1;-moz-transform:translateY(-21px)}}';
            $this->append(self::HEAD_CLOSE, 'style', null, $preloader_css);
            $preloader = '<div id="pagespeed-preloader"><span></span><span></span><span></span></div>';
            $this->append(self::BODY_OPEN, $preloader);
            $preloader_js = "document.addEventListener(\"DOMContentLoaded\", function(){var e = document.getElementById(\"pagespeed-preloader\");e.parentNode.removeChild(e);})";
            $this->append(self::HEAD_CLOSE, 'script', null, $preloader_js);
        }
        return true;
    }

}
