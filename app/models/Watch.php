<?php

class Watch
{
    private $login;
    private $siteId;

    private $user;
    private $site;

    public function __construct($login, $siteId)
    {
        $this->login = $login;
        $this->siteId = $siteId;
        $this->user = new UserWatchingSites($login);
        $this->site = new SiteWatchers($siteId);
    }

    public function watch()
    {
        $this->user->add($this->siteId);
        $this->site->add($this->login);
    }

    public function notWatch()
    {
        $this->user->remove($this->siteId);
        $this->site->remove($this->login);
    }

    public static function allUserWatching($siteId)
    {
        return (new SiteWatchers($siteId))->all();
    }

    public static function allSiteWatched($login)
    {
        return (new UserWatchingSites($login))->all();
    }
}
