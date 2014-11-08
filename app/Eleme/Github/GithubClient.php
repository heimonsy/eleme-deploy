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

    public function request($uri, $params = array(), $post = false)
    {
        $option = array(
            'headers' => $this->headers(),
        );

        if ($post) {
            $option['body'] = $params;
            return json_decode($this->client->post(self::API . $uri, $option)->getBody());
        } else {
            return json_decode($this->client->get(self::API . $uri . '?' . http_build_query($params), $option)->getBody());
        }
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