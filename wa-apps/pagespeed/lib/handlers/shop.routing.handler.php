<?php

class pagespeedShopRoutingHandler extends waEventHandler {

    public function execute(&$params) {
        pagespeed::init();
    }

}
