<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/8/14
 * Time: 1:31 PM
 */

namespace Eleme\Github;

use GuzzleHttp\Client;

class GithubAuthorize
{

    public static function accessToken($code)
    {
        $defaults = array();
        if (!empty($_ENV['CLIENT_PROXY'])) {
            $defaults['proxy'] = $_ENV['CLIENT_PROXY'];
        }

        $client = new Client(array(
            'defaults' => $defaults
        ));
        $response = $client->post('https://github.com/login/oauth/access_token', array(
            'body' => array(
                'client_id' => \Config::get('github.client_id'),
                'client_secret' => \Config::get('github.client_secret'),
                'code' => $code,
            ),
        ));

        $values = array();
        parse_str($response->getBody(), $values);
        return isset($values['access_token']) ? $values['access_token'] : NULL;
    }

    /**
     * @param $scope
     * @return string
     */
    public static function authorizeUrl($scope)
    {
        $client_id = \Config::get('github.client_id');
        $callBackUrl = \Config::get('app.url') . '/github/oauth/callback';

        return 'https://github.com/login/oauth/authorize?type=web_server&client_id='
        . $client_id . '&redirect_uri=' . $callBackUrl
        . '&scope=' .  $scope . '&response_type=code';
    }
}