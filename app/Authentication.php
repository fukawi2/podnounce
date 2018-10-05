<?php

Class Authentication extends Controller {

  /* Validates user credentials to grant/deny access to the password
   * protected sections of the site
   */
  function Login($f3, $params) {
    // check if user is already logged in
    if ($f3->exists('SESSION.USER'))
      $f3->reroute('@home');

    // check if user has submitted the form; if so, validate their credentials
    switch($f3->VERB) {
    case 'GET':
      break;
    case 'POST':
      $username = $f3->get('POST.username');
      $passwd = $f3->get('POST.passwd');
      if ($this->__ValidateCredentials($username, $passwd) === true) {
        // valid credentials
        $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');
        $db_user->load(array('LOWER(username)=LOWER(?)',$username));
        $db_user->last_login_ts = date('Y-m-d G:i:s', $f3->get('sess')->stamp());
        $db_user->last_login_ip = $f3->get('sess')->ip();
        $db_user->save();
        $f3->set('SESSION.USER', $db_user->cast());
        $f3->set('SESSION.TOAST.msg', 'Logged in as '.$f3->get('SESSION.USER.username'));
        $f3->set('SESSION.TOAST.class', 'success');
        $f3->reroute('@home');
      } else {
        // invalid credentials
        $f3->set('SESSION.TOAST.msg', 'Invalid username and/or password');
        $f3->set('SESSION.TOAST.class', 'error');
      }
      break;
    }

    $this->RenderPage('login.htm', 'Login');
  }


  function Logout($f3, $params) {
    if ($f3->exists('SESSION.USER')) {
      $f3->clear('SESSION.USER');
      $f3->set('SESSION.TOAST.msg', 'You have been logged out.');
      $f3->set('SESSION.TOAST.class', 'success');
    }
    $f3->reroute('@home');
  }


  /* Validates a given username/password against the database `users` table
   * Returns true or false to indicate if the given credentials should be
   * granted access or not. Additional checks may go here in future, such as
   * account enabled/disabled, date/time checks etc
   */
  private function __ValidateCredentials($username, $passwd) {
    if (!$username) return false;
    if (!$passwd) return false;

    $f3 = Base::instance();
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');
    $db_user->load(array('LOWER(username)=LOWER(?)',$username));
    if ($db_user->dry())
      return false;

    if ( password_verify($passwd, $db_user->passwd) )
      return true;

    return false;
  }

}
