<?php

Class Home extends Controller {

  private $ep_display_count_default = 3;

  function Index($f3,$params) {
    $db_setting = new DB\SQL\Mapper($f3->get('DB'), 'settings');
    $db_setting->load(array('setting=?','intro_text'));
    $f3->set('intro', trim($db_setting->value));

    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'firehose_feed');
    $db_setting->load(array('setting=?','ep_display_count'));
    $limit = ($db_setting->dry() ? $this->ep_display_count_default : $db_setting->value);
    $f3->set('episodes', $db_episode->find(
      array('publish_ts <= current_timestamp'),
      array('order' => 'publish_ts DESC, episode_id DESC', 'limit' => $limit)
    ));

    $f3->set('show_network_logo', true);
    $this->RenderPage('home.htm', 'Home', $f3->get('SETTINGS.network_name'));
  }

  function License($f3,$params) {
    $this->RenderPage('license.htm', 'License', 'MIT License');
  }

}
