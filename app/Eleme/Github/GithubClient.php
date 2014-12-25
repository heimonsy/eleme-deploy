<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/8/14
 * Time: 1:37 PM
 */
namespace Eleme\Github;

use GuzzleHttp\Client;


class GithubClient
{
    const  API = 'https://api.github.com/';

    private $client;
    private $access_token;
    private $response;

    public function __construct($access_token = NULL)
    {
        $this->access_token = $access_token;
        $defaults = array();
        if (!empty($_ENV['CLIENT_PROXY'])) {
            $defaults['proxy'] = $_ENV['CLIENT_PROXY'];
        }

        $this->client = new Client(array(
            'defaults' => $defaults
        ));
    }

    public function get($url)
    {
        $option = array(
            'headers' => $this->headers(),
        );
        $this->response = $this->client->get($url, $option);
        return json_decode($this->response->getBody());
    }

    public static function catUrl($uri)
    {
        return self::API . $uri;
    }

    public function request($uri, $params = array(), $post = false)
    {
        $option = array(
            'headers' => $this->headers(),
        );

        if ($post) {
            $option['body'] = $params;
            $this->response = $this->client->post(self::API . $uri, $option);
        } else {
            $this->response = $this->client->get(self::API . $uri . '?' . http_build_query($params), $option);
        }
        return json_decode($this->response->getBody());
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;
    }

    private function headers()
    {
        $headers = array();
        if (!empty($this->access_token)) {
            $headers['Authorization'] = 'token ' . $this->access_token;
        }
        return $headers;
    }
}
