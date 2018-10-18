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

    $show_id = $params['show_id'];
    $db_show->load(array('show_id=?', $show_id));
    if ($db_show->dry())
      $f3->error(404, 'No such show: '.$show_id);

    header('Content-Type: application/json');
    echo json_encode($db_show->cast(), JSON_PRETTY_PRINT);

  }

  function GetShows($f3,$params) {
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

    $db_show->load();
    $data = array();
    while (!$db_show->dry()) {
      $data[$db_show->show_id] = $db_show->cast();
      $db_show->next();
    }

    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);

  }

}
