<?php

class pagespeedSiteRoutingHandler extends waEventHandler {

    public function execute(&$params) {
        pagespeed::init();
    }

}
