<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/8/14
 * Time: 8:51 PM
 */

class TeamRepos
{
    private $keyPrefix = 'DEPLOY:S:TEAMREPOS:';
    private $teamId;
    private $repos;
    private $redis;
    private $expires;

    public function __construct($teamId, $userToken= null)
    {
        if ($userToken == NULL) {
            $user = GithubLogin::getLoginUser();
            $userToken = $user->token;
        }
        $this->teamId = $teamId;
        $this->redis = app('redis')->connection();

        $this->expires = 432000; // 5 day

        $jstr = $this->redis->get($this->key());
        if (empty($jstr)) {
            //$user = GithubLogin::getLoginUser();
            $client = new \Eleme\Github\GithubClient($userToken);
            $this->repos = array();
            $page = 1;
            $url = $client->catUrl('teams/' . $teamId . '/repos');
            do {
                $tempRepos = $client->get($url);
                if (empty($tempRepos->message)) {
                    foreach ($tempRepos as $m) {
                        if ($m->owner->login == Config::get('github.organization')) {
                            $this->repos[] = new GithubRepo(
                                $m->id,
                                $m->name,
                                $m->full_name,
                                $m->ssh_url
                            );
                        }
                    }
                } else {
                    throw new Exception('teamId doesn\'t found');
                }
                $header = $client->getResponse()->getHeader('Link');
                preg_match('/<(.+?)>; rel="next"/', $header, $matchs);
                if (count($matchs) != 2) break;
                $url = $matchs[1];
            } while (!empty($url));
            $this->save();
        } else {
            $this->repos = json_decode($jstr);
        }

    }

    public function json()
    {
        return json_encode($this->repos);
    }

    public function save()
    {
        $this->redis->set($this->key(), $this->json(), 'ex', $this->expires);
    }

    public function key()
    {
        return $this->keyPrefix . $this->teamId;
    }

    public function repos()
    {
        return $this->repos;
    }

    public static function delByTeamId($teamId)
    {
        $redis = app('redis')->connection();
        $redis->del('DEPLOY:S:TEAMREPOS:' . $teamId);
    }
}
