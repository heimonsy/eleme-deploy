<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-28
 * Time: 下午9:36
 */

class SystemController extends Controller
{

    public function index()
    {
        $hostTypes = (new HostType())->getList();
        $sites = (new WebSite())->getList();

        $success = Session::get('SCS', false);

        return View::make('index', array(
            'hostTypes' => $hostTypes,
            'sites' => $sites,
            'success' => $success,
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
        $siteId = trim(Input::get('siteId'));
        $siteName = trim(Input::get('siteName'));

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