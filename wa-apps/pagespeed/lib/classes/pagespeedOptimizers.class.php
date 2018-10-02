<?php

class pagespeedOptimizers {

    protected static $optimizers = array();
    protected $settings = array();

    public function __construct($settings) {
        $this->settings = $settings;
        $this->getOptimizers();
    }

    public function __get($optimizer) {
        $optimizer = strtolower($optimizer);
        if (!isset(self::$optimizers[$optimizer])) {
            throw new waException('Указан оптимизатор неверного типа: ' . $optimizer);
        }
        return self::$optimizers[$optimizer];
    }

    protected function getOptimizers() {
        if (empty(self::$optimizers)) {
            $optimizers_dir = __DIR__ . '/optimizers/';
            $files = glob($optimizers_dir . 'pagespeed*Optimizer.class.php');
            foreach ($files as $file) {
                $class_name = basename($file, ".class.php");
                if (!preg_match("/pagespeed(.*)Optimizer/si", $class_name, $match)) {
                    throw new waException('Не определен тип класса: ' . $class_name);
                }
                $optimizer_type = strtolower($match[1]);

                $settings = array();
                foreach ($this->settings as $setting => $value) {
                    if (strpos($setting, $optimizer_type . '_') === 0) {
                        $settings[str_replace($optimizer_type . '_', '', $setting)] = $value;
                    }
                }

                $optimizer = new $class_name($settings);
                if ($optimizer instanceof pagespeedOptimizer) {
                    self::$optimizers[$optimizer_type] = $optimizer;
                }
            }
        }
        return self::$optimizers;
    }

    public function search($html) {
        $replacements = array();
        foreach ($this->getOptimizers() as $optimizer) {
            if ($result = $optimizer->search($html)) {
                $replacements[$optimizer->getType()] = $result;
            }
        }
        return $replacements;
    }

    public function replace($html) {
        foreach ($this->getOptimizers() as $optimizer) {
            $html = $optimizer->replace($html);
        }

        foreach (pagespeedOptimizer::getAppends() as $append) {
            switch ($append['place']) {
                case pagespeedOptimizer::HEAD_OPEN:
                    $html = preg_replace("/(<head\b[^>]*>)/is", "$1\n{$append['html']}\n", $html);
                    break;
                case pagespeedOptimizer::HEAD_CLOSE:
                    $html = preg_replace("/(<\/head>)/is", "\n{$append['html']}\n$1", $html);
                    break;
                case pagespeedOptimizer::BODY_OPEN:
                    $html = preg_replace("/(<body\b[^>]*>)/is", "$1\n{$append['html']}\n", $html);
                    break;
                case pagespeedOptimizer::BODY_CLOSE:
                    $html = preg_replace("/(<\/body>)/is", "\n{$append['html']}\n$1", $html);
                    break;
            }
        }
        return $html;
    }

}
