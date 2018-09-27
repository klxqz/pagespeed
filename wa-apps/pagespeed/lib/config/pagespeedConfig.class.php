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


            if ($settings['debug_mode']) {
                $debug_ip_list = explode("\n", $settings['debug_ip_list']);
                $debug_user_agents = explode("\n", $settings['debug_user_agent']);

                $user_agent = false;
                foreach ($debug_user_agents as $debug_user_agent) {
                    if (strpos(waRequest::server('HTTP_USER_AGENT'), $debug_user_agent) !== false) {
                        $user_agent = true;
                    }
                }
                $ip = waRequest::server('REMOTE_ADDR');

                if (in_array($ip, $debug_ip_list) || $user_agent) {
                    $settings['status'] = '1';
                } else {
                    $settings['status'] = '0';
                }
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
