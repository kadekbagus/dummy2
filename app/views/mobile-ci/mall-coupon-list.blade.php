@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
            @if($data->status === 1)
                @if(sizeof($data->records) > 0)
                    @foreach($data->records as $coupon)
                        <div class="col-xs-12 col-sm-12" id="item-{{$coupon->issued_coupon_id}}">
                            <section class="list-item-single-tenant">
                                <a class="list-item-link" href="{{ url('customer/mallcoupon?id='.$coupon->issued_coupon_id) }}">
                                    <div class="list-item-info">
                                        <header class="list-item-title">
                                            <div><strong>{{ $coupon->promotion_name }}</strong></div>
                                        </header>
                                        <header class="list-item-subtitle">
                                            <div>
                                                {{-- Limit description per two line and 45 total character --}}
                                                <?php
                                                    $desc = explode("\n", $coupon->description);
                                                ?>
                                                @if (mb_strlen($coupon->description) > 45)
                                                    @if (count($desc) > 1)
                                                        <?php
                                                            $two_row = array_slice($desc, 0, 1);
                                                        ?>
                                                        @foreach ($two_row as $key => $value)
                                                            @if ($key === 0)
                                                                {{{ $value }}} <br>
                                                            @else
                                                                {{{ $value }}} ...
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        {{{ mb_substr($coupon->description, 0, 45, 'UTF-8') . '...' }}}
                                                    @endif
                                                @else
                                                    @if (count($desc) > 1)
                                                        <?php
                                                            $two_row = array_slice($desc, 0, 1);
                                                        ?>
                                                        @foreach ($two_row as $key => $value)
                                                            @if ($key === 0)
                                                                {{{ $value }}} <br>
                                                            @else
                                                                {{{ $value }}} ...
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        {{{ mb_substr($coupon->description, 0, 45, 'UTF-8') }}}
                                                    @endif
                                                @endif
                                            </div>
                                        </header>
                                    </div>
                                    <div class="list-vignette-non-tenant"></div>
                                    @if(!empty($coupon->image))
                                    <img class="img-responsive img-fit-tenant" src="{{ asset($coupon->image) }}" />
                                    @else
                                    <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_coupon.png') }}"/>
                                    @endif
                                </a>
                            </section>
                        </div>
                    @endforeach
                @else
                    <div class="row padded">
                        <div class="col-xs-12">
                            <h4>{{ Lang::get('mobileci.greetings.how_to_get_coupons') }}</h4>
                        </div>
                    </div>
                @endif
            @else
                <div class="row padded">
                    <div class="col-xs-12">
                        <h4>{{ Lang::get('mobileci.greetings.how_to_get_coupons') }}</h4>
                    </div>
                </div>
            @endif
            </div>
        </div>
    </div>
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="hasCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="hasCouponLabel">{{ Lang::get('mobileci.modals.coupon_title') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced">
                        <p></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <input type="hidden" name="detail" id="detail" value="">
                    <div class="col-xs-6">
                        <button type="button" id="applyCoupon" class="btn btn-success btn-block">{{ Lang::get('mobileci.modals.coupon_use') }}</button>
                    </div>
                    <div class="col-xs-6">
                        <button type="button" id="denyCoupon" class="btn btn-danger btn-block">{{ Lang::get('mobileci.modals.coupon_ignore') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
                    <div class="col-xs-12 text-left">
                        <p>
                            <input type="checkbox" name="verifyModalCheck" id="verifyModalCheck"> <span>Do not display this message again</span>
                        </p>
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
        var fromLogin = $.cookie('orbit_from_login');
        $.removeCookie('orbit_from_login', {path: '/'});
        var displayModal = (fromLogin === '1');
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
        if (!$.cookie(cookie_dismiss_name)) {
            if (displayModal) {
                $('#verifyModal').on('hidden.bs.modal', function () {
                    if ($('#verifyModalCheck')[0].checked) {
                        $.cookie(cookie_dismiss_name, 't', {expires: 3650});
                    }
                }).modal();
            }
        }
        $('.catalogue-img img').each(function(){
            var h = $(this).height();
            var ph = $('.catalogue').height();
            $(this).css('margin-top', ((ph-h)/2) + 'px');
        });
    }); 
    
    $(window).resize(function(){
        $('.catalogue-img img').each(function(){
            var h = $(this).height();
            var ph = $('.catalogue').height();
            $(this).css('margin-top', ((ph-h)/2) + 'px');
        });
    });
</script>
@stop
