<?php

Class Settings extends Controller {

  function Main($f3,$params) {
    $db_setting = new DB\SQL\Mapper($f3->get('DB'), 'settings');

    if ($f3->VERB == 'POST')
      $this->__MainPOST($f3,$params);

    // retrieve settings from database
    foreach ($this->setting_keys as $key => $required) {
      $db_setting->load(array('setting=?',$key));
      $f3->set('SETTINGS.'.$key, trim($db_setting->value));
    }

    $f3->set('categories', $this->FetchCategories());

    $this->RenderPage('settings.htm', 'Settings');
  }


  private function __MainPOST($f3,$params) {
    $db_setting = new DB\SQL\Mapper($f3->get('DB'), 'settings');
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');

    // validate user input
    foreach ($this->setting_keys as $key => $required) {
      $value = $this->NullIfEmpty($f3->get('POST.'.$key));
      if ($required and empty($value)) {
        $f3->set('SESSION.TOAST.msg', $key.' is required');
        $f3->set('SESSION.TOAST.class', 'error');
        return false;
      }
    }

    // save settings to the database
    foreach ($this->setting_keys as $key => $required) {
      // load first to avoid duplicate keys if record already exists
      $db_setting->load(array('setting=?',$key));
      $value = $f3->get('POST.'.$key);
      $db_setting->setting = $key;
      $db_setting->value = $value;
      $db_setting->save();
      $db_setting->reset();
    }
    $media_id = $this->SaveUploadedFile('network_logo', 'image/*');
    if ($media_id) {
      // load first to avoid duplicate keys if record already exists
      $db_setting->load(array('setting=?','network_logo'));
      $db_setting->setting = 'network_logo';
      $db_setting->value = $media_id;
      $db_setting->save();
      $db_setting->reset();
    }

    $f3->set('SESSION.TOAST.msg', 'Settings Updated.');
    $f3->set('SESSION.TOAST.class', 'success');
  }
}
