<?php

use AsyncAws\S3\S3Client;

/* S3-compatible object storage backend. Works with any provider that speaks
 * the S3 API. Objects are stored public-read so they can be served directly to
 * podcast clients (and referenced as stable enclosure URLs in the RSS feeds)
 * without the application proxying the bytes.
 *
 * Connection details come from the [s3] section of podnounce.conf.
 */
class StorageS3 extends Storage {

  private $client = null;

  public function name() {
    return 's3';
  }

  // read an [s3] config value, throwing if a required one is missing
  private function cfg($key, $required = true) {
    $val = \Base::instance()->get("s3.$key");
    if ($required && ($val === null || $val === '' || $val === 'REPLACE_ME'))
      throw new \Exception("Missing required [s3] config value: $key");
    return $val;
  }

  // lazily build the client so a local-only install never needs S3 config
  private function client() {
    if ($this->client === null) {
      $this->client = new S3Client(array(
        'endpoint'         => $this->cfg('endpoint'),
        'region'           => $this->cfg('region'),
        'accessKeyId'      => $this->cfg('access_key'),
        'accessKeySecret'  => $this->cfg('secret_key'),
        'pathStyleEndpoint'=> filter_var($this->cfg('use_path_style', false), FILTER_VALIDATE_BOOLEAN),
      ));
    }
    return $this->client;
  }

  public function put($local_path, $key, $mime_type, $download_name) {
    $body = fopen($local_path, 'rb');
    if ($body === false)
      return false;
    try {
      $result = $this->client()->putObject(array(
        'Bucket'             => $this->cfg('bucket'),
        'Key'                => $key,
        'Body'               => $body,
        'ContentType'        => $mime_type ?: 'application/octet-stream',
        'ContentDisposition' => sprintf('attachment; filename="%s"', addslashes($download_name)),
        'ACL'                => 'public-read',
      ));
      $result->resolve(); // force the request and surface any error
      return true;
    } catch (\Exception $e) {
      error_log('StorageS3::put failed for '.$key.': '.$e->getMessage());
      return false;
    } finally {
      if (is_resource($body))
        fclose($body);
    }
  }

  public function exists($key) {
    try {
      return $this->client()->objectExists(array(
        'Bucket' => $this->cfg('bucket'),
        'Key'    => $key,
      ))->isSuccess();
    } catch (\Exception $e) {
      error_log('StorageS3::exists failed for '.$key.': '.$e->getMessage());
      return false;
    }
  }

  public function url($key) {
    $endpoint = rtrim($this->cfg('endpoint'), '/');
    $bucket   = $this->cfg('bucket');
    $parts    = parse_url($endpoint);
    $scheme   = isset($parts['scheme']) ? $parts['scheme'] : 'https';
    $host     = isset($parts['host']) ? $parts['host'] : $endpoint;
    $path     = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

    if (filter_var($this->cfg('use_path_style', false), FILTER_VALIDATE_BOOLEAN))
      // path-style: https://<endpoint>/<bucket>/<key>
      return sprintf('%s://%s%s/%s/%s', $scheme, $host, $path, $bucket, rawurlencode($key));
    // virtual-hosted-style: https://<bucket>.<endpoint>/<key>
    return sprintf('%s://%s.%s%s/%s', $scheme, $bucket, $host, $path, rawurlencode($key));
  }

  public function delete($key) {
    try {
      $this->client()->deleteObject(array(
        'Bucket' => $this->cfg('bucket'),
        'Key'    => $key,
      ))->resolve();
      return true;
    } catch (\Exception $e) {
      error_log('StorageS3::delete failed for '.$key.': '.$e->getMessage());
      return false;
    }
  }

  public function fetchToTemp($key) {
    try {
      $result = $this->client()->getObject(array(
        'Bucket' => $this->cfg('bucket'),
        'Key'    => $key,
      ));
      $tmp = tempnam(sys_get_temp_dir(), 'pn_s3_');
      $fh = fopen($tmp, 'wb');
      foreach ($result->getBody()->getChunks() as $chunk)
        fwrite($fh, $chunk);
      fclose($fh);
      return $tmp;
    } catch (\Exception $e) {
      error_log('StorageS3::fetchToTemp failed for '.$key.': '.$e->getMessage());
      return false;
    }
  }

}

// vim: set ts=2 sw=2 et :
