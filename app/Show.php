<?php

Class Show extends Controller {

  /* main list of the shows we publish. publically accessible
   */
  function Index($f3,$params) {
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    // virtual fields for some additional metadata
    $db_show->episode_count = 'SELECT count(*) FROM episodes WHERE episodes.show_id = shows.show_id';
    $db_show->category_name = 'SELECT category_name FROM categories WHERE categories.category_id = shows.category_id';
    $db_show->category_group = 'SELECT category_group FROM categories WHERE categories.category_id = shows.category_id';

    // get data for display from the database
    $f3->set('shows', $db_show->find(
      array('active IS TRUE'),  // filter
      array('order' => 'title') // sorting
    ));

    $this->RenderPage('show/index.htm', 'Shows', 'Show Directory');
  }


  /* detailed display of show data. publically accessible
   */
  function Display($f3,$params) {
    $show_id = $params['show_id'];
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'episodes');

    /* some virtual fields for showing foreign key data with some nice values
     * instead of keys, as well as some additional metadata about the show
     */
    $db_show->license_description = 'SELECT description FROM licenses WHERE licenses.license_id = shows.license_id';
    $db_show->category_name = 'SELECT category_name FROM categories WHERE categories.category_id = shows.category_id';
    $db_show->category_group = 'SELECT category_group FROM categories WHERE categories.category_id = shows.category_id';
    $db_show->episode_count = 'SELECT count(*) FROM episodes WHERE episodes.show_id = shows.show_id';
    $db_episode->download_count = 'SELECT download_count FROM media WHERE media.media_id = episodes.media_id';

    // query the database and validate the result
    $db_show->load(array('show_id=?', $show_id));
    if ($db_show->dry())
      $f3->error(404, 'Unable to find that show, sorry!');
    $f3->set('show', $db_show->cast());

    // convert the media_id to a url for rendering on the html page output
    $f3->set('cover_art_url', $this->GetMediaURLByID($db_show->cover_art_id));

    // load episode data for this show
    $f3->set('episodes', $db_episode->find(
      array('show_id=?', $show_id),     // filter
      array('order'=>'publish_ts DESC') // sorting
    ));

    $this->RenderPage('show/display.htm', $db_show->title);
  }


  function Edit($f3,$params) {
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $db_category = new DB\SQL\Mapper($f3->get('DB'), 'categories');
    $db_license = new DB\SQL\Mapper($f3->get('DB'), 'licenses');
    $show_id = $params['show_id'];

    // if our request method is POST then we need to create/update a record
    if ($f3->VERB == 'POST')
      $this->__EditPOST($f3,$params);

    if ( empty($show_id) ) {
      // create new record
      $db_show->reset();
      $page_title = 'New Show';
      $page_header = 'Create New Show';
    } else {
      // try and load the requested record
      $db_show->load(array('show_id=?', $show_id));
      if ($db_show->dry())
        $f3->error(404, 'Unable to find that show, sorry!');
      $f3->set('show', $db_show->cast());
      $page_title = 'Edit Show';
      $page_header = sprintf('Editing "%s"',$db_show->title);
    }

    // build arrays for select boxes
    $f3->set('licenses', $db_license->find(
      null, // filter
      array('order'=>'license_id') // sorting
    ));
    $f3->set('categories', $this->FetchCategories());

    $this->RenderPage('show/edit.htm', $page_title, $page_header);
  }

  private function __EditPOST($f3,$params) {
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $show_id = $params['show_id'];

    if ( $show_id ) {
      // try and load the requested record for update
      $db_show->load(array('show_id=?', $show_id));
      if ($db_show->dry())
        $f3->error(404, 'Unable to find that show, sorry!');
    }

    $db_show->title = $this->NullIfEmpty($f3->get('POST.show_title'));
    $db_show->category_id = $this->NullIfEmpty($f3->get('POST.category_id'));
    $db_show->short_description = $this->NullIfEmpty($f3->get('POST.short_description'));
    $db_show->full_description = $this->NullIfEmpty($f3->get('POST.full_description'));
    $db_show->author = $this->NullIfEmpty($f3->get('POST.author'));
    $db_show->license_id = $f3->get('POST.license_id');
    $db_show->explicit = $f3->get('POST.explicit');
    $db_show->title_template = $this->NullIfEmpty($f3->get('POST.title_template'));
    $db_show->summary_template = $this->NullIfEmpty($f3->get('POST.summary_template'));
    $db_show->notes_template = $this->NullIfEmpty($f3->get('POST.notes_template'));
    $cover_art_id = $this->SaveUploadedFile('show_image');
    if ($cover_art_id)
      $db_show->cover_art_id = $cover_art_id;
    $db_show->save();

    $f3->set('SESSION.TOAST.msg', sprintf('"%s" Saved', $db_show->title));
    $f3->set('SESSION.TOAST.class', 'success');
    $f3->reroute('@show_index');
  }

}
