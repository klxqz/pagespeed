<?php

require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/tinify/src/Exception.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/tinify/src/ResultMeta.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/tinify/src/Result.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/tinify/src/Source.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/tinify/src/Client.php');
require_once(wa()->getConfig()->getRootPath() . '/wa-apps/pagespeed/lib/vendors/tinify/src/Tinify.php');

class pagespeedImageAction extends waViewAction {

    public function execute() {

        $routes = wa()->getRouting()->getRoutes();
        $this->view->assign('routes', $routes);

        wa('shop');
        $class = new pagespeedShopSitemapConfig();
        $class->execute();
        //print_r($class->urls);

        /*
          $img = wa()->getConfig()->getRootPath() . '/wa-data/public/shop/products/03/00/3/images/1/1.200.jpg';
          $optimized = wa()->getConfig()->getRootPath() . '/1.200.jpg';

          Tinify\setKey("SXVx7lHjvlUDWKu4kBCKV6qjQshqbooG");
          Tinify\fromFile($img)->toFile($optimized); */
    }

}
