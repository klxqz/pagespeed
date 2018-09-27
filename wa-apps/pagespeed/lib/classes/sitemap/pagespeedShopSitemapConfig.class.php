<?php

class pagespeedShopSitemapConfig extends shopSitemapConfig {

    public $urls;

    public function addUrl($loc, $lastmod, $changefreq = null, $priority = null) {
        $this->urls[] = $loc;
    }

    public function execute($n = 1) {

        $app = wa()->getApp();
        wa()->setActive('shop');
        parent::execute($n);
        wa()->setActive($app);
    }

}
