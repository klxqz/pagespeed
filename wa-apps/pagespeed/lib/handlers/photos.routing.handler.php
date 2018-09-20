<?php

class pagespeedPhotosRoutingHandler extends waEventHandler {

    public function execute(&$params) {
        pagespeed::init();
    }

}
