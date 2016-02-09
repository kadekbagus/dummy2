@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    @if($data->status === 1)
        <div class="catalogue-wrapper">
        @foreach($data->records as $luckydraw)
            <div class="main-theme-mall catalogue catalogue-other" id="product-{{$luckydraw->lucky_draw_id}}">
                <div class="row catalogue-top">
                    <div class="col-xs-3 catalogue-img">
                        <a href="{{ url('customer/luckydraw?id='.$luckydraw->lucky_draw_id) }}">
                            <span class="link-spanner-other"></span>
                            @if(!empty($luckydraw->image))
                            <img class="img-responsive" alt="" src="{{ asset($luckydraw->image) }}">
                            @else
                            <img class="img-responsive" src="{{ asset('mobile-ci/images/default_lucky_number.png') }}"/>
                            @endif
                        </a>
                    </div>
                    <div class="col-xs-9 catalogue-info">
                        <a href="{{ url('customer/luckydraw?id='.$luckydraw->lucky_draw_id) }}">
                            <span class="link-spanner-other"></span>
                            <h4>{{ $luckydraw->lucky_draw_name }}</h4>
                            <p>
                            {{-- Limit description per two line and 64 total character --}}
                            <?php
                                $desc = explode("\n", $luckydraw->description);
                            ?>
                            @if (mb_strlen($luckydraw->description) > 64)
                                @if (count($desc) > 2)
                                    <?php
                                        $two_row = array_slice($desc, 0, 2);
                                    ?>
                                    @foreach ($two_row as $key => $value)
                                        @if ($key === 0)
                                            {{{ $value }}} <br>
                                        @else
                                            {{{ $value }}} ...
                                        @endif
                                    @endforeach
                                @else
                                    {{{ mb_substr($luckydraw->description, 0, 64, 'UTF-8') . '...' }}}
                                @endif
                            @else
                                @if (count($desc) > 2)
                                    <?php
                                        $two_row = array_slice($desc, 0, 2);
                                    ?>
                                    @foreach ($two_row as $key => $value)
                                        @if ($key === 0)
                                            {{{ $value }}} <br>
                                        @else
                                            {{{ $value }}} ...
                                        @endif
                                    @endforeach
                                @else
                                    {{{ mb_substr($luckydraw->description, 0, 64, 'UTF-8') }}}
                                @endif
                            @endif
                            </p>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
        </div>
        @if($data->returned_records < $data->total_records)
            <div class="row">
                <div class="col-xs-12 padded">
                    <button class="btn btn-info btn-block" id="load-more-x">{{Lang::get('mobileci.notification.load_more_btn')}}</button>
                </div>
            </div>
        @endif
    @else
        @if(! empty($data->custom_message))
        <div class="row padded">
            <div class="col-xs-12">
                {{ $data->custom_message }}
            </div>
        </div>
        @else
        <div class="row padded">
            <div class="col-xs-12">
                <h4>{{ Lang::get('mobileci.greetings.latest_luckydraw_coming_soon') }}</h4>
            </div>
        </div>
        @endif
    @endif
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1" role="dialog" aria-labelledby="verifyModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="verifyModalLabel"><i class="fa fa-envelope-o"></i> {{ Lang::get('mobileci.promotion.info') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <p style="font-size:15px;">
                            <b>{{ Lang::get('mobileci.modals.enjoy_free') }}</b>
                            <br>
                            @if ($active_user)
                                <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">{{ Lang::get('mobileci.modals.unlimited') }}</span>
                            @else
                                <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">30 {{ Lang::get('mobileci.modals.minutes') }}</span>
                            @endif
                            <br>
                            <b>{{ Lang::get('mobileci.modals.internet') }}</b>
                            <br><br>
                            <b>{{ Lang::get('mobileci.modals.check_out_our') }}</b>
                            <br>
                            <b><span style="color:#0aa5d5;">{{ Lang::get('mobileci.page_title.promotion') }}</span> {{ Lang::get('mobileci.page_title.and') }} <span style="color:#0aa5d5;">{{ Lang::get('mobileci.page_title.coupon_single') }}</span></b>
                        </p>
                    </div>
                </div>
                <div class="row" style="margin-left: -30px; margin-right: -30px; margin-bottom: -15px;">
                    <div class="col-xs-12">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/pop-up-banner.png') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.okay') }}</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 text-left">
                            <input type="checkbox" name="verifyModalCheck" id="verifyModalCheck" style="top:2px;position:relative;">
                            <label for="verifyModalCheck">{{ Lang::get('mobileci.modals.do_not_display') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="userActivationModal" tabindex="-1" role="dialog" aria-labelledby="userActivationModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="userActivationModalLabel"><i class="fa fa-envelope-o"></i> {{ Lang::get('mobileci.promotion.info') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <p style="font-size:15px;">
                            {{{ sprintf(Lang::get('mobileci.modals.message_user_activation'), $user_email) }}}
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.okay') }}</button>
                    </div>
                </div>
            </div>
        </div>
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

        $(document).on('show.bs.modal', '.modal', function (event) {
            var zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack').css('z-index', 0).addClass('modal-stack');
            }, 0);
        });
        {{-- set cookie if modal permanently hidden --}}
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

        $('#load-more-x').click(function(){
            loadMoreX('lucky-draw');
        });
    });

</script>
@stop
