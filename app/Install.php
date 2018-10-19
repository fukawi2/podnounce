<?php

Class Install extends Controller {

  // Refer: https://help.apple.com/itc/podcasts_connect/#/itc9267a2f12
  private $iTunesCategories = array(
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

  private $AudioLicenses = array(
    'none'        => 'All Rights Reserved',
    'CC0'         => 'Public Domain',
    'CC_BY'       => 'Creative Commons - BY',
    'CC_BY-SA'    => 'Creative Commons - BY-SA',
    'CC_BY-NC0'   => 'Creative Commons - BY-NC',
    'CC_BY-NC-SA' => 'Creative Commons - BY-NC-SA',
    'CC_BY-ND'    => 'Creative Commons - BY-ND',
    'CC_BY-NC-ND' => 'Creative Commons - BY-NC-ND',
  );


  function Main($f3,$params) {
    // check if we are already installed
    $db_setting = new DB\SQL\Mapper($f3->get('DB'), 'settings');
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');

    /* count the number of rows in the `users` table and reroute to
     * the home page if the table is not empty. This means the installer
     * can only be run once
     */
    if ($db_user->count() > 0)
      $f3->error(400, 'Installation already completed.');

    if ($f3->VERB == 'POST')
      $this->__DoInstall($f3,$params);

    $this->RenderPage('install.htm', 'Install', 'Install '.$f3->get('PACKAGE'));
  }

  private function __DoInstall($f3,$params) {
    $db_setting = new DB\SQL\Mapper($f3->get('DB'), 'settings');
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');

    // validate user input
    foreach ($this->setting_keys as $key => $required) {
      /* some normally mandatory settings aren't required at install time so
       * only check they're not null if the field is actually set on the
       * install form. this particularly affects the 'network_category' because
       * the database table of categories isn't populated until after install
       * so we can't demand the user selects from a non-existent list prior to
       * installation!
       */
      if (!$f3->exists('POST.'.$key))
        continue;

      $value = $this->NullIfEmpty($f3->get('POST.'.$key));
      if ($required and empty($value)) {
        $f3->set('SESSION.TOAST.msg', $key.' is required');
        $f3->set('SESSION.TOAST.class', 'error');
        return false;
      }
    }
    $username = $f3->get('POST.username');
    $passwd1 = $f3->get('POST.passwd1');
    $passwd2 = $f3->get('POST.passwd2');
    $passwd_quality = $this->CheckPasswordQuality($passwd1, $passwd2);
    if ($passwd_quality !== true) {
      // password valied quality checks
      $f3->set('SESSION.TOAST.msg', $passwd_quality);
      $f3->set('SESSION.TOAST.class', 'error');
      return false;
    }

    // save settings to the database
    foreach ($this->setting_keys as $key => $required) {
      $value = $f3->get('POST.'.$key);
      $db_setting->setting = $key;
      $db_setting->value = $value;
      $db_setting->save();
      $db_setting->reset();
    }

    // create the first user
    $db_user->reset();
    $db_user->username = $username;
    $db_user->passwd = password_hash($passwd1, PASSWORD_DEFAULT);
    $db_user->save();

    // prepopulate database tables
    $this->__PopulateTables($f3,$params);

    $f3->set('SESSION.TOAST.msg', 'Installation Complete!');
    $f3->set('SESSION.TOAST.class', 'success');
    $f3->reroute('@settings');
  }

  /* Populates the database lookup tables with based on the arrays defined
   * at the top of this file. This means we can easily generate a schema-only
   * dump of the database for distribution, and populate it with what we need
   * at install time, rather than having to strip test data from the dump
   * or manually insert the data to the .sql file prior to release.
   * This function is safe to re-run because we count the number of rows in
   * each table before starting to populate it.
   */
  private function __PopulateTables($f3,$params) {
    // populate categories table
    $db_cat = new DB\SQL\Mapper($f3->get('DB'), 'categories');
    if ($db_cat->count() == 0) {
      foreach($this->iTunesCategories as $key => $value) {
        if (is_array($value)) {
          foreach($value as $subcat) {
            $db_cat->reset();
            $db_cat->category_name = $subcat;
            $db_cat->category_group = $key;
            $db_cat->save();
          }
        }
        // top-level category with no subgroups
        $db_cat->reset();
        $db_cat->category_name = $key;
        $db_cat->save();
      }
    }

    // populate licenses table
    $db_license = new DB\SQL\Mapper($f3->get('DB'), 'licenses');
    if ($db_license->count() == 0) {
      foreach($this->AudioLicenses as $key => $value) {
        $db_license->reset();
        $db_license->license_id=$key;
        $db_license->description=$value;
        $db_license->save();
      }
    }
  }

}
