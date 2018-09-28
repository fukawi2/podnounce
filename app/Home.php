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

  function License($f3,$params) {
    $f3->set('PAGE.TITLE', 'License');
    $f3->set('PAGE.HEADER', 'MIT License');
    $f3->set('PAGE.CONTENT','license.htm');
    echo \Template::instance()->render('layouts/default.htm');
  }

}
