@extends('mobile-ci.layout')

@section('content')
<div id="delete-bar" class="row text-right">
    <div class="col-xs-10 text-delete-mode">
        <p class="delete-mode">{{ Lang::get('mobileci.notification.delete_mode') }}</p>
        <p class="read-mode">{{ Lang::get('mobileci.notification.read_mode') }}</p>
    </div>
    <div class="col-xs-2 button-delete-mode">
        <span class="delete-button-parent">
            <i id="delete-icon" class="fa fa-trash"></i>
        </span>
    </div>
</div>
<div id="notification">
</div>
<div class="col-xs-12 text-center" id="spinner"><i class="fa fa-circle-o-notch fa-spin"></i></div>
<div class="col-xs-12 text-center vertically-spaced" style="display:none;" id="no-notification">{{ Lang::get('mobileci.notification.no_notif') }}</div>
<div class="row">
    <button class="col-xs-offset-2 col-xs-8 btn btn-default loadmore">{{ Lang::get('mobileci.notification.view_more_btn') }}</button>
</div>
@stop

@section('ext_script_bot')
    <script type="text/javascript">
        // this var is used to enable/disable pop up notification
        notInMessagesPage = false;

        var skip = 0,
            total_page = 0;

        var deleteNotification = function () {
            var inbox_id = $(this).data('id');
            var $notificationList = $('#notification-' + inbox_id);
            var $body = $('body');

            $body.addClass('modal-open');

            $.ajax({
                method: 'POST',
                url: apiPath + 'inbox/delete',
                data: {
                    inbox_id: inbox_id
                }
            })
            .done(function(data){
                if(data.status === 'success') {
                    $notificationList.fadeOut('slow', function(){
                        $notificationList.remove();
                    });
                }
            })
            .always(function(data){
                $body.removeClass('modal-open');
            });
        }

        var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

        var printDate = function (date) {
            if (date instanceof Date) {
                var day = date.getDate().toString();
                var mth = monthNames[date.getMonth()];
                var yr = date.getFullYear();
                var hr = date.getHours().toString();
                var min = date.getMinutes().toString();
                var sec = date.getSeconds().toString();

                return (day[1]?day:"0"+day[0]) + ' ' + mth + ' ' + yr + ' ' + (hr[1]?hr:"0"+hr[0]) + ':' + (min[1]?min:"0"+min[0]) + ':' + (sec[1]?sec:"0"+sec[0]);
            }
            return null;
        }

        var parseDate = function (strDate) {
            var parts = strDate.match(/([0-9]+)/g);

            // This is still in UTC
            var date = new Date(parts[0], parts[1], parts[2], parts[3], parts[4], parts[5]);

            // Convert it to local browser
            date.setMinutes(date.getMinutes() - (new Date()).getTimezoneOffset());

            return date;
        };

        var generateListNotification = function (inbox) {
            console.log(inbox.created_at);

            var inboxId = inbox.inbox_id;
            var subject = inbox.subject;
            var isRead = inbox.is_read == 'Y' ? true : false;

            var read = isRead ? 'read' : 'unread';
            var mark = isRead ? 'check' : 'exclamation';
            var readUnread = isRead ? 'read-unread' : '';
            var createdDate = parseDate(inbox.created_at);

            var $listDivNotification = $('<div />').attr({
                'id': 'notification-' + inboxId,
                'class': 'main-theme-mall list-notification'
            });
            var $topDivCatalogue = $('<div />').addClass('row catalogue-top');

            var $divNotif = $('<div />').addClass('col-xs-3 notification-icon text-center');
            var $linkWrapper = $('<a />').attr({
                'data-id': inboxId,
                'class': readUnread
            });
            var $spanNotif = $('<span />').attr({
                'class': 'fa-stack fa-lg ' + read
            }).append(
                $('<i />').addClass('fa fa-circle fa-stack-2x circle')
            ).append(
                $('<i />').attr({
                    'class': 'fa fa-' + mark + ' fa-stack-1x symbol'
                })
            );

            var $divTitle = $('<div />').addClass('col-xs-8 notification-title');
            var $linkDetail = $('<a />').attr({
                'class': 'link-detail',
                'href': '{{ url('/customer/message/detail?id=') }}' + inboxId
            });
            var $titleHeader = $('<h4 />').attr({
                'class': read
            }).text(subject);
            var $titleSubheader = $('<small />').text(printDate(createdDate));

            var $divDeleteNotif = $('<div />').attr({
                'class': 'col-xs-1 deleteNotif',
                'data-id': inboxId
            });
            var $spanDeleteBtn = $('<span />').attr({
                'class': 'delete-button-child'
            }).append(
                $('<i />').addClass('fa fa-times')
            );

            $linkWrapper.append($spanNotif);
            $divNotif.append($linkWrapper);

            $linkDetail.append($titleHeader);
            $linkDetail.append($titleSubheader);
            $divTitle.append($linkDetail);

            $divDeleteNotif.append($spanDeleteBtn);

            $topDivCatalogue.append($divNotif);
            $topDivCatalogue.append($divTitle);
            $topDivCatalogue.append($divDeleteNotif);

            $listDivNotification.append($topDivCatalogue);
            return $listDivNotification;
        };

        $(document).ready(function(){
            $.ajax({
                method: 'GET',
                url: '{{ url("app/v1/inbox/list") }}'
            }).done(function(data){
                if(data.data.total_records / (data.data.returned_records + skip) > 1) {
                    $('.loadmore').show();
                } else {
                    $('.loadmore').hide();
                }
                if(data.data.records) {
                    for(var i = 0; i < data.data.records.length; i++) {
                        var inbox = data.data.records[i];
                        var $individualList = generateListNotification(inbox);
                        $('#notification').append($individualList);
                    }
                    skip = skip + {{ Config::get('orbit.pagination.inbox.per_page', 15) }};
                } else {
                    $('#no-notification').show();
                }
                $('#spinner').hide();
            }).fail(function(data){
                $('#spinner').hide();
            }).always(function(data){
                $('#spinner').hide();
            });

            $('body').on('click', '.read-unread', function(e){
                $('body').addClass('modal-open');
                var inbox_id = $(this).data('id');
                $.ajax({
                    method: 'POST',
                    url: apiPath + 'inbox/read-unread',
                    data: {
                        inbox_id: inbox_id
                    }
                }).done(function(data){
                    if(data.status === 'success') {
                        if (data.data === 'read') {
                            $('#notification-'+inbox_id+' .link-detail h4').addClass('read');
                            $('#notification-'+inbox_id+' .link-detail h4').removeClass('unread');
                            $('#notification-'+inbox_id+' .read-unread .fa-stack').removeClass('unread');
                            $('#notification-'+inbox_id+' .read-unread .fa-stack').addClass('read');
                            $('#notification-'+inbox_id+' .read-unread .fa-stack .fa-stack-1x').addClass('fa-check');
                            $('#notification-'+inbox_id+' .read-unread .fa-stack .fa-stack-1x').removeClass('fa-exclamation');
                        } else {
                            $('#notification-'+inbox_id+' .link-detail h4').addClass('unread');
                            $('#notification-'+inbox_id+' .link-detail h4').removeClass('read');
                            $('#notification-'+inbox_id+' .read-unread .fa-stack').removeClass('read');
                            $('#notification-'+inbox_id+' .read-unread .fa-stack').addClass('unread');
                            $('#notification-'+inbox_id+' .read-unread .fa-stack .fa-stack-1x').addClass('fa-exclamation');
                            $('#notification-'+inbox_id+' .read-unread .fa-stack .fa-stack-1x').removeClass('fa-check');
                            $('#notification-'+inbox_id+' .read-unread').removeClass('read-unread'); // disable read on unread notif
                        }
                    }
                }).always(function(data){
                    $('body').removeClass('modal-open');
                });
            });

            $('body').on('click', '.deleteNotif', deleteNotification);

            $('body').on('click', '.loadmore', function(e){
                param = 'take={{ Config::get('orbit.pagination.inbox.per_page', 15) }}';
                param += '&skip='+skip;
                var loadmoreBtn = $(this);
                loadmoreBtn.prop('disabled', true)
                $.ajax({
                    method: 'GET',
                    url: apiPath + 'inbox/list?' + param
                }).done(function(data){
                    if(data.data.total_records / (data.data.returned_records + skip) > 1) {
                        $('.loadmore').show();
                    } else {
                        $('.loadmore').hide();
                    }
                    for(var i = 0; i < data.data.records.length; i++) {
                        var inbox = data.data.records[i];
                        var $individualList = generateListNotification(inbox);
                        $('#notification').append($individualList);

                        if(openDelete){
                            $('.delete-button-child').css('display', 'inline-block');
                        } else {
                            $('.delete-button-child').css('display', 'none');
                        }
                    }
                    skip = skip + {{ Config::get('orbit.pagination.inbox.per_page', 15) }};
                }).fail(function(data){
                    $('#spinner').hide();
                }).always(function(data){
                    $('#spinner').hide();
                    loadmoreBtn.attr('disabled',false);
                });
            });

            $('body').on('click', '.link-detail', function(e){
                if(openDelete){
                    e.preventDefault();
                }
            });

            var openDelete = false;
            $('body').on('click', '.button-delete-mode', function(e){
                openDelete = !openDelete ? true : false;
                $('.delete-button-child').animate({
                    width: [ "toggle", "swing" ],
                    height: [ "toggle", "swing" ],
                    opacity: "toggle"
                }, 300);
                $('.delete-button-parent').toggleClass('active');
                $('.text-delete-mode').toggleClass('active');
            });
        });
    </script>
@stop