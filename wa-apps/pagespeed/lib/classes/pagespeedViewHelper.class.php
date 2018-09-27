<?php

class pagespeedViewHelper extends waAppViewHelper {

    public function isEnabled() {
        return wa('pagespeed')->getConfig()->getSettings('status');
    }

    public function html($content) {
        $pagespeed = new pagespeed();
        return $pagespeed->acceleration($content);
    }

    public function addEncodingHeader() {
        $response = wa()->getResponse();
        $response->addHeader("Content-Encoding", "gzip");
    }

}
