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

  /*
   * user authentication functions
   */
  function Authenticate() {
    $f3 = Base::instance();
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');
    $auth = new \Auth($db_user, array('id'=>'user_id', 'pw'=>'passwd'));
    // TODO: proper authentication, with password hashes!
    if ($auth->basic()) {
      $f3->set('SESSION.USER', $db_user->cast());
      return true;
    }
    return false;
  }

}
