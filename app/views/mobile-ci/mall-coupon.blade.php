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

@section('content')
<div class="row">
    <div class="col-xs-12  main-theme product-detail">
        @if(($coupon->image!='mobile-ci/images/default_coupon.png'))
        <a href="{{{ asset($coupon->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive" alt="" src="{{{ asset($coupon->image) }}}" ></a>
        @else
        <img class="img-responsive" alt="" src="{{{ asset($coupon->image) }}}" >
        @endif
    </div>
</div>
<div class="row product-info padded">
    <div class="col-xs-12">
        <div class="row">
            <div class="col-xs-12">
                <p>{{ nl2br($coupon->description) }}</p>
            </div>
            <div class="col-xs-12">
                <p>{{ nl2br($coupon->long_description) }}</p>
            </div>
            <div class="col-xs-12">
                <h4><strong>{{{ Lang::get('mobileci.coupon_detail.validity_label') }}}</strong></h4>
                <p>{{{ date('d M Y', strtotime($coupon->begin_date)) }}} - {{{ date('d M Y', strtotime($coupon->end_date)) }}}</p>
            </div>
        </div>
    </div>
</div>

<div class="row vertically-spaced">
    <div class="col-xs-12 padded">
        @if(count($tenants) > 0)
        <div class="row vertically-spaced">
            <div class="col-xs-12 text-center">
                <a href="{{{ url('customer/tenants?coupon_redeem_id='.$coupon->promotion_id) }}}" class="btn btn-info btn-block">{{{ Lang::get('mobileci.tenant.redemption_places') }}}</a>
            </div>
        </div>
        @endif
        <div class="row">
            @if(count($tenants) <=0)
                @if($cs_reedem)
                    <div class="col-xs-12 text-center">
                        <button class="btn btn-info btn-block" id="useBtn">{{{ Lang::get('mobileci.modals.coupon_use') }}}</button>
                    </div>
                    @endif
            @else
            <div class="col-xs-12 text-center">
                <button class="btn btn-info btn-block" id="useBtn">{{{ Lang::get('mobileci.modals.coupon_use') }}}</button>
            </div>
            @endif
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
            $(window).scroll(function(){
                s = $(window).scrollTop();
                $('.product-detail img').css('-webkit-transform', 'translateY('+(s/3)+'px)');
            });
            $('#useBtn').click(function(){
                $('#hasCouponModal').modal();
            });
            $('#applyCoupon').click(function(){
                $('#hasCouponModal .modal-content').css('display', 'none');
                $('#hasCouponModal .modal-spinner').css('display', 'block');
                $.ajax({
                    url: apiPath+'issued-coupon/redeem',
                    method: 'POST',
                    data: {
                        issued_coupon_id: '{{$coupon->issuedCoupons[0]->issued_coupon_id}}',
                        merchant_verification_number: $('#tenantverify').val(),
                        current_mall: '{{$retailer->merchant_id}}'
                    }
                }).done(function(data){
                    if(data.status == 'success'){
                        $('#successCouponModal').modal({
                            backdrop: 'static',
                            keyboard: false
                        });
                        $('#successCouponModal').on('shown.bs.modal', function($event){
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
                        $('#successCouponModal').on('hide.bs.modal', function($event){
                            window.location.replace({{ json_encode(url('customer/mallcoupons')) }});
                        });
                    }else{
                        $('#wrongCouponModal').modal();
                        $('#errMsg').text("{{Lang::get('mobileci.coupon.wrong_verification_number')}}");
                    }
                }).fail(function(data) {
                    $('#wrongCouponModal').modal();
                    $('#errMsg').text("{{Lang::get('mobileci.coupon.wrong_verification_number')}}");
                }).always(function(data){
                    $('#hasCouponModal .modal-content').css('display', 'block');
                    $('#hasCouponModal .modal-spinner').css('display', 'none');
                    $('#tenantverify').val('');
                    $('#hasCouponModal').modal('hide');
                });
            });
        });
    </script>
@stop
