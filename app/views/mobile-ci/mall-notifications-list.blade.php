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
        <button class="col-xs-offset-2 col-xs-8 btn btn-default loadmore">{{ Lang::get('mobileci.notification.load_more_btn') }}</button>
    </div>

@stop

@section('ext_script_bot')
    <script type="text/javascript">
        notInMessagesPage = false;
        var skip = 0;
        var total_page = 0;
        /**
         * Get Query String from the URL
         *
         * @author Rio Astamal <me@rioastamal.net>
         * @param string n - Name of the parameter
         */
        function get(n)
        {
            var half = location.search.split(n + '=')[1];
            return half !== undefined ? decodeURIComponent(half.split('&')[0]) : null;
        }

        function updateQueryStringParameter(uri, key, value) {
            var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
            var separator = uri.indexOf('?') !== -1 ? "&" : "?";
            if (uri.match(re)) {
                return uri.replace(re, '$1' + key + "=" + value + '$2');
            } else {
                return uri + separator + key + "=" + value;
            }
        }

        {{-- force reload page to disable page cache on ios safari --}}
        $(window).bind("pageshow", function(event) {
            if (event.originalEvent.persisted) {
                window.location.reload()
            }
        });

        $(document).ready(function(){
            function getNotifList() {
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
                            var inBox = data.data.records[i];
                            var isRead = inBox.is_read == 'Y' ? true : false;
                            var read = isRead ? 'read' : 'unread';
                            var mark = isRead ? 'check' : 'exclamation';
                            var readUnread = isRead ? 'read-unread' : '';
                            var individualList = '<div class="main-theme-mall list-notification" id="notification-'+inBox.inbox_id+'"><div class="row catalogue-top"><a data-id='+inBox.inbox_id+' class="'+readUnread+'"><div class="col-xs-3 notification-icon text-center"><span class="fa-stack fa-lg '+read+'"><i class="fa fa-circle fa-stack-2x circle"></i><i class="fa fa-'+mark+' fa-stack-1x symbol"></i></span></div></a><a class="link-detail" href="{{ url('/customer/message/detail?id=') }}'+inBox.inbox_id+'"><div class="col-xs-8 notification-title" style=""><h4 class="'+read+'">'+inBox.subject+'</h4></div></a><div class="col-xs-1 deleteNotif" data-id="'+inBox.inbox_id+'"><span class="delete-button-child"><i class="fa fa-times"></i></span></div></div></div>';
                            $('#notification').append(individualList);
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
            }

            getNotifList();

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

            $('body').on('click', '.deleteNotif', function(e){
                $('body').addClass('modal-open');
                var inbox_id = $(this).data('id');
                $.ajax({
                    method: 'POST',
                    url: apiPath + 'inbox/delete',
                    data: {
                        inbox_id: inbox_id
                    }
                }).done(function(data){
                    if(data.status === 'success') {
                        $('#notification-'+inbox_id).fadeOut('slow', function(){
                            $('#notification-'+inbox_id).remove();
                        });
                    }
                }).always(function(data){
                    $('body').removeClass('modal-open');
                });
            });

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
                        var inBox = data.data.records[i];
                        var isRead = inBox.is_read == 'Y' ? true : false;
                        var read = isRead ? 'read' : 'unread';
                        var mark = isRead ? 'check' : 'exclamation';
                        var readUnread = isRead ? 'read-unread' : '';
                        var individualList = '<div class="main-theme-mall list-notification" id="notification-'+inBox.inbox_id+'"><div class="row catalogue-top"><a data-id='+inBox.inbox_id+' class="'+readUnread+'"><div class="col-xs-3 notification-icon text-center"><span class="fa-stack fa-lg '+read+'"><i class="fa fa-circle fa-stack-2x circle"></i><i class="fa fa-'+mark+' fa-stack-1x symbol"></i></span></div></a><a class="link-detail" href="{{ url('/customer/message/detail?id=') }}'+inBox.inbox_id+'"><div class="col-xs-8 notification-title" style=""><h4 class="'+read+'">'+inBox.subject+'</h4></div></a><div class="col-xs-1 deleteNotif" data-id="'+inBox.inbox_id+'"><span class="delete-button-child"><i class="fa fa-times"></i></span></div></div></div>';
                        $('#notification').append(individualList);
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