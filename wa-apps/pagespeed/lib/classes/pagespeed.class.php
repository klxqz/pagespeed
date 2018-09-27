<?php

require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Minify.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/CSS.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/JS.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exception.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/BasicException.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/FileImportException.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/IOException.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/ConverterInterface.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Converter.php');

use MatthiasMullie\Minify;

class pagespeed {

    protected $helper;
    protected $settings;
    protected $css_merge_list = array();
    protected $js_merge_list = array();
    protected $css_move_list = array(
        'links' => array(),
        'urls' => array(),
    );
    protected $js_move_list = array(
        'scripts' => array(),
        'urls' => array(),
    );
    protected $lazyload_img = '';
    protected $lazyload_js = '';
    protected $delayed_loading_js = '';
    protected static $inited = false;

    public function __construct() {
        $this->helper = new pagespeedHelper();
        $this->settings = wa('pagespeed')->getConfig()->getSettings();
        $this->lazyload_img = wa()->getAppStaticUrl('pagespeed') . 'img/loading.png';
        $this->lazyload_js = wa()->getAppStaticUrl('pagespeed') . 'js/jquery.lazyload.js?' . wa('pagespeed')->getConfig()->getInfo('version');
        $this->delayed_loading_js = wa()->getAppStaticUrl('pagespeed') . 'js/delayed-loading.js?' . wa('pagespeed')->getConfig()->getInfo('version');
    }

    public static function init() {
        if (
                !self::$inited &&
                wa()->getEnv() == 'frontend'
        ) {
            $view = wa()->getView();
            $smarty_plugins_dir = wa()->getAppPath('lib/smarty-plugins/', 'pagespeed');
            $view->smarty->addPluginsDir($smarty_plugins_dir);
            self::$inited = true;

            if (
                    wa('pagespeed')->getConfig()->getSettings('status') &&
                    wa('pagespeed')->getConfig()->getSettings('html_gzip') &&
                    !waRequest::isXMLHttpRequest()
            ) {
                if (!(wa('pagespeed')->getConfig()->getSettings('debug_mode') && !wa('pagespeed')->getConfig()->getSettings('debug_html_gzip'))) {
                    $response = wa()->getResponse();
                    $response->addHeader("Content-Encoding", "gzip");
                }
            }
        }
    }

    public function acceleration($html) {
        if (!$this->settings['status']) {
            return $html;
        }

        if ($this->settings['debug_mode']) {
            $start = microtime(true);
        }

        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);

        if ($this->settings['img_lazyload']) {
            $html = $this->setRegexCallback("/<img[^>]*(src\s*=\s*[\"']([^\"']+)[\"'])[^>]*>/si", $html, 'imgRegexLazyLoad');
            $append_html = '<script type="text/javascript" src="' . $this->lazyload_js . '"></script>';
            $html = preg_replace("/(<\/body>)/is", "{$append_html}\n$1", $html);
        }

        if ($this->settings['img_browser_cache'] && !$this->settings['img_lazyload']) {
            $html = $this->setRegexCallback("/<img[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'imgRegexBrowserCache');
        }


        if ($this->settings['css_minify']) {
            $html = $this->setRegexCallback("/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'cssRegexCritical');
            
            
            $html = $this->setRegexCallback("/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'cssRegexLink');

            if ($this->settings['css_inline']) {
                $html = $this->setRegexCallback("/<style[^>]*>(.*?)<\/style>/si", $html, 'cssRegexStyle');
            }
        }

        if ($this->settings['css_merge']) {
            $html = $this->setRegexCallback("/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'regexMerge');
            if ($combine_url = $this->combine('css')) {
                $append_html = '<link href="' . $combine_url . '" rel="stylesheet" type="text/css"/>';
                $html = preg_replace("/(<\/head>)/is", "{$append_html}\n$1", $html);

                $preloader_css = '<style>#pagespeed-preloader{text-align:center;width:100%;height:100%;position:fixed;background:#fff;z-index:9999999}#pagespeed-preloader span{top:50%;position:relative;display:inline-block;vertical-align:middle;width:10px;height:10px;margin:0 auto;background:black;border-radius:50px;-webkit-animation:loader 0.9s infinite alternate;-moz-animation:loader 0.9s infinite alternate}#pagespeed-preloader span:nth-of-type(2){-webkit-animation-delay:0.3s;-moz-animation-delay:0.3s}#pagespeed-preloader span:nth-of-type(3){-webkit-animation-delay:0.6s;-moz-animation-delay:0.6s}@-webkit-keyframes loader{0%{width:10px;height:10px;opacity:0.9;-webkit-transform:translateY(0)}100%{width:24px;height:24px;opacity:0.1;-webkit-transform:translateY(-21px)}}@-moz-keyframes loader{0%{width:10px;height:10px;opacity:0.9;-moz-transform:translateY(0)}100%{width:24px;height:24px;opacity:0.1;-moz-transform:translateY(-21px)}}</style>';
                $html = preg_replace("/(<\/head>)/is", "{$preloader_css}\n$1", $html);
                $preloader = '<div id="pagespeed-preloader"><span></span><span></span><span></span></div>';
                $html = preg_replace("/(<body[^>]*>)/is", "$1\n{$preloader}", $html);

                $preloader_js = "\n<script type=\"text/javascript\">document.addEventListener(\"DOMContentLoaded\", function(){var e = document.getElementById(\"pagespeed-preloader\");e.parentNode.removeChild(e);});</script>\n";
                //$preloader_js = '<script type="text/javascript">var e=document.getElementById("pagespeed-preloader");e.parentNode.removeChild(e);</script>';
                $html = preg_replace("/(<head\b[^>]*>)/is", "$1\n{$preloader_js}", $html);
            }
        }


        if ($this->settings['css_move']) {
            $html = $this->setRegexCallback("/<link[^>]*href\s*=\s*[\"']([^\"']+)[\"'][^>]*>/si", $html, 'regexMove', 'css');



            if ($this->css_move_list['urls']) {

                $pagespeedDelayCss = "'" . implode("','", $this->css_move_list['urls']) . "'";
                $append_html = "\n<script type=\"text/javascript\">window.pagespeedDelayCss=[" . $pagespeedDelayCss . "];</script>\n";
                $append_html.= "<script type=\"text/javascript\" src=\"" . $this->delayed_loading_js . "\"></script>";
                $html = preg_replace("/(<\/body>)/is", "{$append_html}\n$1", $html);
                //$append_html = implode("\n", $this->css_move_list['links']);
                //$html = preg_replace("/(<\/body>)/is", "$append_html\n$1", $html);
            }
        }


        if ($this->settings['js_minify']) {
            $html = $this->setRegexCallback("/<script[^>]*>(.*?)<\/script>/si", $html, 'jsRegexScript');
        }
        if ($this->settings['js_merge']) {
            $html = $this->setRegexCallback("/<script[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>(.*?)<\/script>/si", $html, 'regexMerge');
            if ($combine_url = $this->combine('js')) {
                $append_html = '<script type="text/javascript" src="' . $combine_url . '"></script>';
                $html = preg_replace("/(<\/head>)/is", "{$append_html}\n$1", $html);
            }
        }

        if ($this->settings['js_move']) {
            $html = $this->setRegexCallback("/<script[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>(.*?)<\/script>/si", $html, 'regexMove', 'js');
            if ($this->js_move_list['scripts']) {
                $html = preg_replace("/(<\/body>)/is", implode("\n", $this->js_move_list['scripts']) . "\n$1", $html);
            }
        }


        if ($this->settings['html_minify']) {
            $search = array(
                '/\>[^\S ]+/s', // strip whitespaces after tags, except space
                '/[^\S ]+\</s', // strip whitespaces before tags, except space
                '/(\s)+/s', // shorten multiple whitespace sequences
            );
            $replace = array(
                '>',
                '<',
                '\\1',
            );
            $html = preg_replace($search, $replace, $html);
        }

        if ($this->settings['debug_mode']) {
            $finish = microtime(true);
            $time = $finish - $start;

            $html .= "\n<!-- pagespeed-time: {$time}сек. -->";
        }


        if ($this->settings['html_gzip']) {
            $html = gzencode($html, $this->settings['html_gzip_level'], FORCE_GZIP);
        }

        return $html;
    }

    protected function imgRegexLazyLoad($match) {
        $img = $match[0][0];
        $src_attr = $match[1][0];
        $original_url = $match[2][0];

        if ($this->settings['img_browser_cache']) {
            $new_src = 'src="' . $this->makeUrl($this->lazyload_img, 'img') . '" data-original="' . $this->makeUrl($original_url, 'img') . '"';
        } else {
            $new_src = 'src="' . $this->lazyload_img . '" data-original="' . $original_url . '"';
        }
        return str_replace($src_attr, $new_src, $img);
    }

    protected function imgRegexBrowserCache($match) {
        $src = $match[1][0];
        $url = $this->makeUrl($src, 'img');
        return str_replace($src, $url, $match[0][0]);
    }

    protected function cssRegexLink($match) {
        try {
            if (
                    !preg_match("/rel\s*=\s*[\"']stylesheet[\"']|type\s*=\s*[\"']text\/css[\"']/si", $match[0][0]) ||
                    preg_match("/data-disabled-minify\s*=\s*[\"']true[\"']/si", $match[0][0])
            ) {
                return false;
            }
            $href = $match[1][0];
            if ($url = $this->getMinifyUrl($href, 'css')) {
                return str_replace($href, $url, $match[0][0]);
            }
            return false;
        } catch (Exception $ex) {
            $this->log($ex->getMessage());
            return false;
        }
    }

    protected function cssRegexCritical($match) {
        try {
            if (!preg_match("/data-critical-css\s*=\s*[\"']true[\"']/si", $match[0][0])) {
                return false;
            }
            $url = $match[1][0];

            if ($local_path = $this->helper->isLocalFile($url)) {
                $minifier = new Minify\CSS($local_path);
                $minifier->setMaxImportSize($this->settings['css_max_import_size']);
                $content = $minifier->minify(wa()->getConfig()->getRootPath() . '/index.css', $no_save = true, $is_root_dir = true);
                return '<style data-disabled-minify="true">' . $content . '</style>';
            }


            return false;
        } catch (Exception $ex) {
            echo $ex->getMessage();
            exit('ss');
            $this->log($ex->getMessage());
            return false;
        }
    }

    protected function cssRegexStyle($match) {
        try {
            if (
                    preg_match("/data-disabled-minify\s*=\s*[\"']true[\"']/si", $match[0][0])
            ) {
                return false;
            }
            $content = $match[1][0];
            if ($url = $this->getMinifyUrl($content, 'css', true)) {
                return '<link href="' . $url . '" rel="stylesheet" type="text/css"/>';
            }
            return false;
        } catch (Exception $ex) {
            $this->log($ex->getMessage());
            return false;
        }
    }

    protected function jsRegexScript($match) {
        try {
            if (preg_match("/<script[^>]*src\s*=\s*[\"']([^\"']+)[\"'][^>]*>(.*?)<\/script>/si", $match[0][0], $match2)) {
                $src = $match2[1];
                if (($url = $this->getMinifyUrl($src, 'js'))) {
                    return str_replace($src, $url, $match[0][0]);
                }
            } elseif ($this->settings['js_inline'] && ($content = trim($match[1][0]))) {
                if (($url = $this->getMinifyUrl($content, 'js', true))) {
                    return '<script type="text/javascript" src="' . $url . '"></script>';
                }
            }
            return false;
        } catch (Exception $ex) {
            $this->log($ex->getMessage());
            return false;
        }
    }

    protected function combine($type) {
        if ($type == 'css') {
            $merge_list = $this->css_merge_list;
            $gzip = $this->settings['css_gzip'];
            $gzip_level = $this->settings['css_gzip_level'];
        } elseif ($type == 'js') {
            $merge_list = $this->js_merge_list;
            $gzip = $this->settings['js_gzip'];
            $gzip_level = $this->settings['js_gzip_level'];
        } else {
            throw new waException('Указан неверный тип файла: ' . $type);
        }

        if (!$merge_list) {
            return false;
        }

        $hash = $this->helper->stringHash(serialize($merge_list));
        $name = $hash . '.' . $type;

        $minify_path = $this->helper->getMinifyPath($name, $type, $gzip);

        if (!file_exists($minify_path)) {
            if (($handler = @fopen($minify_path, 'w')) === false) {
                throw new waException('Ошибка создания файла: ' . $minify_path);
            }
            $content = '';
            foreach ($merge_list as $merge_file) {
                $content .= file_get_contents($merge_file) . ($type == 'js' ? ';' : '');
            }
            if ($gzip) {
                $content = gzencode($content, $gzip_level, FORCE_GZIP);
            }

            if (($result = @fwrite($handler, $content)) === false || ($result < strlen($content))) {
                throw new waException('Ошибка записи в файл: ' . $minify_path);
            }
            @fclose($handler);
        }
        return $this->makeUrl($name, $type);
    }

    protected function regexMerge($match) {
        try {

            $pagespeed_url = str_replace('/', '\/', wa()->getRouteUrl('pagespeed/frontend'));
            if (!preg_match("/" . $pagespeed_url . "\?url=([^&]*)&type=([^&]*)/si", $match[1][0], $params)) {
                return false;
            }
            list($link, $url, $type) = $params;

            if (!$url || !$type) {
                return false;
            }
            if (!in_array($type, array('css', 'js'))) {
                throw new waException('Указан неверный тип файла: ' . $type);
            }

            $url = urldecode($url);

            if ($this->helper->isLocalFile($url)) {
                if ($param_ofset = strpos($url, '?')) {
                    $url = substr($url, 0, $param_ofset);
                }
            }

            $minify_path = $this->helper->getMinifyPath($url, $type);
            if (!file_exists($minify_path)) {
                return false;
            }
            $hash = $this->helper->fileHash($minify_path);


            if ($type == 'css') {
                $this->css_merge_list[$hash] = $minify_path;
            } elseif ($type == 'js') {
                $this->js_merge_list[$hash] = $minify_path;
            }

            return '';
        } catch (Exception $ex) {
            $this->log($ex->getMessage());
            return false;
        }
    }

    protected function regexMove($match, $type) {
        try {
            if ($type == 'css') {
                if (!preg_match("/rel\s*=\s*[\"']stylesheet[\"']\s*|type\s*=\s*[\"']text\/css[\"']\s*/si", $match[0][0])) {
                    return false;
                }
                $this->css_move_list['links'][] = $match[0][0];
                $this->css_move_list['urls'][] = $match[1][0];
                return '';
            } elseif ($type == 'js') {
                $this->js_move_list['scripts'][] = $match[0][0];
                $this->js_move_list['urls'][] = $match[1][0];
                return '';
            } else {
                throw new waException('Указан неверный тип файла: ' . $type);
            }
            return false;
        } catch (Exception $ex) {
            $this->log($ex->getMessage());
            return false;
        }
    }

    protected function setRegexCallback($pattern, $content, $method_name, $params = null) {
        $processed = '';
        do {
            $match = null;
            if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
                $text = $match[0][0];
                $offset = $match[0][1];
                //$start = $offset + strlen($text);
                if (!method_exists($this, $method_name)) {
                    throw new waException(sprintf('Метод %s->%s() не существует', get_class($this), $method_name));
                }
                if (($replacement = $this->$method_name($match, $params)) !== false) {
                    $processed .= substr($content, 0, $offset);
                    $processed .= $replacement;
                } else {
                    $processed .= substr($content, 0, $offset);
                    $processed .= $text;
                }

                $content = substr($content, $offset + strlen($text));
            } else {
                $processed .= $content;
            }
        } while ($match);

        return $processed;
    }

    protected function getMinifyUrl($url, $type, $is_content = false) {
        if ($type == 'css') {
            $gzip = $this->settings['css_gzip'] && !$this->settings['css_merge'];
            $download_remote_files = $this->settings['css_download_remote_files'];
            $update_time_remote_files = $this->settings['css_update_time_remote_files'];
        } elseif ($type == 'js') {
            $gzip = $this->settings['js_gzip'] && !$this->settings['js_merge'];
            $download_remote_files = $this->settings['js_download_remote_files'];
            $update_time_remote_files = $this->settings['js_update_time_remote_files'];
        } else {
            throw new waException('Указан неверный тип файла: ' . $type);
        }

        if (!$is_content) {
            $local_path = null;
            $new_url = null;

            if ($download_remote_files && $this->helper->isRemoteFile($url)) {
                $download_path = $this->helper->getDownloadPath($url, $type);

                $download = true;
                if (file_exists($download_path)) {
                    $expires_time = filemtime($download_path) + $update_time_remote_files;
                    if (time() < $expires_time) {
                        $download = false;
                    }
                }

                if ($download) {
                    $content = $this->helper->curlGetContents($url);
                    if (!$content) {
                        throw new waException('Получен пустой ответ. URL: ' . $url);
                    }
                    if (($handler = @fopen($download_path, 'w')) === false) {
                        throw new waException('Ошибка создания файла: ' . $download_path);
                    }
                    if (($result = @fwrite($handler, $content)) === false || ($result < strlen($content))) {
                        throw new waException('Ошибка записи в файл: ' . $download_path);
                    }
                    @fclose($handler);
                }

                $new_url = str_replace(wa()->getConfig()->getRootPath(), '', $download_path);
                $local_path = $download_path;
            }

            if ($local_path || ($local_path = $this->helper->isLocalFile($url))) {
                $name = $this->helper->fileHash($local_path) . '_' . ($new_url ? $new_url : $url);
                if ($param_ofset = strpos($name, '?')) {
                    $name = substr($name, 0, $param_ofset);
                }
                $minify_path = $this->helper->getMinifyPath($name, $type, $gzip);

                if (!file_exists($minify_path)) {
                    $this->minify($type, $local_path, $minify_path);
                }
                return $this->makeUrl($name, $type);
            }
        } else {
            $content = $url;
            $name = $this->helper->stringHash($content) . '.' . $type;
            $minify_path = $this->helper->getMinifyPath($name, $type, $gzip);
            if (!file_exists($minify_path)) {
                $this->minify($type, $content, $minify_path);
            }
            return $this->makeUrl($name, $type);
        }

        return false;
    }

    protected function minify($type, $source_path_or_content, $minify_path) {
        if ($type == 'css') {
            $minifier = new Minify\CSS($source_path_or_content);
            $minifier->setMaxImportSize($this->settings['css_max_import_size']);
            $gzip = $this->settings['css_gzip'] && !$this->settings['css_merge'];
            $gzip_level = $this->settings['css_gzip_level'];
        } elseif ($type == 'js') {
            $minifier = new Minify\JS($source_path_or_content);
            $gzip = $this->settings['js_gzip'] && !$this->settings['js_merge'];
            $gzip_level = $this->settings['js_gzip_level'];
        } else {
            throw new waException('Указан неверный тип файла: ' . $type);
        }

        if ($gzip) {
            $minifier->gzip($minify_path, $gzip_level);
        } else {
            $minifier->minify($minify_path);
        }
    }

    protected function makeUrl($url, $type) {
        $data = array(
            'url' => $url,
            'type' => $type,
        );
        return wa()->getRouteUrl('pagespeed/frontend') . '?' . http_build_query($data);
    }

    public function log($message) {
        waLog::log($message, 'pagespeed.log');
    }

}
