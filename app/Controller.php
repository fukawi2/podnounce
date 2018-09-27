<?php

class Controller {

  function beforeRoute($f3,$params) {
    // initialize the session dbms handler
    $session = new DB\SQL\Session($f3->get('DB'));

    // check csrf tokens
    if (!$f3->exists('SESSION.csrf_token'))
      $f3->set('SESSION.csrf_token', $session->csrf());

    // check user authentication
    if (preg_match('|^/admin|', $f3->get('PATTERN'))) {
      if (!$f3->exists('SESSION.USER'))
        $this->Authenticate();
    }

  }

  function afterRoute($f3,$params) {
    $f3->clear('SESSION.TOAST');
  }

  public function NullIfEmpty($str) {
    return (!empty($str) ? $str : null);
  }

  /*
   * user authentication functions
   */
  function Authenticate() {
    $f3 = Base::instance();
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');
    $auth = new \Auth($db_user, array('id'=>'user_id', 'pw'=>'passwd'));
    // TODO: proper authentication, with password hashes!
    if ($auth->basic()) {
      $f3->set('SESSION.USER', $db_user->cast());
      return true;
    }
    return false;
  }

}
