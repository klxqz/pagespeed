<?php

class pagespeedSettingsSaveController extends waJsonController {

    public function execute() {
        try {
            $settings = waRequest::post('settings', array(), waRequest::TYPE_ARRAY);
            $app_settings = new waAppSettingsModel();
            foreach ($settings as $setting => $value) {
                $app_settings->set('pagespeed', $setting, $value);
            }
            $this->response['message'] = 'Сохранено';
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
        }
    }

}
