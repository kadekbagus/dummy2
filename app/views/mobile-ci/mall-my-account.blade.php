@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    <div class="row vertically-spaced-3">
        <div class="col-xs-12 text-center">
            <div class="profile-img-wrapper">
                @if(count($media) > 0)
                <img src="{{ asset($media[0]->path) }}">
                @else
                <img src="{{ asset('mobile-ci/images/default_my_profile_alternate.png') }}">
                @endif
            </div>
        </div>
    </div>
    <div class="row vertically-spaced-3">
        <div class="col-xs-12 text-center">
            <p><b>{{$user_full_name}}</b></p>
        </div>
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
        });
    </script>
@stop