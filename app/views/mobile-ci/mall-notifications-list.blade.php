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
        @for ($i = 1; $i <= 10; $i++)
            <div class="main-theme-mall list-notification" id="notification-{{ $i }}">
                <div class="row catalogue-top">
                    <a href="{{ url('/customer/notification/detail') }}#{{ $i }}">
                        <div class="col-xs-3 notification-icon">
                            @if ($i%2)
                                <span class="fa-stack fa-lg read">
                                  <i class="fa fa-circle fa-stack-2x circle"></i>
                                  <i class="fa fa-check fa-stack-1x symbol"></i>
                                </span>
                            @else
                                <span class="fa-stack fa-lg unread">
                                  <i class="fa fa-circle fa-stack-2x circle"></i>
                                  <i class="fa fa-exclamation fa-stack-1x symbol"></i>
                                </span>
                            @endif
                        </div>
                        <div class="col-xs-8 notification-title" style="">
                            @if ($i%2)
                                <h4>Notifications {{ $i }}</h4>
                            @else
                                <h4 class="unread">Notifications {{ $i }}</h4>
                            @endif
                        </div>
                        <div class="col-xs-1">
                            <span class="delete-button-child">
                                <i class="fa fa-times"></i>
                            </span>
                        </div>
                    </a>
                </div>
            </div>
        @endfor
    </div>
@stop

@section('ext_script_bot')
    {{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
    {{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
    {{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
    <script type="text/javascript">
        var cookie_dismiss_name = 'dismiss_verification_popup';
        var cookie_dismiss_name_2 = 'dismiss_activation_popup';

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


            var path = '{{ url('/customer/tenants?keyword='.Input::get('keyword').'&sort_by=name&sort_mode=asc&cid='.Input::get('cid').'&fid='.Input::get('fid')) }}';
            $('#dLabel').dropdown();
            $('#dLabel2').dropdown();
            $('#category>li').click(function(){
                if(!$(this).data('category')) {
                    $(this).data('category', '');
                }
                path = updateQueryStringParameter(path, 'cid', $(this).data('category'));
                console.log(path);
                window.location.replace(path);
            });
            $('#floor>li').click(function(){
                if(!$(this).data('floor')) {
                    $(this).data('floor', '');
                }
                path = updateQueryStringParameter(path, 'fid', $(this).data('floor'));
                console.log(path);
                window.location.replace(path);
            });

            $('.delete-button-parent').click(function(){
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