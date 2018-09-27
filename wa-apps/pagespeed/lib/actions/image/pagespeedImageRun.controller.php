<?php

class pagespeedImageRunController extends waLongActionController {

    const STAGE_PRODUCTIMAGES = 'productimages';

    private static $models = array();
    private $extra_connection = array(
        'host' => 'localhost',
        'user' => 'yarovitm_adjss5',
        'password' => 'adj2013',
        'database' => 'yarovitm_adjss5',
        'type' => 'mysqli',
    );

    protected function preExecute() {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
    }

    protected $steps = array(
        self::STAGE_PRODUCTIMAGES => 'Обработка товаров',
    );

    public function execute() {
        try {
            set_error_handler(array($this, 'errHandler'));
            parent::execute();
        } catch (waException $ex) {
            if ($ex->getCode() == '302') {
                echo json_encode(array('warning' => $ex->getMessage()));
            } else {
                echo json_encode(array('error' => $ex->getMessage()));
            }
        }
    }

    public function errHandler($errno, $errmsg, $filename, $linenum) {
        $error_message = sprintf('File %s line %s: %s (%s)', $filename, $linenum, $errmsg, $errno);
        waLog::log($error_message, 'imgimport-errors.log');
    }

    protected function isDone() {
        $done = true;
        foreach ($this->data['processed_count'] as $stage => $done) {
            if (!$done) {
                $done = false;
                break;
            }
        }
        return $done;
    }

    private function getNextStep($current_key) {
        $array_keys = array_keys($this->steps);
        $current_key_index = array_search($current_key, $array_keys);
        if (isset($array_keys[$current_key_index + 1])) {
            return $array_keys[$current_key_index + 1];
        } else {
            return false;
        }
    }

    protected function step() {
        $stage = $this->data['stage'];
        if (!empty($this->data['processed_count'][$stage])) {
            $stage = $this->data['stage'] = $this->getNextStep($this->data['stage']);
        }

        $method_name = 'step' . ucfirst($stage);
        if (method_exists($this, $method_name)) {
            if (isset($this->data['profile_config']['step'][$stage]) && $this->data['profile_config']['step'][$stage] == 0) {
                $this->data['processed_count'][$stage] = 1;
            } else {
                $this->$method_name();
            }
        } else {
            throw new waException('Неизвестный метод ' . $method_name);
        }

        return true;
    }

    protected function finish($filename) {
        $this->info();
        if ($this->getRequest()->post('cleanup')) {
            $profile_id = $this->data['profile_id'];
            $profile_helper = new shopImportexportHelper('imgimport');
            $profile = $profile_helper->getConfig($profile_id);
            $config = $profile['config'];
            $config['last_time'] = $this->data['timestamp'];
            $profile_helper->setConfig($config, $profile_id);
            return true;
        }
        return false;
    }

    protected function report() {
        $report = '<div class="successmsg"><i class="icon16 yes"></i>';
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' ' . sprintf(_w('(total time: %s)'), $interval);
        }
        $report .= '&nbsp;</div>';
        return $report;
    }

    protected function info() {

        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
        }
        $stage = $this->data['stage'];
        $response = array(
            'time' => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId' => $this->processId,
            'progress' => 0.0,
            'ready' => $this->isDone(),
            'offset' => $this->data['current'][$stage],
            'count' => $this->data['count'][$stage],
            'stage_name' => $this->steps[$this->data['stage']] . ' - ' . $this->data['current'][$stage] . ($this->data['count'][$stage] ? ' из ' . $this->data['count'][$stage] : ''),
            'memory' => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );

        if ($this->data['count'][$stage]) {
            $response['progress'] = ($this->data['current'][$stage] / $this->data['count'][$stage]) * 100;
        }

        $response['progress'] = sprintf('%0.3f%%', $response['progress']);

        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }

        echo json_encode($response);
    }

    protected function restore() {
        
    }

    protected function init() {
        try {


            $this->data['timestamp'] = time();

            $stages = array_keys($this->steps);

            $this->data['item_ids'] = array();
            $this->data['mount_items'] = array();
            $this->data['count'] = array_fill_keys($stages, 0);

            //$product_model = new shopProductModel();
            $this->data['count'][self::STAGE_PRODUCTIMAGES] = 10;//$product_model->select('id')->query()->count();
           


            $this->data['current'] = array_fill_keys($stages, 0);
            $this->data['processed_count'] = array_fill_keys($stages, 0);
            $this->data['stage'] = reset($stages);

            $this->data['error'] = null;
            $this->data['stage_name'] = $this->steps[$this->data['stage']];
            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();
        } catch (waException $ex) {
            echo json_encode(array('error' => $ex->getMessage(),));
            exit;
        }
    }

    public function stepProductimages() {
        $product_model = $this->getModel('Product');
        $product_ids = $product_model->select('id')->fetchAll();
        if ($product_ids) {
            $product_id = $product_ids[$this->data['current'][self::STAGE_PRODUCTIMAGES]];
            $product_images_model = $this->getModel('ProductImages');
            $images = $product_images_model->getByField('product_id', $product_id);
            if ($images) {
                foreach ($images as $image) {
//$product_images_model->delete($image['id']);
                }
            }

            $sku_model = $this->getModel('ProductSkus');
            $sku = $sku_model->getByField('product_id', $product_id);






            $this->data['current'][self::STAGE_PRODUCTIMAGES] ++;
        }



        if ($this->data['current'][self::STAGE_PRODUCTIMAGES] >= $this->data['count'][self::STAGE_PRODUCTIMAGES]) {
            $this->data['processed_count'][self::STAGE_PRODUCTIMAGES] = 1;
        }
    }

    public function getModel($name) {
        $model_name = sprintf('shop%sModel', $name);
        if (!class_exists($model_name)) {
            throw new waException(sprintf('Модель %s не найдена', $model_name));
        }
        if (!isset(self::$models[$model_name])) {
            self::$models[$model_name] = new $model_name();
        }
        return self::$models[$model_name];
    }

}
