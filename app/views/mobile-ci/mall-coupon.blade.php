@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    <style type="text/css">
        .modal-spinner{
            display: none;
            font-size: 2.5em;
            color: #fff;
            position: absolute;
            top: 50%;
            margin: 0 auto;
            width: 100%;
        }
        .tenant-list{
            margin:0;padding:0;
        }
        .tenant-list li{
            list-style: none;
        }
    </style>
@stop

@section('fb_scripts')
@if(! empty($facebookInfo))
@if(! empty($facebookInfo['version']) && ! empty($facebookInfo['app_id']))
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version={{$facebookInfo['version']}}&appId={{$facebookInfo['app_id']}}";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
@endif
@endif
@stop

@section('content')
<div class="row relative-wrapper">
    <div class="actions-container" style="z-index: 102;">
        <div class="circle-plus action-btn">
            <div class="circle">
                <div class="horizontal"></div>
                <div class="vertical"></div>
            </div>
        </div>
        <div class="actions-panel" style="display: none;">
            <ul class="list-unstyled">
                <li>
                    @if(count($link_to_tenants) > 0)
                        @if(count($link_to_tenants) === 1)
                        <a data-href="{{ route('ci-tenant-detail', ['id' => $link_to_tenants[0]->retailer_id]) }}" href="{{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-tenant-detail', ['id' => $link_to_tenants[0]->retailer_id], $session) }}}">
                        @else
                        <a data-href="{{ route('ci-tenant-list', ['coupon_id' => $coupon->promotion_id]) }}" href="{{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-tenant-list', ['coupon_id' => $coupon->promotion_id], $session) }}}">
                        @endif
                            <span class="fa fa-stack icon">
                                <i class="fa fa-circle fa-stack-2x"></i>
                                <i class="fa fa-shopping-cart fa-inverse fa-stack-1x"></i>
                            </span>
                            <span class="text">{{{ Lang::get('mobileci.tenant.see_tenants') }}}</span>
                        </a>
                    @else
                        <!-- Tenant not found -->
                    <a class="disabled">
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-shopping-cart fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{{ Lang::get('mobileci.tenant.see_tenants') }}}</span>
                    </a>
                    @endif
                </li>
                @if(count($issued_coupons) > 0)
                <li>
                    @if(count($tenants) === 1 && ! $cs_reedem)
                    <a data-href="{{ route('ci-tenant-detail', ['id' => $tenants[0]->retailer_id]) }}" href="{{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-tenant-detail', ['id' => $tenants[0]->retailer_id], $session) }}}">
                    @else
                    <a data-href="{{ route('ci-tenant-list', ['coupon_redeem_id' => $coupon->promotion_id]) }}" href="{{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-tenant-list', ['coupon_redeem_id' => $coupon->promotion_id], $session) }}}">
                    @endif
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-laptop fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{{ Lang::get('mobileci.tenant.redemption_places') }}}</span>
                    </a>
                </li>
                <li>
                    <a id="useBtn">
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-scissors fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{{ Lang::get('mobileci.coupon.use_coupon') }}}</span>
                    </a>
                </li>
                @endif
                @if ($is_logged_in)
                    @if(! empty($coupon->facebook_share_url))
                    <li>
                        <div class="fb-share-button" data-href="{{$coupon->facebook_share_url}}" data-layout="button"></div>
                    </li>
                    @endif
                @endif
            </ul>
        </div>
    </div>
    <div class="col-xs-12 product-detail img-wrapper" style="z-index: 100;">
      <div class="vertical-align-middle-outer">
        <div class="vertical-align-middle-inner">
            @if(($coupon->image!='mobile-ci/images/default_coupon.png'))
            <a href="{{{ asset($coupon->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive" alt="" src="{{{ asset($coupon->image) }}}" ></a>
            @else
            <img class="img-responsive" alt="" src="{{{ asset($coupon->image) }}}" >
            @endif
        </div>
      </div>
    </div>
</div>
<div class="row product-info padded" style="z-index: 101;">
    <div class="col-xs-12">
        <div class="row">
            <div class="col-xs-12">
                <p>{{ nl2br(e($coupon->description)) }}</p>
            </div>
            <div class="col-xs-12">
                <p>{{ nl2br(e($coupon->long_description)) }}</p>
            </div>
            <div class="col-xs-12">
                <h4><strong>{{{ Lang::get('mobileci.coupon_detail.validity_label') }}}</strong></h4>
                <p>{{{ date('d M Y', strtotime($coupon->begin_date)) }}} - {{{ date('d M Y', strtotime($coupon->end_date)) }}}</p>
            </div>
        </div>
    </div>
</div>
<!-- end of product -->
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="hasCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-spinner text-center">
        <i class="fa fa-circle-o-notch fa-spin"></i>
    </div>
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{{ Lang::get('mobileci.coupon.close') }}}</span></button>
                <h4 class="modal-title" id="hasCouponLabel">{{{ Lang::get('mobileci.coupon.use_coupon') }}}</h4>
            </div>
            <div class="modal-body">
                <div class="row select-tenant">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4>{{{ Lang::get('mobileci.coupon.enter_tenant_verification_number') }}}</h4>
                        <small>{{{ Lang::get('mobileci.coupon.ask_our_tenant_employee') }}}</small>
                        <div class="form-data">
                            <input type="password" class="form-control text-center" id="tenantverify" style="font-size:20px;">
                        </div>
                    </div>
                </div>
                <div class="row select-tenant-error" style="display:none;">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4></h4>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <input type="hidden" name="detail" id="detail" value="">
                    <div class="col-xs-12">
                        <button type="button" id="applyCoupon" class="btn btn-info btn-block">{{{ Lang::get('mobileci.coupon.validate') }}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="wrongCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{{ Lang::get('mobileci.coupon.close') }}}</span></button>
                <h4 class="modal-title" id="hasCouponLabel">{{{ Lang::get('mobileci.coupon.use_coupon') }}}</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4 style="color:#d9534f" id="errMsg">{{{ Lang::get('mobileci.coupon.wrong_verification_number') }}}</h4>
                        <small>{{{ Lang::get('mobileci.coupon.please_check_tenant') }}}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="successCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{{ Lang::get('mobileci.coupon.close') }}}</span></button>
                <h4 class="modal-title" id="hasCouponLabel">{{{ Lang::get('mobileci.coupon.use_coupon') }}}</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4 style="color:#33cc99">{{{ Lang::get('mobileci.coupon.successful') }}}</h4>
                        <small>{{{ Lang::get('mobileci.coupon.please_communicate') }}}</small>
                        <div class="form-data">
                            <input id="issuecouponno" type="text" class="form-control text-center" style="font-size:20px;" value="" disabled>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
    {{ HTML::script(Config::get('orbit.cdn.featherlight.1_0_3', 'mobile-ci/scripts/featherlight.min.js')) }}
    {{-- Script fallback --}}
    <script>
        if (typeof $().featherlight === 'undefined') {
            document.write('<script src="{{asset('mobile-ci/scripts/featherlight.min.js')}}">\x3C/script>');
        }
    </script>
    {{-- End of Script fallback --}}
    <script type="text/javascript">
        $(document).ready(function(){
            // Set fromSource in localStorage.
            localStorage.setItem('fromSource', 'mall-coupon');

            // Actions button event handler
            $('.action-btn').on('click', function() {
                $('.actions-container').toggleClass('alive');
                $('.actions-panel').slideToggle();
            });

            setTimeout(function() {
                $('.actions-container').fadeIn();
            }, 500);

            $(window).scroll(function(){
                s = $(window).scrollTop();
                $('.product-detail img').css('-webkit-transform', 'translateY('+(s/3)+'px)');
            });

            $('#useBtn').on('click', function() {
                $('#hasCouponModal').modal();
            });

            @if(count($issued_coupons) > 0)
            $('#applyCoupon').click(function (){
                $('#hasCouponModal .modal-content').css('display', 'none');
                $('#hasCouponModal .modal-spinner').css('display', 'block');

                $.ajax({
                    url: apiPath + 'issued-coupon/redeem',
                    method: 'POST',
                    data: {
                        issued_coupon_id: '{{$issued_coupons[0]->issued_coupon_id}}',
                        merchant_verification_number: $('#tenantverify').val(),
                        current_mall: '{{$retailer->merchant_id}}'
                    }
                })
                .done(function(data){
                    if(data.status == 'success') {
                        $('#successCouponModal').modal({
                            backdrop: 'static',
                            keyboard: false
                        });

                        $('#successCouponModal').on('shown.bs.modal', function ($event) {
                            $('#issuecouponno').val(data.data.issued_coupon_code);
                            $('#denyCoupon').html('<i class="fa fa-circle-o-notch fa-spin"></i>');
                            var y = 5000;
                            var wait = setInterval(function(){
                                if(y == 0) {
                                    clearInterval(wait);
                                }
                                $('#denyCoupon').prop("disabled", false);
                                $('#denyCoupon').html("OK");
                                y--;
                            }, 1000);
                        });

                        $('#successCouponModal').on('hide.bs.modal', function ($event) {
                            window.location.replace('{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-coupon-list', [], $session) }}');
                        });
                    }
                    else{
                        $('#wrongCouponModal').modal();
                        $('#errMsg').text("{{Lang::get('mobileci.coupon.wrong_verification_number')}}");
                    }
                })
                .fail(function (data) {
                    $('#wrongCouponModal').modal();
                    $('#errMsg').text("{{Lang::get('mobileci.coupon.wrong_verification_number')}}");
                })
                .always(function (data) {
                    $('#hasCouponModal .modal-content').css('display', 'block');
                    $('#hasCouponModal .modal-spinner').css('display', 'none');
                    $('#tenantverify').val('');
                    $('#hasCouponModal').modal('hide');
                });
            });
            @endif
        });
    </script>
@stop
