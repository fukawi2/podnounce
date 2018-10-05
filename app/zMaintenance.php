<?php

Class zMaintenance extends Controller {

  /* calculates the duration of audio files in the `media` table for any
   * rows that are missing a duration for whatever reason
   */
  public function FixDurations() {
    $f3 = Base::instance();
    $db_episode = new DB\SQL\Mapper($f3->get('DB'), 'show_feed');
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');

    echo "=== CHECKING FOR MISSING DURATIONS ===<br/>";
    $chkcnt = 0;
    $badcnt = 0;
    $fixcnt = 0;

    // make sure everything has a duration
    $db_episode->load();
    while (!$db_episode->dry()) {
      echo "Episode $db_episode->episode_id has duration '$db_episode->duration'<br/>";
      if (!$db_episode->duration) {
        // no duration previously calculated
        echo "  Duration missing!<br/>";
        $badcnt++;
        $db_media->load(array('media_id=?', $db_episode->media_id));
        if (!$db_media->dry()) {
          echo "  Found media_id $db_media->media_id<br/>";
          $d = $this->CalculateMediaDuration($f3->get('UPLOADS').$db_media->fname_on_disk);
          if ($d) {
            echo "  Updating duration to $d";
            $db_media->duration = $this->CalculateMediaDuration($f3->get('UPLOADS').$db_media->fname_on_disk);
            $db_media->save();
            $fixcnt++;
          }
        }
      }
      $chkcnt++;
      $db_episode->next();
    }
    echo "<br/>";

    // user feedback
    if ($badcnt == 0)
      printf('Checked %s episodes; no errors found!', $chkcnt);
    else
      printf('Checked %s episodes; found %s missing duration and fixed %s of them<br/>',
        $chkcnt, $badcnt, $fixcnt);
  }

  /* Checks all rows in the `media` table for validity:
   * 1. Row is referenced by at least one show and/or episode
   * 2. File still exists on disk
   * Also checks that a row exists for all files still on disk.
   */
  public function ValidateMediaStorage() {
    $f3 = Base::instance();
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');
    $db_shows = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $db_episodes = new DB\SQL\Mapper($f3->get('DB'), 'episodes');

    // change our working directory to the uploads directory
    chdir($f3->get('UPLOADS'));

    echo "=== CHECKING FOR ORPHANED MEDIA RECORDS ===<br/>";
    $chkcnt = 0;
    $badcnt = 0;
    $db_media->reset();
    $db_media->load();
    while (!$db_media->dry()) {
      $show_cnt     = $db_shows->count(array('cover_art_id=?', $db_media->media_id));
      $episode_cnt  = $db_episodes->count(array('media_id=?', $db_media->media_id));
      if ($show_cnt + $episode_cnt > 0) {
        echo "OK: Database record $db_media->media_id used by at least one show/episode<br/>";
      } else {
        echo "NG: Unused record $db_media->media_id; REMOVING<br/>";
        $db_media->erase();
        $badcnt++;
      }
      $chkcnt++;
      $db_media->next();
    }
    printf('Checked %s media records; Removed %s<br/>', $chkcnt, $badcnt);
    echo "<br/>";

    echo "=== CHECKING FOR ORPHANED ON DISK FILES ===<br/>";
    $chkcnt = 0;
    $badcnt = 0;
    foreach ($this->__find_all_files('.') as $fname) {
      $fname = basename($fname);
      $db_media->load(array('fname_on_disk=?', $fname));
      if ($db_media->dry()) {
        // no record in the database for this file
        echo "NG: No database record for $fname; DELETING<br/>";
        unlink($fname);
        $badcnt++;
      } else {
        echo "OK: $fname belongs to database record $db_media->media_id<br/>";
      }
      $chkcnt++;
    }
    printf('Checked %s files on disk; Deleted %s<br/>', $chkcnt, $badcnt);
    echo "<br/>";

    echo "=== CHECKING FOR MISSING ON DISK FILES ===<br/>";
    $chkcnt = 0;
    $badcnt = 0;
    $db_media->reset();
    $db_media->load();
    while (!$db_media->dry()) {
      if (is_file($db_media->fname_on_disk)) {
        echo "OK: Database record $db_media->media_id file $db_media->fname_on_disk exists<br/>";
      } else {
        echo "NG: Missing file on disk: $db_media->fname_on_disk ($db_media->fname_nice)<br/>";
        $badcnt++;
      }
      $chkcnt++;
      $db_media->next();
    }
    printf('Checked %s records; Found %s missing files.<br/>', $chkcnt, $badcnt);
  }

  /* builds an array of all files in a directory, recursing as required. Only
   * returns the actual files (and paths), but not the actual directory names
   * or special files (ie, "." and "..")
   */
  private function __find_all_files($dir) {
    $dir = rtrim($dir, '/');
    $root = scandir($dir);
    foreach($root as $value) {
      if($value === '.' || $value === '..')
        continue;
      if(is_file("$dir/$value")) {
        $result[] = "$dir/$value";
        continue;
      }
      foreach($this->__find_all_files("$dir/$value") as $value)
        $result[] = $value;
    }
    return $result;
  }

}
?>
