<?php

Class Episode extends Controller {

  /* Display() shows the full details for an individual episode
   * It is publically viewable.
   */
  function Display($f3,$params) {
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $episode_id = $params['episode_id'] ?? null;

    // load database record for the episode
    $db_episode->load(array('episode_id=?',$episode_id));
    if ($db_episode->dry())
      $f3->error(404, 'Episode not found');
    $f3->set('episode', $db_episode->cast());

    // load database record for the associated show
    $db_show->load(array('show_id=?', $db_episode->show_id));
    if ($db_show->dry())
      $f3->error(404, sprintf('Show %s missing', $db_episode->show_id));
    $f3->set('show', $db_show->cast());

    $this->RenderPage('episode/display.htm', $db_episode->title);
  }


  /* Publish() is used by an authenticated user to create a new
   * episode for a show. the POST data from the form is passed
   * to $this->__DoPOST function to process the input and handle
   * database operations.
   */
  function Publish($f3,$params) {
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');

    // if our request method is POST then we need to create/update a record
    if ($f3->VERB == 'POST')
      $this->__DoPOST($f3,$params);

    // make database query to get a list of shows
    $f3->set('shows', $db_show->find(
      array('active IS TRUE'),  // filter
      array('order'=>'title')   // sorting
    ));

    $this->RenderPage('episode/publish.htm', 'Publish', 'Publish New Episode');
  }

  /* Edit() is for editing an existing show. the POST data from the
   * form is passed to $this->__DoPOST function to process the input
   * and handle database operations.
   */
  function Edit($f3,$params) {
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $episode_id = $params['episode_id'];

    // if our request method is POST then we need to create/update a record
    if ($f3->VERB == 'POST')
      $this->__DoPOST($f3,$params);

    // data for the page
    $db_episode->load(array('episode_id=?', $episode_id));
    if ($db_episode->dry())
      $f3->error(404, 'Episode does not exist');
    $f3->set('episode', $db_episode->cast());

    $db_show->current_season  = 'SELECT MAX(season_number) FROM episodes WHERE episodes.show_id = shows.show_id';
    $db_show->current_episode = '
      SELECT MAX(episode_number)
      FROM episodes
      WHERE episodes.show_id = shows.show_id
        AND episodes.season_number = (SELECT MAX(season_number) FROM episodes WHERE episodes.show_id = shows.show_id)';
    $f3->set('shows', $db_show->find(
      array( 'active IS TRUE' ),
      array('order' => 'title')
    ));

    $this->RenderPage('episode/edit.htm', 'Edit '.$db_episode->title);
  }


  /* Deletes an episode from the database, and maybe from the disk
   * if the file is not used by any other media records
   */
  function Delete($f3,$params) {
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');
    $episode_id = $params['episode_id'];

    // load the database record for the episode
    $db_episode->load(array('episode_id=?', $episode_id));
    if ($db_episode->dry())
      $f3->error(404, 'Episode not found');

    /* on first request (GET) we ask the user for confirmation. this then
     * generates a second request (POST) where we do the actual deletion
     */
    switch($f3->VERB) {
    case 'GET':
      $f3->set('episode', $db_episode->cast());
      $this->RenderPage('episode/delete.htm', 'Delete', 'Delete "'.$db_episode->title.'"');
      break;
    case 'POST':
      // Save a couple of pieces of data before erasing so we can use them for the user feedback and rerouting steps.
      $ep_title = $db_episode->title;
      $show_id = $db_episode->show_id;
      $media_id = $db_episode->media_id;
      $db_episode->erase();

      // Delete the media record once the episode is deleted, otherwise we get a fkey constraint error
      $db_media->load(array('media_id=?', $media_id));
      if (!$db_media->dry()) {
        /* check if this is the only record in the media table that points to
         * this file on disk. if so, it is safe to remove it from disk.
         * otherwise we need to leave it on disk for the other media records
         */
        if ($db_media->count(array('fname_on_disk=?', $db_media->fname_on_disk)) == 1) {
          $fname = $f3->get('UPLOADS').$db_media->fname_on_disk;
          if (file_exists($fname))
            unlink($fname);
        }
        $db_media->erase();
      }

      $f3->set('SESSION.TOAST.msg', sprintf('Deleted episode "%s"', $ep_title));
      $f3->set('SESSION.TOAST.class', 'success');
      $f3->reroute("@show_by_id(@show_id=$show_id)");
      break;
    }
  }

  /* Use the Web plugin to send the bianry data of an episode to the remote
   * client. note there is an option to set $throttle to a value grater than
   * zero, in which case the download will be limited to that number of kbps
   */
  function Download($f3,$params) {
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');
    $episode_id = $params['episode_id'];
    $throttle = 0;

    /* $force=true makes the client download the file rather than display it in
     * browser by setting the "Content-Disposition: attachment" HTTP header.
     */
    $force = true;

    // load and validate the episode data from the database
    $db_episode->load(array('episode_id=?', $episode_id));
    if ($db_episode->dry())
      $f3->error(404, 'Episode not found');

    /* load the associated record from the media table. if this does not
     * return and data then we throw a 500 error (instead of 404) because
     * it indicates the database record is invalid as opposed to the client
     * making a bad request.
     */
    $db_media->load(array('media_id=?', $db_episode->media_id));
    if ($db_media->dry())
      $f3->error(500, 'media_id not set');

    // check the file still exists on disk
    $src_filename = $f3->get('UPLOADS').$db_media->fname_on_disk;
    if (!is_file($src_filename))
      $f3->error(404, 'Unable to locate file: '.$src_filename);

    /* send the file to the client, and if successful then increase the
     * download_count value against the db_media record and save it back
     * to the database
     */
    $web = \Web::instance();
    $sent = $web->send($src_filename, NULL, $throttle, true, $db_media->fname_nice);
    if ($sent)  {
      $db_media->download_count++;
      $db_media->save();
    }
  }


  /* handles POST request data for Publish() and Edit() functions above
   */
  private function __DoPOST($f3,$params) {
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');
    $web = \Web::instance();
    $episode_id = $params['episode_id'] ?? '';

    /* validate the supplied data. note that empty() can only test variables
     * prior to php 5.5, which means for compatibility with php 5.4 we have to
     * assign the values of the $f3 methods to temporary variables for use
     * with empty(). the $emsg variable is used for any errors; it is checked
     * after all the validation and if it is not false, we throw an error to
     * the user via a toast box.
     */
    $emsg = false;
    $show_id    = $f3->get('POST.show_id');
    $title      = $f3->get('POST.ep_title');
    $summary    = $f3->get('POST.summary');
    $s_num      = $f3->get('POST.season_number');
    $e_num      = $f3->get('POST.episode_number');
    $show_notes = $f3->get('POST.show_notes');
    if (empty($show_id))  { $emsg = 'You must select a show to publish this episode to.'; }
    if (empty($title))    { $emsg = 'Show title is required.'; }
    if (empty($s_num))    { $emsg = 'Please select a season number.'; }
    if (empty($e_num))    { $emsg = 'Please select an episode number.'; }
    if (empty($summary))  { $emsg = 'Summary is required.'; }
    if (strlen($summary) > 255) { $emsg = 'Summary must be 255 characters or less.'; }
    if (strlen($show_notes) > 4000) { $emsg = 'Show notes must be 4,000 characters or less.'; }

    if ($this->__CountDuplicateEpisodeNumbers($show_id, $s_num, $e_num, $episode_id) > 0)
      $emsg = sprintf('Season %s, Episode %s for this show already exists',
        $f3->get('POST.season_number'), $f3->get('POST.episode_number'));

    /* when creating a new episode, we need to process the uploaded file into
     * our media library. we throwaway the uploaded filename and give it a nice
     * name to match the episode so it looks nice at download time. the slug()
     * function converts foreign characters to their approximate English
     * equivalents, and removes all non-alphanumeric characters (converting
     * them to dashes)
     */
    if (!$episode_id) {
      $fname = sprintf('S%02dE%02d_%s',
        $f3->get('POST.season_number'),
        $f3->get('POST.episode_number'),
        $web->slug($f3->get('POST.ep_title'))
      );
      $media_id = $this->SaveUploadedFile('audio_file', array('audio/mpeg','audio/mp3'), $fname);
      if (!$media_id) { $emsg = 'Error uploading file. Perhaps wrong file type?'; }
    }

    /* if any validation steps above fail, then $emsg is set to the error
     * to show the user. set the toast error message and return without
     * saving the record to the database.
     */
    if ($emsg) {
      $f3->set('SESSION.TOAST.msg', $emsg);
      $f3->set('SESSION.TOAST.class', 'error');
      return false;
    }

    /* validation passed, so now we can insert/update new episode to the
     * database. first we check if we're doing an insert or update, and
     * load() to reset() the $db_episode object appropriately. there are
     * some fields we only set when creating a new record, so we'll set
     * them in here too.
     */
    if ($episode_id) {
      $db_episode->load(array('episode_id=?', $episode_id));
    } else {
      $db_episode->reset();
      $db_episode->guid = $this->uuid();
      $db_episode->media_id = $media_id;
    }
    // set all the object properties to the users data, and save() to the db
    $db_episode->show_id        = $this->NullIfEmpty($f3->get('POST.show_id'));
    $db_episode->title          = $this->NullIfEmpty($f3->get('POST.ep_title'));
    $db_episode->season_number  = $this->NullIfEmpty($f3->get('POST.season_number'));
    $db_episode->episode_number = $this->NullIfEmpty($f3->get('POST.episode_number'));
    $db_episode->summary        = $this->NullIfEmpty($f3->get('POST.summary'));
    $db_episode->explicit       = $this->NullIfEmpty($f3->get('POST.explicit'));
    $db_episode->show_notes     = $this->NullIfEmpty($f3->get('POST.show_notes'));
    $db_episode->created_by     = $f3->get('SESSION.USER.user_d');
    // optional pubish_ts (defaults to now() in the db layer)
    if ($this->NullIfEmpty($f3->get('POST.publish_ts')))
      $db_episode->publish_ts = $f3->get('POST.publish_ts');
    $db_episode->save();

    $f3->set('SESSION.TOAST.msg', sprintf('"%s" Saved', $db_episode->title));
    $f3->set('SESSION.TOAST.class', 'success');
    $f3->reroute("@show_by_id(@show_id=$db_episode->show_id)");
  }


  /* count how many season/episode already exists for a given show and
   * season/episode numberto avoid having duplicate season/episode values for
   * each show. if $episode_id is given then we need to adjust the query to
   * exclude that $episode_id editing otherwise we'll get a false-positive of
   * a duplicate on it's own database record.
   */
  private function __CountDuplicateEpisodeNumbers($show_id, $s_num, $e_num, $episode_id = null) {
    $f3 = Base::instance();
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');

    if ($episode_id) {
      return $db_episode->count(array('show_id=? AND season_number=? AND episode_number=? AND episode_id != ?',
        $show_id, $s_num, $e_num, $episode_id));
    } else {
      return $db_episode->count(array('show_id=? AND season_number=? AND episode_number=?',
        $show_id, $s_num, $e_num));
    }

    return null;
  }

}
