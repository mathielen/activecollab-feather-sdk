<?php

namespace ActiveCollab\SDK;

use ActiveCollab\SDK\Exceptions\FileNotReadable;
use ActiveCollab\SDK\Exceptions\IssueTokenException;

/**
 * activeCollab API client
 */
final class Client
{
    const VERSION = '2.0.2'; // API wrapper version

    /**
     * Return user agent string
     *
     * @return string
     */
    public static function getUserAgent()
    {
        return 'Active Collab API Wrapper; v' . self::VERSION;
    }

    // ---------------------------------------------------
    //  Info
    // ---------------------------------------------------

    /**
     * Cached info response
     *
     * @var bool
     */
    private static $info_response = false;

    /**
     * Return info
     *
     * @param  string|bool $property
     * @return bool|null|string
     */
    public static function info($property = false)
    {
        if (self::$info_response === false) {
            self::$info_response = self::get('info')->getJson();
        }

        if ($property) {
            return isset(self::$info_response[$property]) && self::$info_response[$property] ? self::$info_response[$property] : null;
        } else {
            return self::$info_response;
        }
    }

    // ---------------------------------------------------
    //  Make and process requests
    // ---------------------------------------------------

    private static $headerAuthToken = true;

    public static function useHeaderForAuthToken($headerAuthToken = true)
    {
        self::$headerAuthToken = $headerAuthToken;
    }

    /**
     * API URL
     *
     * @var string
     */
    private static $url;

    /**
     * Return API URL
     *
     * @return string
     */
    public static function getUrl()
    {
        return self::$url;
    }

    /**
     * Set API URL
     *
     * @param string $value
     */
    public static function setUrl($value)
    {
        self::$url = $value;
    }

    /**
     * API version
     *
     * @var int
     */
    private static $api_version = 1;

    /**
     * Return API version
     *
     * @return int
     */
    public static function getApiVersion()
    {
        return self::$api_version;
    }

    /**
     * Set API version
     *
     * @param integer $version
     */
    public static function setApiVersion($version)
    {
        self::$api_version = (integer)$version;
    }

    /**
     * API key
     *
     * @var string
     */
    private static $key;

    /**
     * Return API key
     *
     * @return string
     */
    public static function getKey()
    {
        return self::$key;
    }

    /**
     * Set API key
     *
     * @param string $value
     */
    public static function setKey($value)
    {
        self::$key = $value;
    }

    /**
     * Connector instance
     *
     * @var \ActiveCollab\SDK\Connector
     */
    private static $connector;

    /**
     * Return connector instance
     *
     * @return Connector
     */
    public static function &getConnector()
    {
        if (empty(self::$connector)) {
            self::$connector = new Connector();
        }

        return self::$connector;
    }

    /**
     * @param  string $email_or_username
     * @param  string $password
     * @param  string $client_name
     * @param  string $client_vendor
     * @param  bool $read_only
     * @return string
     * @throws Exceptions\IssueTokenException
     */
    public static function issueToken($email_or_username, $password, $client_name, $client_vendor, $read_only = false)
    {
        $response = self::getConnector()->post(self::prepareUrl('issue-token'), [], self::prepareParams([
            'username' => $email_or_username,
            'password' => $password,
            'client_name' => $client_name,
            'client_vendor' => $client_vendor,
            'read_only' => $read_only,
        ]));

        $error = 0;

        if ($response instanceof Response && $response->isJson()) {
            $json = $response->getJson();

            if (is_array($json) && !empty($json['is_ok']) && !empty($json['token'])) {
                return $json['token'];
            } else {
                if (empty($json['error'])) {
                    return 'Invalid response';
                } else {
                    return $json['error'];
                }
            }
        }

        throw new IssueTokenException($error);
    }

    /**
     * Send a get request
     *
     * @param  string $path
     * @return Response
     */
    public static function get($path)
    {
        return self::getConnector()->get(self::prepareUrl($path), self::prepareHeaders());
    }

    /**
     * Send a POST request
     *
     * @param  string $path
     * @param  array|null $params
     * @param  array|null $attachments
     * @return Response
     */
    public static function post($path, $params = null, $attachments = null)
    {
        return self::getConnector()->post(self::prepareUrl($path), self::prepareHeaders(), self::prepareParams($params), self::prepareAttachments($attachments));
    }

    /**
     * Send a PUT request
     *
     * @param  string $path
     * @param  array|null $params
     * @param  array|null $attachments
     * @return Response
     */
    public static function put($path, $params = null, $attachments = null)
    {
        return self::getConnector()->put(self::prepareUrl($path), self::prepareHeaders(), self::prepareParams($params), self::prepareAttachments($attachments));
    }

    /**
     * Send a delete command
     *
     * @param  string $path
     * @param  array|null $params
     * @return Response
     */
    public static function delete($path, $params = null)
    {
        return self::getConnector()->delete(self::prepareUrl($path), self::prepareHeaders(), self::prepareParams($params));
    }

    /**
     * Prepare headers
     *
     * @return array
     */
    private static function prepareHeaders()
    {
        $headers = [
            'Accept: application/json'
        ];

        if (self::$headerAuthToken) {
            $headers[] = 'X-Angie-AuthApiToken: ' . self::getKey();
        }

        return $headers;
    }

    /**
     * Prepare URL from the given path
     *
     * @param  string $path
     * @return string
     */
    private static function prepareUrl($path)
    {
        $bits = parse_url($path);

        $path = isset($bits['path']) && $bits['path'] ? $bits['path'] : '/';

        if (substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }

        if (!self::$headerAuthToken) {
            $bits['query'] =
                isset($bits['query']) && $bits['query'] ?
                    $bits['query'] .= '&auth_api_token=' . self::getKey() :
                    $bits['query'] = '&auth_api_token=' . self::getKey();
        }

        $query = isset($bits['query']) && $bits['query'] ? '?' . $bits['query'] : '';

        return self::getUrl() . '/api/v' . self::getApiVersion() . $path . $query;
    }

    /**
     * Prepare params
     *
     * @param  array|null $params
     * @return array
     */
    private static function prepareParams($params)
    {
        return empty($params) ? [] : $params;
    }

    /**
     * Prepare attachments for request
     *
     * @param  array|null $attachments
     * @return array|null
     * @throws Exceptions\FileNotReadable
     */
    private static function prepareAttachments($attachments = null)
    {
        $file_params = [];

        if ($attachments) {
            $counter = 1;

            foreach ($attachments as $attachment) {
                $path = is_array($attachment) ? $attachment[0] : $attachment;

                if (is_readable($path)) {
                    $file_params['attachment_' . $counter++] = $attachment;
                } else {
                    throw new FileNotReadable($attachment);
                }
            }
        }

        return $file_params;
    }
}
