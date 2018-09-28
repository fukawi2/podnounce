<?php

class Controller {

  public $iTunesCategories = array(
    'Arts' => array('Design','Fashion & Beauty','Food','Literature','Performing Arts','Visual Arts'),
    'Business' => array('Business News','Careers','Investing','Management & Marketing','Shopping'),
    'Comedy' => null,
    'Education' => array('Education Technology','Higher Education','K-12','Language Courses','Training'),
    'Games & Hobbies' => array('Automotive','Aviation','Hobbies','Other Games','Video Games'),
    'Government & Organizations' => array('Local','National','Non-Profit','Regional'),
    'Health' => array('Alternative Health','Fitness & Nutrition','Self-Help','Sexuality'),
    'Kids & Family' => null,
    'Music' => null,
    'News & Politics' => null,
    'Religion & Spirituality' => array('Buddhism','Christianity','Hinduism','Islam','Judaism','Other','Spirituality'),
    'Science & Medicine' => array('Medicine','Natural Sciences','Social Sciences'),
    'Society & Culture' => array('History','Personal Journals','Philosophy','Places & Travel'),
    'Sports & Recreation' => array('Amateur','College & High School','Outdoor','Professional'),
    'Technology' => array('Gadgets','Podcasting','Software How-To','Tech News'),
    'TV & Film' => null,
  );

  function beforeRoute($f3,$params) {
    // initialize the session dbms handler
    $session = new DB\SQL\Session($f3->get('DB'));

    // check csrf tokens
    if (!$f3->exists('SESSION.csrf_token'))
      $f3->set('SESSION.csrf_token', $session->csrf());

    // check if we need to install
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');
    if (!preg_match('|^/install|', $f3->get('PATTERN')) and $db_user->count() == 0)
      $f3->reroute('@install');

    // check user authentication
    if (preg_match('|^/admin|', $f3->get('PATTERN'))) {
      if (!$f3->exists('SESSION.USER'))
        $this->Authenticate();
    }

  }

  function afterRoute($f3,$params) {
    $f3->clear('SESSION.TOAST');
  }

  public function NullIfEmpty($str) {
    return (!empty($str) ? $str : null);
  }

  public function CheckPasswordQuality($p1, $p2) {
    if ($p1 != $p2)
      return 'Passwords do not match';

    if (strlen($p1) < 8)
      return 'Password must be at least 8 characters';

    return true;
  }

}
