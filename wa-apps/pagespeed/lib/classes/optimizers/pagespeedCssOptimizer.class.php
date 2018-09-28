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

    protected $critical_list = array();
    protected $move_list = array(
        'links' => array(),
        'urls' => array(),
    );
    protected $delayed_loading_js = '';

    public function __construct($settings) {
        parent::__construct($settings);
        $this->delayed_loading_js = wa()->getAppStaticUrl('pagespeed') . 'js/delayed-loading.js?' . wa('pagespeed')->getConfig()->getInfo('version');
    }

    public function execute($html) {
        if ($this->settings['minify']) {
            $html = $this->setRegexCallback("/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'cssRegexLink');

            if ($this->settings['inline']) {
                $html = $this->setRegexCallback("/<style[^>]*>(.*?)<\/style>/si", $html, 'cssRegexStyle');
            }

            $html = $this->setRegexCallback("/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>|<style[^>]*>(.*?)<\/style>/si", $html, 'cssRegexCritical');
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

                $html = preg_replace("/(<\/head>)/is", "<style data-disabled-minify=\"true\">{$critical_css}</style>\n$1", $html);
            }
        }

        if ($this->settings['merge']) {
            $html = $this->setRegexCallback("/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'regexMerge');
            
            if ($combine_url = $this->combine()) {
                $append_html = '<link href="' . $combine_url . '" rel="stylesheet" type="text/css"/>';
                $html = preg_replace("/(<\/head>)/is", "{$append_html}\n$1", $html);
           
                $preloader_css = '<style>#pagespeed-preloader{text-align:center;width:100%;height:100%;position:fixed;background:#fff;z-index:9999999}#pagespeed-preloader span{top:50%;position:relative;display:inline-block;vertical-align:middle;width:10px;height:10px;margin:0 auto;background:black;border-radius:50px;-webkit-animation:loader 0.9s infinite alternate;-moz-animation:loader 0.9s infinite alternate}#pagespeed-preloader span:nth-of-type(2){-webkit-animation-delay:0.3s;-moz-animation-delay:0.3s}#pagespeed-preloader span:nth-of-type(3){-webkit-animation-delay:0.6s;-moz-animation-delay:0.6s}@-webkit-keyframes loader{0%{width:10px;height:10px;opacity:0.9;-webkit-transform:translateY(0)}100%{width:24px;height:24px;opacity:0.1;-webkit-transform:translateY(-21px)}}@-moz-keyframes loader{0%{width:10px;height:10px;opacity:0.9;-moz-transform:translateY(0)}100%{width:24px;height:24px;opacity:0.1;-moz-transform:translateY(-21px)}}</style>';
                $html = preg_replace("/(<\/head>)/is", "{$preloader_css}\n$1", $html);
                $preloader = '<div id="pagespeed-preloader"><span></span><span></span><span></span></div>';
                $html = preg_replace("/(<body[^>]*>)/is", "$1\n{$preloader}", $html);

                $preloader_js = "\n<script type=\"text/javascript\">document.addEventListener(\"DOMContentLoaded\", function(){var e = document.getElementById(\"pagespeed-preloader\");e.parentNode.removeChild(e);});</script>\n";
                $html = preg_replace("/(<head\b[^>]*>)/is", "$1\n{$preloader_js}", $html);
            }
        }


        if ($this->settings['move']) {
            $html = $this->setRegexCallback("/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'regexMove', 'css');

            if ($this->move_list['urls']) {

                $pagespeedDelayCss = "'" . implode("','", $this->move_list['urls']) . "'";
                $append_html = "\n<script type=\"text/javascript\">window.pagespeedDelayCss=[" . $pagespeedDelayCss . "];</script>\n";
                $append_html.= "<script type=\"text/javascript\" src=\"" . $this->delayed_loading_js . "\"></script>";
                $html = preg_replace("/(<\/body>)/is", "{$append_html}\n$1", $html);
                //$append_html = implode("\n", $this->css_move_list['links']);
                //$html = preg_replace("/(<\/body>)/is", "$append_html\n$1", $html);
            }
        }
        return $html;
    }

    protected function cssRegexCritical($match) {
        if (!preg_match("/data-critical-css\s*=\s*[\"']true[\"']/si", $match[0][0])) {
            return false;
        }
        if (!empty($match[2][0])) {
            $this->critical_list[] = array('content' => $match[2][0]);
        } elseif (self::isLocalFile($match[1][0])) {
            if ($param_ofset = strpos($match[1][0], '?')) {
                $match[1][0] = substr($match[1][0], 0, $param_ofset);
            }
            $this->critical_list[] = array('url' => $match[1][0]);
        }
        return '';
    }

    protected function cssRegexLink($match) {

        try {
            if (
                    !preg_match("/rel\s*=\s*[\"']stylesheet[\"']|type\s*=\s*[\"']text\/css[\"']/si", $match[0][0]) ||
                    preg_match("/data-disabled-minify\s*=\s*[\"']true[\"']/si", $match[0][0]) ||
                    preg_match("/data-critical-css\s*=\s*[\"']true[\"']/si", $match[0][0])
            ) {
                return false;
            }
            $href = $match[1][0];
            if ($url = $this->getMinifyUrl($href, 'css')) {
                return str_replace($href, $url, $match[0][0]);
            }
            return false;
        } catch (Exception $ex) {
            self::log($ex->getMessage());
            return false;
        }
    }

    protected function cssRegexStyle($match) {
 
        try {
            if (
                    preg_match("/data-disabled-minify\s*=\s*[\"']true[\"']/si", $match[0][0]) ||
                    preg_match("/data-critical-css\s*=\s*[\"']true[\"']/si", $match[0][0])
            ) {
                return false;
            }
  
            $content = $match[1][0];
            if ($url = $this->getMinifyUrl($content, 'css', true)) {
                return '<link href="' . $url . '" rel="stylesheet" type="text/css"/>';
            }
            return false;
        } catch (Exception $ex) {
            self::log($ex->getMessage());
            return false;
        }
    }

    protected function regexMove($match) {
        if (!preg_match("/rel\s*=\s*[\"']stylesheet[\"']\s*|type\s*=\s*[\"']text\/css[\"']\s*/si", $match[0][0])) {
            return false;
        }
        $this->move_list['links'][] = $match[0][0];
        $this->move_list['urls'][] = $match[1][0];
        return '';
    }

}
