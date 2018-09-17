<?php

class pagespeedViewHelper extends waAppViewHelper {

    public function html($html) {
        $pagespeed = new pagespeed();
        return $pagespeed->acceleration($html);
    }

    protected function minifyJS($html) {
        $this->log('minifyJS');
        $dom = new simple_html_dom();
        $dom->load($html, $lowercase = true, $stripRN = false, $defaultBRText = DEFAULT_BR_TEXT);
        $scripts = $dom->find('script');

        foreach ($scripts as $script) {
            if (!isset($script->src)) {
                continue;
            }
            $this->log('Обработка JS: ' . $script->src);

            //удаленные файлы
            if (preg_match('/(https?:)?\/\/(.+)/i', $script->src, $matche)) {
                
            } else {
                
            }

            $url_info = parse_url($script->src);
            $local_path = wa()->getConfig()->getRootPath() . '/' . ltrim($url_info['path'], '/');
            echo $script->src . "\n";
            print_r($url_info);
            if (isset($url_info['host'])) {
                print_r($url_info);
            } elseif (file_exists($local_path)) {
                $name = basename($local_path);
                $minifier = new Minify\JS($local_path);
                $minifier->minify(wa()->getDataPath($name, true, 'pagespeed'));
                $script->src = wa()->getDataUrl($name, true, 'pagespeed');
            }
        }

        return $dom->save();
    }

    protected function minifyCssInOne($html) {
        $this->log('minifyCssInOne');
        $dom = new simple_html_dom();
        $dom->load($html, $lowercase = true, $stripRN = false, $defaultBRText = DEFAULT_BR_TEXT);
        $links = $dom->find('link');

        $minifier = new Minify\CSS();
        $minifier->setMaxImportSize(5);
        foreach ($links as $link) {
            if (!(
                    (isset($link->type) && $link->type == 'text/css') ||
                    (isset($link->rel) && $link->rel == 'stylesheet') ||
                    (isset($link->property) && $link->property == 'stylesheet')
                    )) {
                continue;
            }
            $this->log('Обработка CSS: ' . $link->href);
            $url_info = parse_url($link->href);
            $local_path = wa()->getConfig()->getRootPath() . '/' . ltrim($url_info['path'], '/');

            if (isset($url_info['host'])) {
                $content = pagespeedHelper::curlGetContents($link->href);
                $minifier->add($content);
                $link->outertext = '';
            } elseif (file_exists($local_path)) {
                $minifier->add($local_path);
                $link->outertext = '';
            }
        }


        $styles = $dom->find('style');
        foreach ($styles as $style) {
            $minifier->add($style->innertext);
            $style->outertext = '';
        }

        $name = 'styles.min.css';
        $minifier->minify(wa()->getDataPath($name, true, 'pagespeed'));
        $dom->find('head', 0)->innertext .= '<link href="' . wa()->getDataUrl($name, true, 'pagespeed') . '" rel="stylesheet" type="text/css"/>';


        return $dom->save();
    }

    protected function minifyCss($html) {
        $this->log('minifyCss');
        $dom = new simple_html_dom();
        $dom->load($html, $lowercase = true, $stripRN = false, $defaultBRText = DEFAULT_BR_TEXT);
        $links = $dom->find('link');

        foreach ($links as $link) {
            if (!(
                    (isset($link->type) && $link->type == 'text/css') ||
                    (isset($link->rel) && $link->rel == 'stylesheet') ||
                    (isset($link->property) && $link->property == 'stylesheet')
                    )) {
                continue;
            }
            $this->log('Обработка CSS: ' . $link->href);
            $url_info = parse_url($link->href);
            $local_path = wa()->getConfig()->getRootPath() . '/' . ltrim($url_info['path'], '/');

            if (isset($url_info['host'])) {
                $content = pagespeedHelper::curlGetContents($link->href);
                $minifier = new Minify\CSS($content);
                $minifier->setMaxImportSize(5);
                $name = $url_info['host'] . '_' . $url_info['path'] . '_' . $url_info['query'];
                $name = pagespeedHelper::validFileName($name);
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) != 'css') {
                    $name .= '.css';
                }
                $minifier->minify(wa()->getDataPath($name, true, 'pagespeed'));
                $link->href = wa()->getDataUrl($name, true, 'pagespeed');
            } elseif (file_exists($local_path)) {
                $name = basename($local_path);
                $minifier = new Minify\CSS($local_path);
                $minifier->setMaxImportSize(5);
                $minifier->minify(wa()->getDataPath($name, true, 'pagespeed'));
                $link->href = wa()->getDataUrl($name, true, 'pagespeed');
            }
        }

        $styles = $dom->find('style');
        if (!empty($styles)) {
            $minifier = new Minify\CSS();
            $minifier->setMaxImportSize(5);
            foreach ($styles as $style) {
                $minifier->add($style->innertext);
                $style->outertext = '';
            }
            $name = 'inline.css';
            $minifier->minify(wa()->getDataPath($name, true, 'pagespeed'));
            $dom->find('body', 0)->innertext .= '<link href="' . wa()->getDataUrl($name, true, 'pagespeed') . '" rel="stylesheet" type="text/css"/>';
        }

        return $dom->save();
    }

    public function log($message) {
        waLog::log($message, 'pagespeed.log');
    }

}
