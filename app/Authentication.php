<?php

Class Authentication extends Controller {

  function Login($f3, $params) {
    // check if user is already logged in
    if ($f3->exists('SESSION.USER'))
      $f3->reroute('@home');

    // check if user has submitted the form; if so, validate their credentials
    if ($f3->VERB == 'POST') {
      $userid = $f3->get('POST.userid');
      $passwd = $f3->get('POST.passwd');
      if ($this->__ValidateCredentials($userid, $passwd) === true) {
        // valid credentials
        $f3->set('SESSION.USER.user_id', $userid);
        $f3->set('SESSION.TOAST.msg', 'Logged in as '.$f3->get('SESSION.USER.user_id'));
        $f3->set('SESSION.TOAST.class', 'success');
        $f3->reroute('@home');
      } else {
        // invalid credentials
        $f3->set('SESSION.TOAST.msg', 'Invalid username and/or password');
        $f3->set('SESSION.TOAST.class', 'error');
      }
    }

    // prompt user for credentials
    $f3->set('PAGE.TITLE', 'Login');
    $f3->set('PAGE.HEADER', 'Login');
    $f3->set('PAGE.CONTENT','login.htm');
    echo \Template::instance()->render('layouts/default.htm');
  }

  function Logout($f3, $params) {
    if ($f3->exists('SESSION.USER')) {
      $f3->clear('SESSION.USER');
      $f3->set('SESSION.TOAST.msg', 'You have been logged out.');
      $f3->set('SESSION.TOAST.class', 'success');
    }
    $f3->reroute('@home');
  }

  private function __ValidateCredentials($userid, $passwd) {
    if (!$userid) return false;
    if (!$passwd) return false;

    $f3 = Base::instance();
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');
    $db_user->load(array('LOWER(user_id)=LOWER(?)',$userid));
    if ($db_user->dry())
      return false;

    if ( password_verify($passwd, $db_user->passwd) ) {
      // account is valid and password is correct
      return true;
    } else {
      return false;
    }

    return false;
  }

}
