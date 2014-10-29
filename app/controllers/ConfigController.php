<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-24
 * Time: 下午8:17
 */


class ConfigController extends Controller
{

    public function __construct()
    {
        $sites = (new WebSite())->getList();
        View::share('sites', $sites);
    }

    public function config($siteId)
    {
        $dc = new DC($siteId);
        $root = $dc->get(DC::ROOT);
        $staticDir = $dc->get(DC::STATIC_DIR);
        $SCOK = Session::get('SCOK', false);


        return View::make('deploy.config', array(
            'deploy_root'       => $dc->get(DC::ROOT),
            'static_dir'        => $dc->get(DC::STATIC_DIR),
            'default_branch'    => $dc->get(DC::DEFAULT_BRANCH),
            'remote_user'       => $dc->get(DC::REMOTE_USER),
            'service_name'      => $dc->get(DC::SERVICE_NAME),
            'remote_app_dir'    => $dc->get(DC::REMOTE_APP_DIR),
            'remote_static_dir' => $dc->get(DC::REMOTE_STATIC_DIR),
            'build_command'     => $dc->get(DC::BUILD_COMMAND),
            'rsync_exclude'     => $dc->get(DC::RSYNC_EXCLUDE),
            'remote_owner'      => $dc->get(DC::REMOTE_OWNER),
            'staticScript'      => $dc->get(DC::DEPLOY_STATIC_SCRIPT),
            'webScript'      => $dc->get(DC::DEPLOY_WEB_SCRIPT),
            'SCOK' => $SCOK,
            'siteId' => $siteId,
        ));
    }

    public function saveConfig()
    {
        $siteId = Input::get('siteId');
        $dc = new DC($siteId);

        $dc->set(DC::ROOT, Input::get('deployRoot'));
        $dc->set(DC::STATIC_DIR, Input::get('staticDir'));
        $dc->set(DC::DEFAULT_BRANCH, Input::get('defaultBranch'));
        $dc->set(DC::REMOTE_USER, Input::get('remoteUser'));
        $dc->set(DC::SERVICE_NAME, Input::get('serviceName'));
        $dc->set(DC::REMOTE_APP_DIR, Input::get('remoteAppDir'));
        $dc->set(DC::REMOTE_STATIC_DIR, Input::get('remoteStaticDir'));
        $dc->set(DC::BUILD_COMMAND, Input::get('buildCommand'));
        $dc->set(DC::RSYNC_EXCLUDE, Input::get('rsyncExclude'));
        $dc->set(DC::REMOTE_OWNER, Input::get('remoteOwner'));
        $dc->set(DC::DEPLOY_STATIC_SCRIPT, Input::get('staticHostScript'));
        $dc->set(DC::DEPLOY_WEB_SCRIPT, Input::get('webHostScript'));


        return Redirect::to('/site/config/'.$siteId)->with('SCOK', true);
    }

    public function hostConfig()
    {
        $redis = app('redis')->connection();
        $static_staging = $redis->lrange('deploy.L.static.hosts.staging', 0, -1);
        $static_production = $redis->lrange('deploy.L.static.hosts.production', 0, -1);

        $web_staging = $redis->lrange('deploy.L.web.hosts.staging', 0, -1);
        $web_production = $redis->lrange('deploy.L.web.hosts.production', 0, -1);

        $solve = function(&$arr, &$brr) {
            foreach($brr as $m) {
                $arr[] = json_decode($m, true);
            }
        };

        $static_hosts = array();
        $solve($static_hosts, $static_staging);
        $solve($static_hosts, $static_production);

        $web_hosts = array();
        $solve($web_hosts, $web_staging);
        $solve($web_hosts, $web_production);

        return View::make('hostconfig', array(
            'static_hosts' => $static_hosts,
            'web_hosts' => $web_hosts,
        ));
    }

    public function hostAdd()
    {
        $hostname = Input::get('hostname');
        $hostip = Input::get('hostip');
        $hostport = Input::get('hostport');
        $hosttype = Input::get('hosttype');
        $time = date('Y-m-d H:i:s');

        $type = Input::get('type') ;

        $host = array(
            'hostname' => $hostname,
            'hostip' => $hostip,
            'hostport' => intval($hostport),
            'hosttype' => $hosttype,
            'type' => $type,
            'time' => $time
        );

        $redis = app('redis')->connection();
        $jstr = json_encode($host);
        $redis->lpush('deploy.L.'.$type.'.hosts.'.$hosttype, $jstr);

        return Response::json(array_merge($host, array('jstr' => $jstr)));
    }

    public function hostDel() {
        // lrem deploy.L.static.hosts.production 1
        $redis = app('redis')->connection();
        $jstr = Input::get('jstr');
        $host = json_decode($jstr, true);
        $res = $redis->lrem('deploy.L.'.$host['type'].'.hosts.'.$host['hosttype'], 1, $jstr);

        return Response::json(array('res' => $res));
    }
}
