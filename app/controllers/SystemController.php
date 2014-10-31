<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-28
 * Time: 下午9:36
 */

use Symfony\Component\Process\Process;

class SystemController extends Controller
{

    public function index()
    {
        $sc = new SystemConfig();
        $hostTypes = (new HostType())->getList();
        $sites = (new WebSite())->getList();

        $success = Session::get('SCS', false);

        return View::make('index', array(
            'hostTypes' => $hostTypes,
            'sites' => $sites,
            'success' => $success,
            'workRoot' => $sc->get(SystemConfig::WORK_ROOT_FIELD),
        ));
    }

    public function systemConfig()
    {
        $sc = new SystemConfig();
        $workRoot = trim(Input::get('workRoot'));

//        if (File::isWritable($workRoot)) {
//            $sc->set(SystemConfig::WORK_ROOT_FIELD, $workRoot);
//        } else {
//            return Response::json(array(
//                'res' => 1,
//                'errMsg' => 'Work Root目录不可写!',
//            ));
//        }

        $sc->set(SystemConfig::WORK_ROOT_FIELD, $workRoot);

        return Response::json(array(
            'res' => 0,
            'write' => File::isWritable($workRoot),
        ));
    }

    public function addHostType()
    {
        $hostType = trim(Input::get('hostType'));
        (new HostType())->add($hostType);

        return Redirect::to('/')->with('SCS', '添加Host Type成功');
    }

    public function delHostType()
    {
        $hosttype = Input::get('hostType');
        (new HostType())->remove($hosttype);

        return Response::json(array('res' => 0));
    }

    public function addSite()
    {
        $workRoot = (new SystemConfig())->get(SystemConfig::WORK_ROOT_FIELD);
        if ($workRoot == '') {
            return Redirect::to('/')->with('SCS', 'Work Root未配置！');
        }

        $siteId = trim(Input::get('siteId'));
        $siteName = trim(Input::get('siteName'));
        $gitOrigin = trim(Input::get('gitOrigin'));
        (new Process("mkdir -p '{$workRoot}/$siteId/commit' "))->mustRun();
        (new Process("mkdir -p '{$workRoot}/$siteId/branch' "))->mustRun();

        (new DC($siteId))->set(DC::GIT_ORIGIN, $gitOrigin);
        (new WebSite())->add(array(
            'siteId' => $siteId,
            'siteName' => $siteName,
        ));

        return Redirect::to('/')->with('SCS', '添加Site成功');
    }

    public function delSite()
    {
        $siteId = trim(Input::get('siteId'));
        $siteName = trim(Input::get('siteName'));

        (new WebSite())->remove(array(
            'siteId' => $siteId,
            'siteName' => $siteName,
        ));

        return Response::json(array('res' => 0));
    }

}