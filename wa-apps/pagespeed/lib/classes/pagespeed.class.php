<?php

include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/simpleHtmlDom/simple_html_dom.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Minify.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/CSS.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/JS.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exception.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/BasicException.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/FileImportException.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Exceptions/IOException.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/ConverterInterface.php');
include(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/minify/src/Converter.php');

use MatthiasMullie\Minify;

class pagespeed {

    protected $helper;
    protected $settings;

    public function __construct() {
        $this->helper = new pagespeedHelper();
        $this->settings = wa('pagespeed')->getConfig()->getSettings();
    }

    public function acceleration($html) {
        if (empty($this->settings['status'])) {
            return $html;
        }

        $dom = new simple_html_dom();
        $dom->load($html, $lowercase = true, $stripRN = false, $defaultBRText = DEFAULT_BR_TEXT);

        if (!empty($this->settings['css_minify'])) {
            $this->css($dom);
        }



        $html = $dom->save();
        return $html;
    }

    protected function css(&$dom) {
        $links = $dom->find('link');
        if ($links) {
            foreach ($links as &$link) {
                if (!(
                        (isset($link->type) && $link->type == 'text/css') ||
                        (isset($link->rel) && $link->rel == 'stylesheet') ||
                        (isset($link->property) && $link->property == 'stylesheet')
                        )) {
                    continue;
                }

                if ($this->helper->isRemoteFile($link->href) && !empty($this->settings['css_download_remote_files'])) {
                    try {
                        $url = $link->href;
                        $minify_path = $this->helper->getMinifyPath($url, 'css', $this->settings['css_gzip']);

                        $download = true;
                        if (is_readable($minify_path)) {
                            $update_time = ifset($this->settings['css_update_time_remote_files']);
                            $expires_time = filemtime($minify_path) + $update_time;
                            if (time() < $expires_time) {
                                $download = false;
                            }
                        }

                        if ($download) {
                            $content = $this->helper->curlGetContents($url);
                            if (!$content) {
                                throw new waException('Получен пустой ответ. URL: ' . $url);
                            }
                            $this->minifyCss($content, $minify_path);
                        }
                        $link->href = $this->makeUrl($url, 'css');
                    } catch (Exception $ex) {
                        $this->log($ex->getMessage());
                    }
                } elseif ($local_path = $this->helper->isLocalFile($link->href)) {
                    $name = $link->href;
                    if ($param_ofset = strpos($name, '?')) {
                        $name = substr($name, 0, $param_ofset);
                    }
                    $minify_path = $this->helper->getMinifyPath($name, 'css', $this->settings['css_gzip']);

                    if (
                            !is_readable($minify_path) ||
                            $this->helper->fileHash($local_path) != $this->helper->fileHash($minify_path)
                    ) {
                        $this->minifyCss($local_path, $minify_path);
                    }
                    $link->href = $this->makeUrl($link->href, 'css');
                }
            }
            unset($link);
        }

        $styles = $dom->find('style');
        if ($styles && !empty($this->settings['css_style'])) {
            foreach ($styles as &$style) {
                $content = $style->innertext;
                $hash = $this->helper->stringHash($content);
                $name = $hash . '.css';

                $minify_path = $this->helper->getMinifyPath($name, 'css', $this->settings['css_gzip']);

                if (!is_readable($minify_path)) {
                    $this->minifyCss($content, $minify_path);
                }
                $style->outertext = str_get_html('<link href="' . $this->makeUrl($name, 'css') . '" rel="stylesheet" type="text/css"/>');
            }
            unset($style);
        }

        if (!empty($this->settings['css_minify'])) {
            $this->mergeCss($dom);
        }
    }

    protected function mergeCss(&$dom) {

        $links = $dom->find('style');
        if (!$links) {
            return;
        }
        foreach ($links as &$link) {
            /*
              if (!(
              (isset($link->type) && $link->type == 'text/css') ||
              (isset($link->rel) && $link->rel == 'stylesheet') ||
              (isset($link->property) && $link->property == 'stylesheet')
              )) {
              continue;
              } */

            echo $link->outertext . ' ' . $link->href . "\n";
        }
        unset($link);
        exit('ss');
    }

    protected function minifyCss($source_path_or_content, $minify_path) {
        $minifier = new Minify\CSS($source_path_or_content);
        $minifier->setMaxImportSize(5);
        if (!empty($this->settings['css_gzip'])) {
            $css_gzip_level = ifset($this->settings['css_gzip_level']);
            $minifier->gzip($minify_path, $css_gzip_level);
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

    protected function minifyJS($html) {
        $this->log('minifyJS');
        $dom = new simple_html_dom();
        $dom->load($html, $lowercase = true, $stripRN = false, $defaultBRText = DEFAULT_BR_TEXT);
        $scripts = $dom->find('script');

        foreach ($scripts as $script) {
            if (isset($script->src)) {
                $this->log('Обработка JS: ' . $script->src);
                if ($this->helper->isRemoteFile($script->src)) {
                    //удаленный файл
                    $content = $this->helper->curlGetContents($script->src);
                    $minifier = new Minify\JS($content);
                    $name = $this->helper->validFileName($script->src);
                    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) != 'js') {
                        $name .= '.js';
                    }
                    $minifier->minify(wa()->getDataPath($name, true, 'pagespeed'));
                    $script->src = wa()->getDataUrl($name, true, 'pagespeed');
                } elseif ($local_path = $this->helper->isLocalFile($script->src)) {
                    //локальный файл
                    $name = basename($local_path);
                    $minifier = new Minify\JS($local_path);
                    $minifier->minify(wa()->getDataPath($name, true, 'pagespeed'));
                    $script->src = wa()->getDataUrl($name, true, 'pagespeed');
                }
            } else {
                $content = $script->innertext;
                $name = md5($content) . '.js';
                $minifier = new Minify\JS($content);
                $minifier->minify(wa()->getDataPath($name, true, 'pagespeed'));
                $script->src = wa()->getDataUrl($name, true, 'pagespeed');
                $script->innertext = '';
            }
        }

        return $dom->save();
    }

    public function log($message) {
        waLog::log($message, 'pagespeed.log');
    }

}
