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

abstract class pagespeedOptimizer {

    protected $search_pattern = '';
    protected $type;
    protected $settings = array();
    protected $merge_list = array();
    protected $replacements = array();
    protected static $appends = array();

    const HEAD_OPEN = 'after_open_head';
    const HEAD_CLOSE = 'before_close_head';
    const BODY_OPEN = 'after_open_body';
    const BODY_CLOSE = 'before_close_body';

    public function __construct($settings) {
        $class_name = get_class($this);
        if (!preg_match("/pagespeed(.*)Optimizer/si", $class_name, $match)) {
            throw new waException('Не определен тип класса: ' . $class_name);
        }
        $this->type = strtolower($match[1]);
        $this->settings = $settings;
    }

    public function getType() {
        return $this->type;
    }

    public static function getAppends() {
        return self::$appends;
    }

    public function append($place = self::HEAD_CLOSE, $tag, $url = null, $content = null) {

        switch ($tag) {
            case 'link':
                $html = '<link href="' . $url . '" rel="stylesheet" type="text/css"/>';
                break;
            case 'style':
                $html = "<style>" . $content . "</style>";
                break;
            case 'script':
                if ($url) {
                    $html = "<script type=\"text/javascript\" src=\"" . $url . "\"></script>";
                } else {
                    $html = "<script type=\"text/javascript\">" . $content . "</script>";
                }
                break;

            default:
                $html = $tag;
                $tag = '';
                break;
        }

        self::$appends[] = array(
            'html' => $html,
            'tag' => $tag,
            'url' => $url,
            'content' => $content,
            'place' => $place,
            'optimizer' => $this->type,
        );
    }



    public function replace($html) {
        foreach ($this->replacements as $search => $replacement) {
            if ($search != $replacement['replace']) {
                $html = str_replace($search, $replacement['replace'], $html);
            }
        }
        return $html;
    }

    public function search($html, $pattern = null) {
        if ($this->search_pattern && !$pattern) {
            $pattern = $this->search_pattern;
        }
        if (!$pattern) {
            return false;
        }
        $data = array();
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[0] as $index => $match) {
                if (!method_exists($this, 'searchFilter') || $this->searchFilter($match)) {
                    for ($i = 0; $i < count($matches); $i++) {
                        $data[$i][] = $matches[$i][$index];
                    }
                }
            }
        }
        if ($data) {
            $this->buildReplacements($data);
        }
        return $data;
    }

    protected function buildReplacements($matches) {
        $this->replacements = array();
        foreach ($matches[0] as $index => $search) {
            $this->replacements[$search] = array(
                'replace' => $search,
                'data' => array(),
            );
            for ($i = 0; $i < count($matches); $i++) {
                $this->replacements[$search]['data'][$i] = $matches[$i][$index];
            }
        }
    }

    protected function merge() {
        $pagespeed_url = str_replace('/', '\/', wa()->getRouteUrl('pagespeed/frontend'));
        foreach ($this->replacements as &$replacement) {
            if (!$replacement['replace']) {
                continue;
            }
            if (!preg_match("/" . $pagespeed_url . "\?url=([^&]*)&type=([^&]*)/si", $replacement['data']['url'], $params)) {
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
                $this->updateReplacement($replacement);
            }
        }
    }

    protected function combine() {
        if (!$this->merge_list) {
            return false;
        }

        $hash = self::stringHash(serialize($this->merge_list));
        $name = $hash . '.' . $this->type;

        $minify_path = self::getMinifyPath($name, $this->type, $this->settings['gzip']);

        if (!file_exists($minify_path)) {
            if (($handler = @fopen($minify_path, 'w')) === false) {
                throw new waException('Ошибка создания файла: ' . $minify_path);
            }
            $content = '';
            foreach ($this->merge_list as $merge_file) {
                $content .= file_get_contents($merge_file) . ($this->type == 'js' ? ';' : '');
            }
            if ($this->settings['gzip']) {
                $content = gzencode($content, $this->settings['gzip_level'], FORCE_GZIP);
            }

            if (($result = @fwrite($handler, $content)) === false || ($result < strlen($content))) {
                throw new waException('Ошибка записи в файл: ' . $minify_path);
            }
            @fclose($handler);
        }
        return self::makeUrl($name, $this->type);
    }

    protected function getMinifyUrl($url, $type, $is_content = false) {
        $gzip = $this->settings['gzip'] && !$this->settings['merge'];

        if (!$is_content) {
            $local_path = null;
            $new_url = null;

            if ($this->settings['download_remote_files'] && self::isRemoteFile($url)) {
                $download_path = self::getDownloadPath($url, $type);

                $download = true;
                if (file_exists($download_path)) {
                    $expires_time = filemtime($download_path) + $this->settings['update_time_remote_files'];
                    if (time() < $expires_time) {
                        $download = false;
                    }
                }

                if ($download) {
                    $content = self::curlGetContents($url);
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

            if ($local_path || ($local_path = self::isLocalFile($url))) {
                $name = self::fileHash($local_path) . '_' . ($new_url ? $new_url : $url);
                if ($param_ofset = strpos($name, '?')) {
                    $name = substr($name, 0, $param_ofset);
                }
                $minify_path = self::getMinifyPath($name, $type, $gzip);

                if (!file_exists($minify_path)) {
                    self::minify($type, $local_path, $minify_path);
                }
                return self::makeUrl($name, $type);
            }
        } else {
            $content = $url;
            $name = self::stringHash($content) . '.' . $type;
            $minify_path = self::getMinifyPath($name, $type, $gzip);
            if (!file_exists($minify_path)) {
                self::minify($type, $content, $minify_path);
            }
            return self::makeUrl($name, $type);
        }

        return false;
    }

    public static function isRemoteFile($url) {
        if (preg_match('/^(https?:)?\/\/(.+)/i', $url, $matche)) {
            return true;
        }
        return false;
    }

    public static function isLocalFile($url) {
        if ($param_ofset = strpos($url, '?')) {
            $url = substr($url, 0, $param_ofset);
        }
        $local_path = wa()->getConfig()->getRootPath() . '/' . ltrim($url, '/');

        if (file_exists($local_path)) {
            return $local_path;
        }
        return false;
    }

    public static function curlGetContents($url) {
        if (strpos($url, '//') === 0) {
            $url = 'http:' . $url;
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($curl);

        curl_close($curl);
        return $data;
    }

    public static function validFileName($filename) {
        $filename = preg_replace('/^(https?:)?\/\//i', '', $filename);
        $filename = ltrim($filename, '/');
        $filename = preg_replace('/\s+/u', '_', $filename);
        if ($filename) {
            foreach (waLocale::getAll() as $l) {
                $filename = waLocale::transliterate($filename, $l);
            }
        }
        $filename = str_replace('/', '_', $filename);
        $filename = str_replace('\\', '_', $filename);
        $filename = preg_replace('/[^a-zA-Z0-9_\.-]+/', '', $filename);
        return $filename;
    }

    public static function fileHash($filename) {
        return md5_file($filename);
    }

    public static function stringHash($string) {
        return md5($string);
    }

    public static function getMinifyPath($name, $type, $gzip = 0) {
        return self::getPath('minify', $name, $type, $gzip);
    }

    public static function getDownloadPath($name, $type, $gzip = 0) {
        return self::getPath('download', $name, $type, $gzip);
    }

    public static function getPath($dir, $name, $type, $gzip = 0) {
        $name = self::validFileName($name);
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) != $type) {
            $name .= '.' . $type;
        }
        return wa()->getCachePath(sprintf('%s/%s/%s/%s', $dir, $type, ($gzip ? 'gzip' : 'normal'), $name), 'pagespeed');
    }

    protected function minify($type, $source_path_or_content, $minify_path) {
        if ($type == 'css') {
            $minifier = new Minify\CSS($source_path_or_content);
            $minifier->setMaxImportSize($this->settings['max_import_size']);
        } elseif ($type == 'js') {
            $minifier = new Minify\JS($source_path_or_content);
        } else {
            throw new waException('Указан неверный тип файла: ' . $type);
        }

        $gzip = $this->settings['gzip'] && !$this->settings['merge'];
        if ($gzip) {
            $minifier->gzip($minify_path, $this->settings['gzip_level']);
        } else {
            $minifier->minify($minify_path);
        }
    }

    public static function makeUrl($url, $type) {
        $data = array(
            'url' => $url,
            'type' => $type,
        );
        return wa()->getRouteUrl('pagespeed/frontend') . '?' . http_build_query($data);
    }

    public static function log($message) {
        waLog::log($message, 'pagespeed.log');
    }

}
