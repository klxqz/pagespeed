<?php

class pagespeedBlogRoutingHandler extends waEventHandler {

    public function execute(&$params) {
        pagespeed::init();
    }

}
