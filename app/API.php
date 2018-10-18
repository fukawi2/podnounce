<?php

Class API extends Controller {

  function GetShow($f3,$params) {
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');

    // virtual fields for some additional metadata
    $db_show->episode_count = 'SELECT count(*) FROM episodes WHERE episodes.show_id = shows.show_id';
    $db_show->category_name = 'SELECT category_name FROM categories WHERE categories.category_id = shows.category_id';
    $db_show->category_group = 'SELECT category_group FROM categories WHERE categories.category_id = shows.category_id';
    $db_show->license_name = 'SELECT description FROM licenses WHERE licenses.license_id = shows.license_id';
    $db_show->current_season  = '
      SELECT MAX(season_number) FROM episodes WHERE episodes.show_id = shows.show_id';
    $db_show->current_episode = '
      SELECT MAX(episode_number)
      FROM episodes
      WHERE episodes.show_id = shows.show_id
        AND episodes.season_number = (SELECT MAX(season_number) FROM episodes WHERE episodes.show_id = shows.show_id)';

    // load the database record(s), with or without a filter
    $show_id = $f3->get('GET.id');
    if ($show_id)
      $db_show->load(array('active IS TRUE AND show_id=?', $show_id));
    else
      $db_show->load('active IS TRUE');

    // build the response to the client
    $data = array();
    while (!$db_show->dry()) {
      $data[$db_show->show_id] = $db_show->cast();
      $db_show->next();
    }

    $this->__RenderJSON($data);
  }


  function GetEpisode($f3,$params) {
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');

    // load the database record(s), with or without a filter
    $episode_id = $f3->get('GET.id');
    if ($episode_id)
      $db_episode->load(array('episode_id=?', $episode_id));
    else
      $db_episode->load();

    // build the response to the client
    $data = array();
    while (!$db_episode->dry()) {
      $data[$db_episode->episode_id] = $db_episode->cast();
      $db_episode->next();
    }

    $this->__RenderJSON($data);
  }


  function GetCategory($f3,$params) {
    $db_category = new DB\SQL\Mapper($f3->get('DB'), 'categories');

    // load the database record(s), with or without a filter
    $category_id = $f3->get('GET.id');
    if ($category_id)
      $db_category->load(array('category_id=?', $category_id));
    else
      $db_category->load();

    // build the response to the client
    $data = array();
    while (!$db_category->dry()) {
      $data[$db_category->category_id] = $db_category->cast();
      $db_category->next();
    }

    $this->__RenderJSON($data);
  }


  function GetLicense($f3,$params) {
    $db_license = new DB\SQL\Mapper($f3->get('DB'), 'licenses');

    // load the database record(s), with or without a filter
    $license_id = $f3->get('GET.id');
    if ($license_id)
      $db_license->load(array('license_id=?', $license_id));
    else
      $db_license->load();

    // build the response to the client
    $data = array();
    while (!$db_license->dry()) {
      $data[$db_license->license_id] = $db_license->cast();
      $db_license->next();
    }

    $this->__RenderJSON($data);
  }


  function GetUser($f3,$params) {
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');

    // load the database record(s), with or without a filter
    $user_id = $f3->get('GET.id');
    if ($user_id)
      $db_user->load(array('user_id=?', $user_id));
    else
      $db_user->load();

    // build the response to the client
    $data = array();
    while (!$db_user->dry()) {
      $data[$db_user->user_id] = $db_user->cast();
      unset($data[$db_user->user_id][passwd]);
      $db_user->next();
    }

    $this->__RenderJSON($data);
  }


  private function __RenderJSON($jsonData) {
    header('Content-Type: application/json');
    echo json_encode($jsonData, JSON_PRETTY_PRINT);
  }

}
