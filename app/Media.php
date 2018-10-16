<?php

Class Media extends Controller {

  function Index($f3,$params) {
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');
    // virtual fields for some additional metadata
//    $db_media->episode_id = 'SELECT episode_id FROM episodes WHERE media.media_id = episodes.media_id';
//    $db_media->show_id = 'SELECT show_id FROM episodes WHERE media.media_id = episodes.media_id';

    // get data for display from the database
    $f3->set('media', $db_media->find());

    $this->RenderPage('media/index.htm', 'Media Index');
  }

}
