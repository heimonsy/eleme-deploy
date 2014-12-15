<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-24
 * Time: 下午8:17
 */


class ConfigController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function config($siteId)
    {
        $dc = new DC($siteId);
        $SCOK = Session::get('SCOK', false);

        $passphrase = empty($dc->get(DC::PASSPHRASE)) ? '' : '******';
        $identifyfile = empty($dc->get(DC::IDENTIFYFILE))? '' : '**** Secret ****';

        return View::make('deploy.config', array(
            'workRoot'       => (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD),
            'static_dir'        => $dc->get(DC::STATIC_DIR),
            'remote_user'       => $dc->get(DC::REMOTE_USER),
            'defaultBranch'       => $dc->get(DC::DEFAULT_BRANCH),
            //'service_name'      => $dc->get(DC::SERVICE_NAME),
            'remote_app_dir'    => $dc->get(DC::REMOTE_APP_DIR),
            'remote_static_dir' => $dc->get(DC::REMOTE_STATIC_DIR),
            'build_command'     => $dc->get(DC::BUILD_COMMAND),
            'rsync_exclude'     => $dc->get(DC::RSYNC_EXCLUDE),
            'remote_owner'      => $dc->get(DC::REMOTE_OWNER),
            'staticScript'      => $dc->get(DC::DEPLOY_STATIC_SCRIPT),
            'webScript'         => $dc->get(DC::DEPLOY_WEB_SCRIPT),
            'gitOrigin'         => $dc->get(DC::GIT_ORIGIN),
            'testCommand'       => $dc->get(DC::TEST_COMMAND),
            'passphrase'       => $passphrase,
            'identifyfile'       => $identifyfile,
            'SCOK' => $SCOK,
            'siteId' => $siteId,
            'leftNavActive' => 'config',
        ));
    }

    public function saveConfig()
    {
        $siteId = Input::get('siteId');
        $dc = new DC($siteId);

        $dc->set(DC::IDENTIFYFILE, Input::get('identifyfile'));
        $dc->set(DC::PASSPHRASE, Input::get('passphrase'));
        $dc->set(DC::STATIC_DIR, Input::get('staticDir'));
        $dc->set(DC::DEFAULT_BRANCH, Input::get('defaultBranch'));
        $dc->set(DC::REMOTE_USER, Input::get('remoteUser'));
        //$dc->set(DC::SERVICE_NAME, Input::get('serviceName'));
        $dc->set(DC::REMOTE_APP_DIR, Input::get('remoteAppDir'));
        $dc->set(DC::REMOTE_STATIC_DIR, Input::get('remoteStaticDir'));
        $dc->set(DC::BUILD_COMMAND, Input::get('buildCommand'));
        $dc->set(DC::RSYNC_EXCLUDE, Input::get('rsyncExclude'));
        $dc->set(DC::TEST_COMMAND, Input::get('testCommand'));
        $dc->set(DC::REMOTE_OWNER, Input::get('remoteOwner'));
        $staticHostScript = Input::get('staticHostScript');
        $webHostScript = Input::get('webHostScript');
        $compile = 'Static Host';
        try {
            $list = ScriptCommand::complie($staticHostScript, $siteId);
            $compile = 'Web Host';
            $list = ScriptCommand::complie($webHostScript, $siteId);

        } catch(Exception $e) {
            return Response::json(array(
                'res' => 1,
                'errMsg' => "[{$compile} Script] ".$e->getMessage()
            ));
        }
        $dc->set(DC::DEPLOY_STATIC_SCRIPT, $staticHostScript);
        $dc->set(DC::DEPLOY_WEB_SCRIPT, Input::get('webHostScript'));

        return Response::json(array('res' => 0));
    }

    public function hostConfig($siteId)
    {
        $hostTypes = (new HostType())->getList();

        $staticHosts = array();
        $webHosts = array();
        foreach ($hostTypes as $hostType) {
            $staticHosts = array_merge($staticHosts, (new SiteHost($siteId, $hostType, SiteHost::STATIC_HOST))->getList());
            $webHosts = array_merge($webHosts, (new SiteHost($siteId, $hostType, SiteHost::WEB_HOST))->getList());
        }

        return View::make('deploy.hostconfig', array(
            'static_hosts' => $staticHosts,
            'web_hosts' => $webHosts,
            'siteId' => $siteId,
            'hostTypes' => $hostTypes,
            'hostStaticType' => SiteHost::STATIC_HOST,
            'hostWebType'    => SiteHost::WEB_HOST,
            'leftNavActive' => 'hostConfig',
        ));
    }

    public function hostAdd()
    {
        $hostname = Input::get('hostname');
        $hostip = Input::get('hostip');
        $hostport = intval(Input::get('hostport'));
        $hosttype = Input::get('hosttype');
        $time = date('Y-m-d H:i:s');
        $type = Input::get('type');
        $siteId = Input::get('siteId');

        (new SiteHost($siteId, $hosttype, $type))->add(array(
            'hostname' => $hostname,
            'hostip'   => $hostip,
            'hosttype' => $hosttype,
            'hostport' => $hostport,
            'time'     => $time,
            'type'     => $type,
        ));

        return Redirect::to('/host/config/'.$siteId)->with('SCOK', '主机'.$hostname.'添加成功');
    }

    public function hostDel() {
        $siteId = Input::get('siteId');
        $jstr = Input::get('jstr');
        $host = json_decode($jstr, true);

        (new SiteHost($siteId, $host['hosttype'], $host['type']))->remove($host);

        return Response::json(array('res' => 0));
    }
}
