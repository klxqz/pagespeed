<?php

class pagespeedSettingsAction extends waViewAction {

    public function execute() {
        $this->view->assign('settings', wa('pagespeed')->getConfig()->getSettings());
    }

}
