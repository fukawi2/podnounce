<?php

class Controller {

  const VERSION = '1.0';

  /* general settings that are stored in the database are defined here for
   * batch processing in loops etc. the array key is the name of the setting
   * ('setting' column in the database) and the array value is a boolean to
   * indicate if that setting is required or optional
   */
  protected $setting_keys = array(
    'network_name'    => true,
    'admin_name'      => true,
    'admin_email'     => true,
    'canonical_url'   => true,
    'network_category'=> true,
    'intro_text'      => false,
    'analytics_code'  => false,
    'ep_display_count'=> false,
  );


  /*
   * fatfree calls this function on every execution before running the handler
   * in the relevant class. we use it to do some processing we always need,
   * such as loading global settings from the databae, csrf checks, and
   * checking user authentication. note that some classes override this
   * specific function with their own, in which case they explicitly call this
   * copy of beforeRoute() to ensure these things still happen.
   */
  function beforeRoute($f3,$params) {
    // Check csrf token. Refer: https://fatfreeframework.com/3.6/session#csrf
    if (!$f3->exists('SESSION.csrf_token')) {
      // create a new csrf token
      $f3->set('SESSION.csrf_token', $f3->get('sess')->csrf());
    } else if ($f3->VERB == 'POST') {
      // validate the csrf token
      if ($f3->get('POST.token') != $f3->get('SESSION.csrf_token')) {
        $f3->error(400, 'Bad CSRF Token');
      }
    }

    // load settings from database
    $this->__LoadSettings();

    /* check if we need to install; if there are no users in the `users` table
     * then bounce the user over to the install page where they can create an
     * initial user account.
     */
    $db_user = new DB\SQL\Mapper($f3->get('DB'), 'users');
    if (!preg_match('|^/install|', $f3->get('PATTERN')) and $db_user->count() == 0)
      $f3->reroute('@install');

    /* Check user authentication. Any url that starts with "/admin" requires
     * the client to be authenticated. This makes it easy and reliable to
     * ensure which pages are and are not protected by managing the url in our
     * routes as required.
     */
    if (preg_match('|^/admin|', $f3->get('PATTERN'))) {
      if (!$f3->exists('SESSION.USER'))
        $f3->reroute('@login');
    }
  }

  function afterRoute($f3,$params) {
    $f3->clear('SESSION.TOAST');
  }


  /* This takes care of the final page rendering steps. Rather than repeating
   * these same lines in every controller function, we have this short helper
   * to reduce page rendering steps to a single function call.
   */
  public function RenderPage($content, $title, $header = null) {
    $f3 = Base::instance();
    $header = $header ?: $title; // default header to title if header not set
    $f3->set('PAGE.TITLE', $title);
    $f3->set('PAGE.HEADER', $header);
    $f3->set('PAGE.CONTENT', $content);
    echo \Template::instance()->render('layouts/default.htm');
  }


  /* This function will return null instead of an empty string which helps
   * keep the database a bit 'nicer' so we can do proper null comparisons
   * rather than having to look for nulls and/or empty strings.
   */
  public function NullIfEmpty($str) {
    return (!empty($str) ? $str : null);
  }


  /* Basic password checks made when adding/changing a password for users.
   * any additional checks can be added here and apply globally. The return
   * value is a string describing the problem with the password(s) given.
   * IMPORTANT: when calling this function, make sure you check for strict
   * equality (use '===') because boolean true and a non-empty string both
   * evaluate loosely to true for comparison purposes.
   */
  public function CheckPasswordQuality($p1, $p2) {
    if ($p1 != $p2)
      return 'Passwords do not match';

    if (strlen($p1) < 8)
      return 'Password must be at least 8 characters';

    return true;
  }


  /* Process an uploaded file and store metadata about it in the `media` table
   * in the database. Optionally also checks that the filetype matches what we
   * expect if @expectedType is passed as a mime-type (supports file globbing
   * patterns for generic matching like "audio/*" for all audio).
   * For audio files, we also attempt to calculate the file duration and add it
   * to the `media` record too.
   */
  public function SaveUploadedFile($formFieldName, $expectedType = null, $newName = null) {
    $f3 = Base::instance();
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');

    // get the associative array of the upload and check it's populated
    $uploadfile = $f3->get('FILES')[$formFieldName];
    if (empty($uploadfile['name']))
      return false;

    // validate the mime type of the uploaded file
    if (!is_null($expectedType)) {
      $type_is_valid = false; // assume the file doesn't match
      if (is_array($expectedType)) {
        // array of valid types; loop over each
        foreach ($expectedType as $t) {
          if (!fnmatch($t, $uploadfile['type'])) {
            $type_is_valid = true;
            break;
          }
        }
      } else {
        // single mime type; simple check
        $type_is_valid = fnmatch($expectedType, $uploadfile['type']);
      }
      if ($type_is_valid === false)
        return false;
    }

    // generate a sha1sum of the temporary file
    $tmpname = $uploadfile['tmp_name'];
    $filehash = sha1_file($uploadfile['tmp_name']);

    // the filename we'll store the uploaded file as
    $fname_on_disk= sprintf('%s.%s',
      $filehash,
      pathinfo($uploadfile['name'], PATHINFO_EXTENSION)
    );

    // build a db object for the 'media' table
    $db_media->fname_nice   = $newName ?: $uploadfile['name']; // uses first not-null value
    $db_media->fname_on_disk= $fname_on_disk;
    $db_media->media_bytes  = $uploadfile['size'];
    $db_media->mime_type    = $uploadfile['type'];

    // relative path and filename to save the file on disk
    $savepath = sprintf('%s%s',
      $f3->get('UPLOADS'),
      $db_media->fname_on_disk
    );

    // if this is an audio file, determine duration and add to the media record
    if (in_array($db_media->mime_type, array('audio/mpeg', 'audio/mp3')))
      $db_media->duration = $this->CalculateMediaDuration($tmpname);

    /* Because we hash the file and save it to disk using the hash as the
     * filename, there is a small chance that the file already exists in our
     * storage (eg, the same episode published to multiple shows). So check
     * if there is already a file on disk with the same hash; if not, move the
     * uploaded file in to place. Then return the media_id to the caller.
     */
    if (is_file($savepath)) {
      // file already exists on disk; use the existing copy!
      $db_media->save();
      return $db_media->media_id;
    } elseif (move_uploaded_file($tmpname, $savepath)) {
      // move the uploaded file into place
      $db_media->save();
      return $db_media->media_id;
    }

    // failed
    return false;
  }


  public function CalculateMediaDuration($fname) {
    $mp3file = new MP3File($fname);
    $duration = $mp3file->getDuration();
    return MP3File::formatTime($duration);
  }


  // this is a static function so it can be called from within templates
  public static function GetMediaURLByID($media_id) {
    $f3 = Base::instance();
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');

    $db_media->load(array('media_id=?', $media_id));
    if ($db_media->dry())
      return null;

    return ($f3->get('UPLOADS') . $db_media->fname_on_disk);
  }


  /* Generates a type 4 UUID. This is used to give each episode a GUIG
   * in the database for the RSS feed functionality.
   */
  public function uuid(){
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      // 32 bits for "time_low"
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      // 16 bits for "time_mid"
      mt_rand(0, 0xffff),
      // 16 bits for "time_hi_and_version",
      // four most significant bits holds version number 4
      mt_rand(0, 0x0fff) | 0x4000,
      // 16 bits, 8 bits for "clk_seq_hi_res",
      // 8 bits for "clk_seq_low",
      // two most significant bits holds zero and one for variant DCE1.1
      mt_rand(0, 0x3fff) | 0x8000,
      // 48 bits for "node"
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
  }


  /* Converts a raw byte figure to human readable string
   * Source: http://jeffreysambells.com/2012/10/25/human-readable-filesize-php
  // this is a static function so it can be called from within templates
   */
  public static function bytes2human($bytes, $decimals = 2) {
    $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
  }


  public function FetchCategories() {
    $f3 = Base::instance();
    $db_category = new DB\SQL\Mapper($f3->get('DB'), 'categories');

    $categories = array();
    $db_category->load(
      null, // filter
      array('order'=>'category_group ASC NULLS FIRST, category_name ASC') // sorting
    );
    while (!$db_category->dry()) {
      $catid    = $db_category->category_id;
      $catname  = $db_category->category_name;
      $catgrp   = (!empty($db_category->category_group) ? $db_category->category_group : $catname);
      $categories[$catgrp][] = array(
        'category_id' => $catid,
        'category_name' => $catname,
      );
      $db_category->next();
    }
    return $categories;
  }


  /* Loads various global settings from the database and saves them into the
   * fatfree framework hive so they can be accessed everywhere.
   */
  private function __LoadSettings() {
    $f3 = Base::instance();
    $db_setting = new DB\SQL\Mapper($f3->get('DB'), 'settings');

    // network name
    $db_setting->load(array('setting=?','network_name'));
    $f3->set('SETTINGS.network_name', ($db_setting->dry() ? $f3->get('PACKAGE') : trim($db_setting->value)));

    // canonical url
    $generated_full_url = sprintf('%s://%s%s', $f3->get('SCHEME'), $f3->get('HOST'), $f3->get('BASE'));
    $db_setting->load(array('setting=?','canonical_url'));
    $f3->set('SETTINGS.canonical_url', ($db_setting->dry() ? $generated_full_url : trim($db_setting->value)));

    // administrator name and email
    $db_setting->load(array('setting=?','admin_name'));
    $f3->set('SETTINGS.admin_name', trim($db_setting->value));
    $db_setting->load(array('setting=?','admin_email'));
    $f3->set('SETTINGS.admin_email', trim($db_setting->value));

    // analytics code (needs to be included on every page so load it here)
    $db_setting->load(array('setting=?','analytics_code'));
    $f3->set('SETTINGS.analytics_code', trim($db_setting->value));

    // analytics code (needs to be included on every page so load it here)
    $db_setting->load(array('setting=?','analytics_code'));
    $f3->set('SETTINGS.analytics_code', trim($db_setting->value));

    // network logo id
    $db_setting->load(array('setting=?','network_logo'));
    if ($db_setting->value)
      $f3->set('SETTINGS.network_logo_url', $this->GetMediaURLByID($db_setting->value));

    return true;
  }

}
