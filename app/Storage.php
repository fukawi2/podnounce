<?php

/* Abstraction over a media object store. Each media file is addressed by a
 * "key" which, for historical reasons, is the value stored in the
 * `media.fname_on_disk` column (a content hash plus extension). Concrete
 * backends (local filesystem, S3-compatible object store) implement the
 * operations below so the rest of the application never needs to know where a
 * file physically lives.
 *
 * Every `media` row records its backend in the `storage_location` column so
 * that old and new media can coexist: use Storage::forLocation() when reading
 * an existing record, and Storage::instance() (the configured write backend)
 * when storing something new.
 */
abstract class Storage {

  // cache of instantiated backends, keyed by driver name
  private static $backends = array();

  /* Return the backend for an explicit driver name ('local' or 's3'). Used
   * when reading a media record whose location is already known.
   */
  public static function forLocation($driver) {
    $driver = $driver ?: 'local';
    if (!isset(self::$backends[$driver])) {
      switch ($driver) {
        case 'local':
          self::$backends[$driver] = new StorageLocal();
          break;
        case 's3':
          self::$backends[$driver] = new StorageS3();
          break;
        default:
          throw new \Exception("Unknown storage driver: $driver");
      }
    }
    return self::$backends[$driver];
  }

  /* Return the backend configured for NEW uploads (the [storage] driver
   * setting), defaulting to local when unset.
   */
  public static function instance() {
    $f3 = \Base::instance();
    return self::forLocation($f3->get('storage.driver'));
  }

  /* The driver name for this backend, as stored in media.storage_location. */
  abstract public function name();

  /* Persist a local file (typically an uploaded temp file) under $key.
   * $downloadName is the human-friendly filename to present when the object is
   * later downloaded. Returns true on success (including when the object
   * already exists, since keys are content hashes).
   */
  abstract public function put($local_path, $key, $mime_type, $download_name);

  /* Does an object with this key already exist in the backend? */
  abstract public function exists($key);

  /* Fully-qualified, publicly fetchable URL for the object. */
  abstract public function url($key);

  /* Remove the object. Returns true on success or if already absent. */
  abstract public function delete($key);

  /* Fetch the object to a local temp file and return its path, or false on
   * failure. Caller is responsible for unlinking the returned path.
   */
  abstract public function fetchToTemp($key);

}

// vim: set ts=2 sw=2 et :
