/**
 * Created by heimonsy on 14-10-27.
 */
$(function(){
    var elemeInitBtn = function() {
        $("#addStaticHost").unbind('click');
        $('#addStaticHost').click(function(event){
            var hostname = $("#s-hostname").val();
            var hostip = $("#s-ip").val();
            var hostport = $('#s-port').val();
            var hostype = $('#s-type').val();

            if (! /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(hostip)) {
                alert('host ip 格式错误');
                $('#s-hostip').focus();
                return false;
            }
            if (!(/^\d{1,5}$/i.test(hostport) && hostport < 65536)){
                alert("host port 格式错误");
                $("#s-hostport").val();
                return false;
            }
            $.getJSON("/host/add?hostname=" + hostname + "&hostip=" + hostip + "&hostport=" + hostport
            + "&hosttype=" + hostype + "&type=static", function(data) {
                var cls = data.hosttype == 'staging' ? 'text-info' : '';
                var htm = "<tr class=\""+cls+" needShow\" style=\"display: none;\"><td>" + data.hostname + "</td><td>" + data.hostip + "</td><td>"
                    + data.hostport + "</td><td>" + data.hosttype + "</td><td>" + data.time + "</td>" +
                    "<td><buttion type=\"button\" data-jstr='" + data.jstr + "' class=\"btn btn-sm btn-warning delHost\">删除</buttion></td></tr>";
                data.hosttype == 'staging' ? $(".table-static-list tbody").prepend(htm) : $(".table-static-list tbody").append(htm);
                $(".needShow").show('slow');
                $(".needShow").removeClass('needShow');
                elemeInitBtn();
                return false;
            });
        });
        $("#addWebHost").unbind('click');
        $('#addWebHost').click(function(event){
            var hostname = $("#w-hostname").val();
            var hostip = $("#w-ip").val();
            var hostport = $('#w-port').val();
            var hostype = $('#w-type').val();

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
            $.getJSON("/host/add?hostname=" + hostname + "&hostip=" + hostip + "&hostport=" + hostport
            + "&hosttype=" + hostype + "&type=web", function(data) {
                var cls = data.hosttype == 'staging' ? 'text-info' : '';
                var htm = "<tr class=\""+cls+" needShow\" style=\"display: none;\"><td>" + data.hostname + "</td><td>" + data.hostip + "</td><td>"
                    + data.hostport + "</td><td>" + data.hosttype + "</td><td>" + data.time + "</td>" +
                    "<td><buttion type=\"button\" data-jstr='" + data.jstr + "' class=\"btn btn-sm btn-warning delHost\">删除</buttion></td></tr>";
                data.hosttype == 'staging' ? $(".table-web-list tbody").prepend(htm) : $(".table-web-list tbody").append(htm);
                $(".needShow").show('slow');
                $(".needShow").removeClass('needShow');
                elemeInitBtn();
                return false;
            });
        });

        $(".delHost").unbind('click');
        $(".delHost").click(function(e) {
            var ttr = $(this).parent().parent();
            var jstr = $(this).attr('data-jstr');
            $.post('/host/del',{
               'jstr' : jstr
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
    };
    elemeInitBtn();
});
