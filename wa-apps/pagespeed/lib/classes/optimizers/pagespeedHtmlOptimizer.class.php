<?php

class pagespeedHtmlOptimizer extends pagespeedOptimizer {

    public function execute($html) {
        if ($this->settings['minify']) {
            /* $search = array(
              '/\>[^\S ]+/s', // strip whitespaces after tags, except space
              '/[^\S ]+\</s', // strip whitespaces before tags, except space
              '/(\s)+/s', // shorten multiple whitespace sequences
              );
              $replace = array(
              '>',
              '<',
              '\\1',
              );
             */
            $search = array(
                '/>[\s\n]+</s',
                '/>[\s\n]+/s',
                '/[\s\n]+</s',
                //'/\s+/',
            );
            $replace = array(
                '> <',
                '> ',
                ' <',
                //' '
            );
            $html = preg_replace($search, $replace, $html);
        }
        return $html;
    }

    public function removeComments($html) {
        return preg_replace('/<!--(.|\s)*?-->/', '', $html);
    }

    public function gzipEncode($html) {
        return gzencode($html, $this->settings['gzip_level'], FORCE_GZIP);
    }

}
