<?php

class pagespeed {

    protected $settings;
    protected $optimizer;
    protected static $inited = false;

    public function __construct() {
        $this->settings = wa('pagespeed')->getConfig()->getSettings();
        $this->optimizer = new pagespeedOptimizers($this->settings);
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

        $html = $this->optimizer->html->removeComments($html);
        if ($this->settings['debug_mode']) {
            $finish_remove_comments = microtime(true) - $start;
        }

        $html = $this->optimizer->css->execute($html);
        if ($this->settings['debug_mode']) {
            $finish_css = microtime(true) - $start;
        }
        $html = $this->optimizer->img->execute($html);
        if ($this->settings['debug_mode']) {
            $finish_img = microtime(true) - $start;
        }
        $html = $this->optimizer->js->execute($html);
        if ($this->settings['debug_mode']) {
            $finish_js = microtime(true) - $start;
        }
        $html = $this->optimizer->html->execute($html);
        if ($this->settings['debug_mode']) {
            $finish_html = microtime(true) - $start;
        }

        if ($this->settings['debug_mode']) {
            $finish = microtime(true) - $start;

            $html .= "\n<!-- pagespeed-comment-time:    {$finish_remove_comments}сек. -->";
            $html .= "\n<!-- pagespeed-css-time:        " . $finish_css . "сек.   (" . ($finish_css - $finish_remove_comments) . "сек.) -->";
            $html .= "\n<!-- pagespeed-img-time:        " . $finish_img . "сек.   (" . ($finish_img - $finish_css) . "сек.) -->";
            $html .= "\n<!-- pagespeed-js-time:         " . $finish_js . "сек.   (" . ($finish_js - $finish_img) . "сек.) -->";
            $html .= "\n<!-- pagespeed-html-time:       " . $finish_html . "сек.   (" . ($finish_html - $finish_js) . "сек.)  -->";
            $html .= "\n<!-- pagespeed-time:            {$finish}сек. -->";
        }


        if ($this->settings['html_gzip']) {
            $html = $this->optimizer->html->gzipEncode($html);
        }

        return $html;
    }

}
