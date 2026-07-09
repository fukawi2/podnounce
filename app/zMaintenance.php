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
          /* fetch the media to a local file (a no-op for local storage,
           * a download for remote backends) so we can inspect the audio
           */
          $storage = Storage::forLocation($db_media->storage_location);
          $tmp = $storage->fetchToTemp($db_media->fname_on_disk);
          $d = $tmp ? $this->CalculateMediaDuration($tmp) : false;
          if ($tmp && $storage->name() !== 'local')
            @unlink($tmp); // clean up the downloaded copy
          if ($d) {
            echo "  Updating duration to $d";
            $db_media->duration = $d;
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
   * 2. Object still exists in its storage backend
   * Also checks that a row exists for all files still in local storage.
   */
  public function ValidateMediaStorage() {
    $f3 = Base::instance();
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');
    $db_shows = new DB\SQL\Mapper($f3->get('DB'), 'shows');
    $db_episodes = new DB\SQL\Mapper($f3->get('DB'), 'episodes');

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

    echo "=== CHECKING FOR ORPHANED LOCAL FILES ===<br/>";
    $chkcnt = 0;
    $badcnt = 0;
    $uploads = $f3->get('UPLOADS');
    foreach ($this->__find_all_files($uploads) as $path) {
      $fname = basename($path);
      $db_media->load(array('fname_on_disk=?', $fname));
      if ($db_media->dry()) {
        // no record in the database for this file
        echo "NG: No database record for $fname; DELETING<br/>";
        unlink($uploads.$fname);
        $badcnt++;
      } else {
        echo "OK: $fname belongs to database record $db_media->media_id<br/>";
      }
      $chkcnt++;
    }
    printf('Checked %s local files; Deleted %s<br/>', $chkcnt, $badcnt);
    echo "<br/>";

    echo "=== CHECKING FOR MISSING OBJECTS ===<br/>";
    $chkcnt = 0;
    $badcnt = 0;
    $db_media->reset();
    $db_media->load();
    while (!$db_media->dry()) {
      $storage = Storage::forLocation($db_media->storage_location);
      if ($storage->exists($db_media->fname_on_disk)) {
        echo "OK: Database record $db_media->media_id object $db_media->fname_on_disk exists ($db_media->storage_location)<br/>";
      } else {
        echo "NG: Missing object: $db_media->fname_on_disk ($db_media->fname_nice) [$db_media->storage_location]<br/>";
        $badcnt++;
      }
      $chkcnt++;
      $db_media->next();
    }
    printf('Checked %s records; Found %s missing objects.<br/>', $chkcnt, $badcnt);
  }

  /* Migrates all locally-stored media to the S3 backend, then reclaims disk by
   * deleting the local copy once the object is confirmed present in S3. This is
   * safe to re-run: already-migrated records are skipped, and a local file is
   * only removed after its S3 object has been verified AND no remaining local
   * record still references it.
   */
  public function MigrateToS3() {
    $f3 = Base::instance();
    $db_media = new DB\SQL\Mapper($f3->get('DB'), 'media');
    $s3 = Storage::forLocation('s3');
    $local = Storage::forLocation('local');

    echo "=== MIGRATING LOCAL MEDIA TO S3 ===<br/>";
    $migrated = 0;
    $skipped = 0;
    $failed = 0;
    $deleted = 0;

    $rows = $db_media->find(array('storage_location=?', 'local'));
    $migrated_keys = array();
    foreach ($rows as $row) {
      $key = $row->fname_on_disk;
      $src = $f3->get('UPLOADS').$key;
      if (!is_file($src)) {
        echo "SKIP: media $row->media_id local file missing: $key<br/>";
        $skipped++;
        continue;
      }
      // upload, then verify the object really landed before flipping the record
      if ($s3->put($src, $key, $row->mime_type, $row->fname_nice) && $s3->exists($key)) {
        $row->storage_location = 's3';
        $row->save();
        $migrated_keys[$key] = true;
        $migrated++;
        echo "OK: media $row->media_id uploaded and flipped to s3: $key<br/>";
      } else {
        echo "FAIL: media $row->media_id upload/verify failed; left on local: $key<br/>";
        $failed++;
      }
    }

    // reclaim disk: delete each migrated local file no longer referenced by a
    // record still marked 'local' (the same content hash can back several rows)
    foreach (array_keys($migrated_keys) as $key) {
      if ($db_media->count(array('fname_on_disk=? AND storage_location=?', $key, 'local')) == 0) {
        if ($local->delete($key)) {
          $deleted++;
          echo "RECLAIMED: deleted local copy of $key<br/>";
        }
      } else {
        echo "KEEP: local copy of $key still referenced by a local record<br/>";
      }
    }

    echo "<br/>";
    printf('Migrated %s; Skipped %s; Failed %s; Local copies deleted %s<br/>',
      $migrated, $skipped, $failed, $deleted);
  }

  /* builds an array of all files in a directory, recursing as required. Only
   * returns the actual files (and paths), but not the actual directory names
   * or special files (ie, "." and "..")
   */
  private function __find_all_files($dir) {
    $dir = rtrim($dir, '/');
    $result = array();
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
