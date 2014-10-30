/**
 * Created by heimonsy on 14-10-27.
 */
$(function(){
    jQuery.fn.isValueNotEmpty = function() {
        if ($.trim($(this).val()) == "") {
            $(this).focus();
            alert($(this).attr('placeholder') + "不能为空");
            return false;
        }
        return true;
    };
    var elemeInitBtn = function() {
        $("#addStaticHost").unbind('click');
        $('#addStaticHost').click(function(event){
            var hostname = $("#s-hostname").val();
            var hostip = $("#s-ip").val();
            var hostport = $('#s-port').val();
            var hostype = $('#s-type').val();
            var siteId = $('#s-siteId').val();

            if (! /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(hostip)) {
                alert('host ip 格式错误');
                $('#s-hostip').focus();
                return false;
            }
            if (!(/^\d{1,5}$/i.test(hostport) && hostport < 65536)) {
                alert("host port 格式错误");
                $("#s-hostport").val();
                return false;
            }
            return true;
        });

        $("#addWebHost").unbind('click');
        $('#addWebHost').click(function(event){
            var hostname = $("#w-hostname").val();
            var hostip = $("#w-ip").val();
            var hostport = $('#w-port').val();
            var hostype = $('#w-type').val();
            var siteId = $('#w-siteId').val();

            if (! /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(hostip)) {
                alert('host ip 格式错误');
                $('#w-hostip').focus();
                return false;
            }
            if (!(/^\d{1,5}$/i.test(hostport) && hostport < 65536)){
                alert("host port 格式错误");
                $("#w-hostport").val();
                return false;
            }
            return true;
        });

        $(".delHost").unbind('click');
        $(".delHost").click(function(e) {
            var ttr = $(this).parent().parent();
            var jstr = $(this).attr('data-jstr');
            var siteId = $(this).attr('data-id');
            $.post('/host/del',{
                'jstr' : jstr,
                'siteId' : siteId
            }, function(data){
                ttr.hide('slow', function(e){
                    ttr.remove();
                })
            }, 'json');
        });

        $('#deployCommit').unbind('click');
        $("#deployCommit").click(function(e) {
            if ($('#s-commit').val()=='') {
                alert('请选择commit version');
                return false;
            }
            return true;
        });

        $('.delHostType').unbind('click');
        $(".delHostType").click(function(e) {
            var hostType = $(this).attr('data-id');
            var hostTypeLi = $(this).parent().parent();
            $.post('/hostType/del',{
                'hostType' : hostType
            }, function(data){
                if (data.res == 0) {
                    hostTypeLi.remove();
                } else {
                    alert('删除失败');
                }
            }, 'json');
        });

        $('#addHostType').unbind();
        $('#addHostType').click(function(){
            return $("#hostType").isValueNotEmpty();
        });
        $('#addSite').unbind();
        $('#addSite').click(function(){
            var siteId = $("#siteId").val();
            if (/[\w\d_]+/i.test(siteId) == false) {
                alert('Site Id格式错误');
                return false;
            }
            return $('#siteName').isValueNotEmpty();

        });
        $('.delSite').unbind();
        $('.delSite').click(function(){
            var siteTr = $(this).parent().parent();
            var siteName = $(this).attr("data-name");
            if (confirm("确定要删除站点"+siteName+"吗？")) {
                $.post('/site/del', {
                    'siteId': $(this).attr("data-id"),
                    'siteName': $(this).attr("data-name")
                }, function (data) {
                    if (data.res == 0) {
                        siteTr.remove();
                    } else {
                        alert('删除失败');
                    }
                }, 'json');
            }
        });
        $('#siteConfigSave').unbind();
        $('#siteConfigSave').click(function(){
            $('.saveConfigInfo').hide();
            $.post('/config/save', $("#configSaveForm").serialize() , function (data) {
                if (data.res == 0) {
                    $('.saveConfigInfo').fadeIn('slow');
                } else {
                    alert(data.errMsg);
                }
            }, 'json');
        });

        $('#saveSysConfig').unbind();
        $('#saveSysConfig').click(function(){
            $.post('/system/config/save', $("#systemConfigForm").serialize() , function (data) {
                if (data.res == 0) {
                    $('.systemConfigInfo').fadeIn('slow');
                } else {
                    alert(data.errMsg);
                }
            }, 'json');

        });
    };
    elemeInitBtn();
});
