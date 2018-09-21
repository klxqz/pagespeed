<?php

class pagespeedConfig extends waAppConfig {

    public function getSettings($field = null) {
        static $settings = array();
        if (!$settings) {
            $settings = wa()->getSetting(null, '', 'pagespeed');
            $default_settings = include(wa('pagespeed')->getConfig()->getAppPath('lib/config/data/default_settings.php'));
            foreach ($default_settings as $key => $value) {
                if (!isset($settings[$key])) {
                    $settings[$key] = $value;
                }
            }

            if (waRequest::server('REMOTE_ADDR') == '188.226.74.251' || strpos(waRequest::server('HTTP_USER_AGENT'), 'Google Page Speed') !== false) {
                //$settings['status'] = '1';
            } else {
                //$settings['status'] = '0';
            }
        }
        if ($field) {
            if (isset($settings[$field])) {
                return $settings[$field];
            } else {
                return wa()->getSetting($field, null, 'pagespeed');
            }
        } else {
            return $settings;
        }
    }

}
