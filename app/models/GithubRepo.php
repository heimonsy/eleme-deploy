<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/8/14
 * Time: 8:59 PM
 */


class GithubRepo
{
    public $id;
    public $name;
    public $fullName;
    public $gitUrl;

    public function __construct($id, $name, $fullName, $gitUrl)
    {
        $this->id = $id;
        $this->name = $name;
        $this->fullName = $fullName;
        $this->gitUrl = $gitUrl;
    }
}