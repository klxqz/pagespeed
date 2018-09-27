<?php

class pagespeedHubFrontendHeadHandler extends waEventHandler {

    public function execute(&$params) {
        pagespeed::init();
    }

}
