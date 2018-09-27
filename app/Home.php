<?php

Class Home extends Controller {

  function Index($f3,$params) {
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');
    $f3->set('episodes', $db_episode->find(
      array('publish_ts <= current_timestamp'),
      array('order' => 'publish_ts')
    ));

    $f3->set('PAGE.TITLE', 'Home');
    $f3->set('PAGE.HEADER', 'Welcome to '.$f3->get('PACKAGE'));
    $f3->set('PAGE.CONTENT','home.htm');
    echo \Template::instance()->render('layouts/default.htm');
  }

  function Settings($f3,$params) {
    echo 'TODO';
  }

  function Login($f3, $params) {
    // the controller beforeroute() function takes care of doing the
    // authentication, so this just needs to bounce the user back to
    // the home page
    if ($f3->exists('SESSION.USER')) {
      $f3->set('SESSION.TOAST.msg', 'Welcome back '.$f3->get('SESSION.USER.user_id'));
      $f3->set('SESSION.TOAST.class', 'success');
    }
    $f3->reroute('@home');
  }

  function Logout($f3, $params) {
    if ($f3->exists('SESSION.USER')) {
      $f3->clear('SESSION.USER');
      $f3->set('SESSION.TOAST.msg', 'You have been logged out.');
      $f3->set('SESSION.TOAST.class', 'success');
    }
    $f3->reroute('@home');
  }

  function License($f3,$params) {
    $f3->set('PAGE.TITLE', 'License');
    $f3->set('PAGE.HEADER', 'MIT License');
    $f3->set('PAGE.CONTENT','license.htm');
    echo \Template::instance()->render('layouts/default.htm');
  }

}
