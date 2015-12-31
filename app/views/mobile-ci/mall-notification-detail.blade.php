@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    {{ $inbox->content }}

    @if($inbox->inbox_type == 'lucky_draw_issuance')
    <div class="row vertically-spaced">
        <div class="col-xs-12 padded">
        <a href="{{ url('customer/luckydraws') }}" class="btn btn-block btn-info">{{ Lang::get('mobileci.notification.view_lucky_draw_btn') }}</a>
        </div>
    </div>
    @elseif($inbox->inbox_type == 'coupon_issuance')
    <div class="row vertically-spaced">
        <div class="col-xs-12 padded">
        <a href="{{ url('customer/mallcoupons') }}" class="btn btn-block btn-info">{{ Lang::get('mobileci.notification.view_coupons_btn') }}</a>
        </div>
    </div>
    @endif
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
        });
    </script>
@stop