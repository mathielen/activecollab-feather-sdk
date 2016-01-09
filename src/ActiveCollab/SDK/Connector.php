<?php
namespace ActiveCollab\SDK;

use ActiveCollab\SDK\Exceptions\AppException;
use ActiveCollab\SDK\Exceptions\CallFailed;

/**
 * Connector makes requests and returns API responses
 */
class Connector
{

  private $contentTypeForPost = 'application/json';

  public function setContentTypeForPost($contentTypeForPost = 'application/json')
  {
    $this->contentTypeForPost = $contentTypeForPost;
  }

  /**
   * GET data
   *
   * @param  string $url
   * @param  array|null $headers
   * @return Response
   */
  public function get($url, $headers = null)
  {
    $http = $this->getHandle($url, $headers);

    return $this->execute($http);
  }

  /**
   * POST data
   *
   * @param  string $url
   * @param  array|null $headers
   * @param  array $post_data
   * @param  array $files
   * @return Response
   */
  public function post($url, $headers = null, $post_data = null, $files = null)
  {
    if (empty($headers)) {
      $headers = [];
    }

    if ($files) {
      $headers[] = 'Content-type: multipart/form-data';
    } else {
      $headers[] = 'Content-type: ' . $this->contentTypeForPost;
    }

    $http = $this->getHandle($url, $headers);

    curl_setopt($http, CURLOPT_POST, 1);

    if ($files) {
      if (empty($post_data)) {
        $post_data = [];
      }

      $counter = 1;

      foreach ($files as $file) {
        if (is_array($file)) {
          list($path, $mime_type) = $file;
        } else {
          $path = $file;
          $mime_type = 'application/octet-stream';
        }

        $post_data['attachment_' . $counter++] = '@' . $path . ';type=' . $mime_type;
      }

      curl_setopt($http, CURLOPT_SAFE_UPLOAD, false); // PHP 5.6 compatibility for file uploads
      curl_setopt($http, CURLOPT_POST, 1);
      curl_setopt($http, CURLOPT_POSTFIELDS, $post_data);
    } else {
      if ($post_data) {
        curl_setopt($http, CURLOPT_POSTFIELDS, $this->contentTypeForPost === 'application/json' ? json_encode($post_data) : http_build_query($post_data));
      } else {
        curl_setopt($http, CURLOPT_POSTFIELDS, $this->contentTypeForPost === 'application/json' ? '{}' : []);
      }
    }

    return $this->execute($http);
  }

  /**
   * Send a PUT request
   *
   * @param  string $url
   * @param  array|null $headers
   * @param  array $put_data
   * @return Response
   */
  public function put($url, $headers = null, $put_data = null)
  {
    if (empty($headers)) {
      $headers = [];
    }

    $headers[] = 'Content-type: application/json';

    $http = $this->getHandle($url, $headers);

    curl_setopt($http, CURLOPT_CUSTOMREQUEST, 'PUT');

    if ($put_data) {
      curl_setopt($http, CURLOPT_POSTFIELDS, json_encode($put_data));
    } else {
      curl_setopt($http, CURLOPT_POSTFIELDS, '{}');
    }

    return $this->execute($http);
  }

  /**
   * Send a DELETE request
   *
   * @param  string $url
   * @param  array|null $headers
   * @param  array $delete_data
   * @return Response
   */
  public function delete($url, $headers = null, $delete_data = null)
  {
    if (empty($headers)) {
      $headers = [];
    }

    $headers[] = 'Content-type: application/json';

    $http = $this->getHandle($url, $headers);

    curl_setopt($http, CURLOPT_CUSTOMREQUEST, 'DELETE');

    if ($delete_data) {
      curl_setopt($http, CURLOPT_POSTFIELDS, json_encode($delete_data));
    } else {
      curl_setopt($http, CURLOPT_POSTFIELDS, '{}');
    }

    return $this->execute($http);
  }

  /**
   * Return curl resource
   *
   * @param  string $url
   * @param  array|null $headers
   * @return resource
   */
  private function &getHandle($url, $headers)
  {
    $http = curl_init();

    curl_setopt($http, CURLOPT_USERAGENT, Client::getUserAgent());
    curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($http, CURLINFO_HEADER_OUT, true);
    curl_setopt($http, CURLOPT_URL, $url);

    if (is_array($headers) && count($headers)) {
      curl_setopt($http, CURLOPT_HTTPHEADER, $headers);
    }

    return $http;
  }

  /**
   * Do the call
   *
   * @param  resource $http
   * @return string
   * @throws CallFailed
   * @throws AppException
   */
  private function execute(&$http)
  {
    $raw_response = curl_exec($http);

    if ($raw_response === false) {
      $error_code = curl_errno($http);
      $error_message = curl_error($http);

      curl_close($http);

      throw new CallFailed($error_code, $raw_response, null, $error_message);
    } else {
      $response = new Response($http, $raw_response);
      curl_close($http);

      return $response;
    }
  }
}
