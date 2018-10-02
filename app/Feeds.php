<?php

Class Feeds extends Controller {

  function ByShow($f3,$params) {
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'show_feed');
    $show_id = $params['show_id'];

    // create some virtual fields
    $db_show->category_name = 'SELECT category_name FROM categories WHERE categories.category_id = shows.category_id';
    $db_show->category_group = 'SELECT category_group FROM categories WHERE categories.category_id = shows.category_id';

    // load show data from database
    $db_show->load(array('show_id=?', $show_id));
    if ($db_show->dry())
      $f3->error(404, 'Unable to find that show, sorry!');
    $f3->set('show', $db_show->cast());

    // load episodes
    if ($db_episode->count(array('show_id=?', $show_id)) == 0)
      $f3->error(503, 'No episodes for this show.');
    $f3->set('episodes', $db_episode->find(
      array('show_id = ?', $show_id),
      array('order' => 'publish_ts DESC')
    ));

    $this->__ValidateEpisodeData($f3->get('episodes'));

    echo \Template::instance()->render('feed.xml', 'application/xml');
  }



  function Firehose($f3,$params) {
    $db_firehose_feed = new DB\SQL\Mapper($f3->get('DB'), 'firehose_feed');

    /* get the network category using virtual fields; yeah, this is a little
     * nasty randomly coercing the value column to an integer but it works
     */
    $db_settings = new DB\SQL\Mapper($f3->get('DB'), 'settings');
    $db_settings->category_name =
      'SELECT category_name FROM categories WHERE categories.category_id = settings.value::int';
    $db_settings->category_group =
      'SELECT category_group FROM categories WHERE categories.category_id = settings.value::int';
    $db_settings->load(array('setting=?', 'network_category'));

    $metashow = array(
      'title' => $f3->get('NETWORK_NAME'),
      'author' => $f3->get('NETWORK_NAME'),
      'short_description ' => 'Aggregation of pocasts from our network.',
      'explicit' => true,
      'image_fname' => '',  // TODO
      'category_name' => $db_settings->category_name,
      'category_group' => $db_settings->category_group,
    );
    $f3->set('show', $metashow);

    // load episodes
    if ($db_firehose_feed->count() == 0)
      $f3->error(503, 'No episodes available.');
    $f3->set('episodes', $db_firehose_feed->find(
      null,
      array('order' => 'publish_ts DESC')
    ));

    $this->__ValidateEpisodeData($f3->get('episodes'));

    echo \Template::instance()->render('feed.xml', 'application/xml');
  }


  /* basic sanity checks of the data. these should never fail in healthy
   * system, but to avoid generating a broken feed in the event something
   * is wrong, we'll check and return a HTTP error rather than silently
   * distrubuting a faulty rss feed.
   */
  private function __ValidateEpisodeData($episodes) {
    $f3 = Base::instance();
    foreach ($episodes as $item) {
      $ep_id = $item['episode_id'];
      if (empty($item['mime_type']))      $f3->error(503, sprintf('Episode %s missing mime_type', $ep_id));
      if (empty($item['media_bytes']))    $f3->error(503, sprintf('Episode %s missing media_bytes', $ep_id));
      if (empty($item['duration']))       $f3->error(503, sprintf('Episode %s missing duration', $ep_id));
      if (empty($item['guid']))           $f3->error(503, sprintf('Episode %s missing guid', $ep_id));
    }
    return true;
  }

}
