<?php

Class Install extends Controller {

  private $setting_keys = array(
    'network_name' => true,
    'admin_name' => true,
    'admin_email' => true,
    'canonical_url' => true,
    'intro_text' => false
  );

  function Main($f3,$params) {
    // check if we are already installed
    $db_setting = new DB\SQL\Mapper($f3->get('DB'), 'settings');
    if ($db_setting->count() != 0)
      $f3->reroute('@home');

    if ($f3->VERB == 'POST')
      $this->__DoInstall($f3,$params);

    $f3->set('PAGE.TITLE', 'Install');
    $f3->set('PAGE.HEADER', 'Install '.$f3->get('PACKAGE'));
    $f3->set('PAGE.CONTENT','install.htm');
    echo \Template::instance()->render('layouts/default.htm');
  }

  private function __DoInstall($f3,$params) {
    $db_setting = new DB\SQL\Mapper($f3->get('DB'), 'settings');
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');

    // validate user input
    foreach ($this->setting_keys as $key => $required) {
      $value = $this->NullIfEmpty($f3->get('POST.'.$key));
      if ($required and empty($value)) {
        $f3->set('SESSION.TOAST.msg', $passwd_quality);
        $f3->set('SESSION.TOAST.class', 'error');
        return false;
      }
    }
    $userid = $f3->get('POST.userid');
    $passwd1 = $f3->get('POST.passwd1');
    $passwd2 = $f3->get('POST.passwd2');
    $passwd_quality = $this->CheckPasswordQuality($passwd1, $passwd2);
    if ($passwd_quality !== true) {
      // password valied quality checks
      $f3->set('SESSION.TOAST.msg', $passwd_quality);
      $f3->set('SESSION.TOAST.class', 'error');
      return false;
    }

    // save settings to the database
    foreach ($this->setting_keys as $key => $required) {
      $value = $f3->get('POST.'.$key);
      $db_setting->setting = $key;
      $db_setting->value = $value;
      $db_setting->save();
      $db_setting->reset();
    }

    // create the first user
    $db_user->reset();
    $db_user->user_id = $userid;
    $db_user->passwd = password_hash($passwd1, PASSWORD_DEFAULT);
    $db_user->save();

    $f3->set('SESSION.TOAST.msg', 'Installation Complete!');
    $f3->set('SESSION.TOAST.class', 'success');
    $f3->reroute('@home');
  }

}
