@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    <div id="delete-bar" class="row text-right">
        <div class="col-xs-10 text-delete-mode">
            <p>Delete Mode</p>
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
    <div class="col-xs-12 text-center vertically-spaced" style="display:none;" id="no-notification">There is no notification right now.</div>
    <div class="row">
        <button class="col-xs-offset-2 col-xs-8 btn btn-default loadmore">Load More...</button>
    </div>

@stop

@section('ext_script_bot')
    {{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
    {{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
    {{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
    <script type="text/javascript">
        var cookie_dismiss_name = 'dismiss_verification_popup';
        var cookie_dismiss_name_2 = 'dismiss_activation_popup';
        var skip = 0;
        var total_page = 0;
        @if ($active_user)
        cookie_dismiss_name = 'dismiss_verification_popup_unlimited';
        @endif

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

        $(document).ready(function(){
            function getNotifList() {
                $.ajax({
                    method: 'GET',
                    url: apiPath + 'inbox/list'
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
                            var individualList = '<div class="main-theme-mall list-notification" id="notification-'+inBox.inbox_id+'"><div class="row catalogue-top"><a class="link-detail" href="{{ url('/customer/message/detail?id=') }}'+inBox.inbox_id+'"><div class="col-xs-3 notification-icon"><span class="fa-stack fa-lg '+read+'"><i class="fa fa-circle fa-stack-2x circle"></i><i class="fa fa-'+mark+' fa-stack-1x symbol"></i></span></div><div class="col-xs-8 notification-title" style=""><h4 class="'+read+'">'+inBox.subject+'</h4></div></a><div class="col-xs-1 deleteNotif" data-id="'+inBox.inbox_id+'"><span class="delete-button-child"><i class="fa fa-times"></i></span></div></div></div>';
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
                    console.log('xxx');
                    $('#spinner').hide();
                });
            }
            
            getNotifList();

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
                        var individualList = '<div class="main-theme-mall list-notification" id="notification-'+inBox.inbox_id+'"><div class="row catalogue-top"><a class="link-detail" href="{{ url('/customer/message/detail?id=') }}'+inBox.inbox_id+'"><div class="col-xs-3 notification-icon"><span class="fa-stack fa-lg '+read+'"><i class="fa fa-circle fa-stack-2x circle"></i><i class="fa fa-'+mark+' fa-stack-1x symbol"></i></span></div><div class="col-xs-8 notification-title" style=""><h4 class="'+read+'">'+inBox.subject+'</h4></div></a><div class="col-xs-1 deleteNotif" data-id="'+inBox.inbox_id+'"><span class="delete-button-child"><i class="fa fa-times"></i></span></div></div></div>';
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
                console.log('x');
                if(openDelete){
                    console.log('y');
                    e.preventDefault();
                }
            });

            $('#verifyModal').on('hidden.bs.modal', function () {
                if ($('#verifyModalCheck')[0].checked) {
                    $.cookie(cookie_dismiss_name, 't', {expires: 3650});
                }
            });

            $('#userActivationModal').on('hidden.bs.modal', function () {
                $.cookie(cookie_dismiss_name_2, 't', {path: '/', domain: window.location.hostname, expires: 3650});
            });

            {{-- a sequence of modals... --}}
            var modals = [
                {
                    selector: '#verifyModal',
                    display: get('internet_info') == 'yes' && !$.cookie(cookie_dismiss_name)
                },
                {
                    selector: '#userActivationModal',
                    @if ($active_user)
                        display: false
                    @else
                        display: get('from_login') === 'yes' && !$.cookie(cookie_dismiss_name_2)
                    @endif
                }
            ];
            var modalIndex;

            for (modalIndex = 0; modalIndex < modals.length; modalIndex++) {
                {{-- for each displayable modal, after it is hidden try and display the next displayable modal --}}
                if (modals[modalIndex].display) {
                    $(modals[modalIndex].selector).on('hidden.bs.modal', (function(myIndex) {
                        return function() {
                            for (var i = myIndex + 1; i < modals.length; i++) {
                                if (modals[i].display) {
                                    $(modals[i].selector).modal();
                                    return;
                                }
                            }
                        }
                    })(modalIndex));
                }
            }

            {{-- display the first displayable modal --}}
            for (modalIndex = 0; modalIndex < modals.length; modalIndex++) {
                if (modals[modalIndex].display) {
                    $(modals[modalIndex].selector).modal();
                    break;
                }
            }
            var openDelete = false;
            $('body').on('click', '.delete-button-parent', function(e){
                openDelete = !openDelete ? true : false;
                $('.delete-button-child').animate({
                    width: [ "toggle", "swing" ],
                    height: [ "toggle", "swing" ],
                    opacity: "toggle"
                }, 300);
                $('.delete-button-parent').toggleClass('active');
            });
        });
    </script>
@stop