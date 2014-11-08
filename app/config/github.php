<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/8/14
 * Time: 2:08 PM
 */

return array(
    'organization' => $_ENV['GITHUB_ORGANIZATION'],

    'client_id' => $_ENV['GITHUB_CLIENT_ID'],
    'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'],


    'scope' => 'user,read:org,repo',
);