<?php

class pagespeed {

    protected $settings;
    protected $optimizers;
    protected static $inited = false;

    public function __construct() {
        $this->settings = wa('pagespeed')->getConfig()->getSettings();
        $this->optimizers = new pagespeedOptimizers($this->settings);
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

        $html = $this->optimizers->html->removeComments($html);
        if ($this->settings['debug_mode']) {
            $finish_remove_comments = microtime(true) - $start;
        }

        $this->optimizers->img->execute($html);
        if ($this->settings['debug_mode']) {
            $finish_img = microtime(true) - $start;
        }

        $matches = $this->optimizers->search($html);
        $cache_id = md5(serialize($matches));

        $cache_time = wa()->getConfig()->isDebug() ? 0 : 7200;
        $cache = new waSerializeCache($cache_id, $cache_time, 'pagespeed');

        if ($cache && $cache->isCached()) {
            $this->optimizers->setCacheResult($cache->get());
        } else {
            $this->optimizers->css->execute();
            if ($this->settings['debug_mode']) {
                $finish_css = microtime(true) - $start;
            }

            $this->optimizers->js->execute();
            if ($this->settings['debug_mode']) {
                $finish_js = microtime(true) - $start;
            }

            if ($cache) {
                $cache->set($this->optimizers->getCacheResult());
            }
        }


        $html = $this->optimizers->replace($html);

        $html = $this->optimizers->html->execute($html);
        if ($this->settings['debug_mode']) {
            $finish_html = microtime(true) - $start;
        }

        if ($this->settings['debug_mode']) {
            $finish = microtime(true) - $start;

            $html .= "\n<!-- pagespeed-comment-time:    {$finish_remove_comments}сек. -->";
            $html .= "\n<!-- pagespeed-css-time:        " . ifset($finish_css) . "сек.   (" . (ifset($finish_css) - $finish_remove_comments) . "сек.) -->";
            $html .= "\n<!-- pagespeed-img-time:        " . $finish_img . "сек.   (" . ($finish_img - ifset($finish_css)) . "сек.) -->";
            $html .= "\n<!-- pagespeed-js-time:         " . ifset($finish_js) . "сек.   (" . (ifset($finish_js) - $finish_img) . "сек.) -->";
            $html .= "\n<!-- pagespeed-html-time:       " . $finish_html . "сек.   (" . ($finish_html - ifset($finish_js)) . "сек.)  -->";
            $html .= "\n<!-- pagespeed-time:            {$finish}сек. -->";
        }


        if ($this->settings['html_gzip']) {
            $html = $this->optimizers->html->gzipEncode($html);
        }

        return $html;
    }

}
