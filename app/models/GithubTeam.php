<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/8/14
 * Time: 7:38 PM
 */


class GithubTeam
{
    public $name;
    public $id;
    public $permission;
    public $repositoriesUrl;

    public function __construct($team = NULL)
    {
        if ($team != NULL) {
            $this->name = $team->name;
            $this->id = $team->id;
            $this->permission = $team->permission;
            $this->repositoriesUrl = $team->repositories_url;
        }
    }

    public static function makeTeam($name, $id, $permission, $repositoriesUrl)
    {
        $gt = new GithubTeam();
        $gt->name = $name;
        $gt->id = $id;
        $gt->permission = $permission;
        $gt->repositoriesUrl = $repositoriesUrl;
        return $gt;
    }

}