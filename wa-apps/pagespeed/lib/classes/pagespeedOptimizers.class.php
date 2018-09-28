<?php

class pagespeedOptimizers {

    protected static $optimizers = array();
    protected $settings = array();

    public function __construct($settings) {
        $this->settings = $settings;
    }

    public function __get($optimizer) {
        $optimizer = strtolower($optimizer);
        if (!isset(self::$optimizers[$optimizer])) {
            $class_name = sprintf('pagespeed%sOptimizer', ucfirst($optimizer));
            if (class_exists($class_name)) {
                $settings = array();
                foreach ($this->settings as $setting => $value) {
                    if (strpos($setting, $optimizer . '_') === 0) {
                        $settings[str_replace($optimizer . '_', '', $setting)] = $value;
                    }
                }
                self::$optimizers[$optimizer] = new $class_name($settings);
            }
        }

        return self::$optimizers[$optimizer];
    }

}
