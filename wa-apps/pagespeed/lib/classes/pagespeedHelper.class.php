<?php

class pagespeedHelper {

    public function isRemoteFile($url) {
        if (preg_match('/^(https?:)?\/\/(.+)/i', $url, $matche)) {
            return true;
        }
        return false;
    }

    public function isLocalFile($url) {
        if ($param_ofset = strpos($url, '?')) {
            $url = substr($url, 0, $param_ofset);
        }
        $local_path = wa()->getConfig()->getRootPath() . '/' . ltrim($url, '/');

        if (is_readable($local_path)) {
            return $local_path;
        }
        return false;
    }

    public function curlGetContents($url) {
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

    public function validFileName($filename) {
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

    public function fileHash($filename) {
        return md5_file($filename);
    }

    public function stringHash($string) {
        return md5($string);
    }

    public function getMinifyPath($name, $type, $css_gzip = 0) {
        $name = $this->validFileName($name);
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) != $type) {
            $name .= '.' . $type;
        }
        if ($css_gzip) {
            $short_path = $type . '/gzip/' . $name;
        } else {
            $short_path = $type . '/normal/' . $name;
        }
        return wa()->getCachePath($short_path, 'pagespeed');
    }

}
