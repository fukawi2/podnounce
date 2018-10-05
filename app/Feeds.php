<?php

Class Feeds extends Controller {

  /* Generate an rss feed for a specific show. note the use of 'show_feed' view
   * in the database to retrieve the episode data to avoid a bunch of expensive
   * virtual fields to join the `media` table for each episode.
   */
  function ByShow($f3,$params) {
    $db_show = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'show_feed');
    $show_id = $params['show_id'];

    /* create some virtual fields to map foreign keys in the database
     * to proper values to include in the feed.
     */
    $db_show->category_name = 'SELECT category_name FROM categories WHERE categories.category_id = shows.category_id';
    $db_show->category_group = 'SELECT category_group FROM categories WHERE categories.category_id = shows.category_id';

    // load show data from database
    $db_show->load(array('show_id=?', $show_id));
    if ($db_show->dry())
      $f3->error(404, 'Unable to find that show, sorry!');
    $f3->set('show', $db_show->cast());

    // load episodes from database
    if ($db_episode->count(array('show_id=?', $show_id)) == 0)
      $f3->error(204, 'No episodes available.'); // HTTP 204 = "No Content"
    $f3->set('episodes', $db_episode->find(
      array('show_id=?', $show_id), // filter
      array('order'=>'publish_ts DESC, episode_id DESC') // sorting
    ));

    $this->__ValidateEpisodeData($f3->get('episodes'));

    echo \Template::instance()->render('feed.xml', 'application/xml');
  }

  /* Generate a 'firehose' rss feed containing all episodes of all shows.
   * This also utilizes a view in the database to get all the data we need, but
   * we create a meta-show for the feed 'channel' data.
   */
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

    /* if any of the episodes or shows included in this feed are marked as
     * explicit, then we will mark the entire channel as explicit
     */
    if ($db_firehose_feed->count('show_explicit IS TRUE OR episode_explicit IS TRUE') === 0)
      $is_explicit = false;
    else
      $is_explicit = true;

    // build a meta-show array to encompass the whole network
    $metashow = array(
      'title' => $f3->get('SETTINGS.network_name'),
      'author' => $f3->get('SETTINGS.network_name'),
      'short_description' => 'Aggregation of podcasts from our network.',
      'explicit' => $is_explicit,
      'image_url' => $f3->get('SETTINGS.network_logo_url'),
      'category_name' => $db_settings->category_name,
      'category_group' => $db_settings->category_group,
    );
    $f3->set('show', $metashow);

    // load episodes
    if ($db_firehose_feed->count() != 0)
      $f3->error(204, 'No episodes available.'); // HTTP 204 = "No Content"
    $f3->set('episodes', $db_firehose_feed->find(
      null, // filter
      array('order' => 'publish_ts DESC, episode_id DESC') // sorting
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
