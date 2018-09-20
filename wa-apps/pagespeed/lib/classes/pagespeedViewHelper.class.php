<?php

class pagespeedViewHelper extends waAppViewHelper {

    public function html($content) {
        $pagespeed = new pagespeed();
        return $pagespeed->acceleration($content);
    }

}
