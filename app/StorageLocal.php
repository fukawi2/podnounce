<?php

/* Local filesystem storage backend. This preserves the original behaviour of
 * PodNounce: media files live in the directory named by the UPLOADS hive var
 * (default 'uploads/') and are served as static files by the web server.
 */
class StorageLocal extends Storage {

  public function name() {
    return 'local';
  }

  // absolute-on-disk path for a given key
  private function path($key) {
    return \Base::instance()->get('UPLOADS') . $key;
  }

  public function put($local_path, $key, $mime_type, $download_name) {
    $dest = $this->path($key);

    /* Keys are content hashes, so an existing file with the same key is
     * byte-identical; treat that as success without overwriting.
     */
    if (is_file($dest))
      return true;

    /* An uploaded file must be moved with move_uploaded_file() for safety;
     * anything else (eg a file being re-imported) is copied into place.
     */
    if (is_uploaded_file($local_path))
      return move_uploaded_file($local_path, $dest);
    return copy($local_path, $dest);
  }

  public function exists($key) {
    return is_file($this->path($key));
  }

  public function url($key) {
    $f3 = \Base::instance();
    // fully-qualified URL: <canonical_url>/<uploads_dir>/<key>
    return sprintf('%s/%s%s',
      rtrim($f3->get('SETTINGS.canonical_url'), '/'),
      $f3->get('UPLOADS'),
      $key
    );
  }

  public function delete($key) {
    $dest = $this->path($key);
    if (!is_file($dest))
      return true;
    return unlink($dest);
  }

  public function fetchToTemp($key) {
    // the object is already a local file; hand back its real path
    $src = $this->path($key);
    return is_file($src) ? $src : false;
  }

}

// vim: set ts=2 sw=2 et :
