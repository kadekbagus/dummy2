@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    {{ HTML::style('mobile-ci/stylesheet/lightslider.min.css') }}
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
<div class="row product">
    <div class="col-xs-12 product-img">
        @if(($coupon->image!='mobile-ci/images/default_product.png'))
        <div class="zoom-wrapper">
            <div class="zoom"><a href="{{ asset($coupon->image) }}" data-featherlight="image"><img alt="" src="{{ asset('mobile-ci/images/product-zoom.png') }}" ></a></div>
        </div>
        @endif
        @if(($product->image!='mobile-ci/images/default_product.png'))
        <a href="{{ asset($coupon->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($coupon->image) }}" ></a>
        @else
        <img class="img-responsive" alt="" src="{{ asset($coupon->image) }}" >
        @endif
    </div>
    <div class="col-xs-12 main-theme product-detail">
        <div class="row">
            <div class="col-xs-12">
                <h3>{{ $coupon->promotion_name }}</h3>
            </div>
            <div class="col-xs-12">
                <p>{{ $coupon->description }}</p>
            </div>
            <div class="col-xs-12">
                <p>{{ $coupon->long_description }}</p>
            </div>
            <div class="col-xs-12">
                <h4>{{ Lang::get('mobileci.promotion.validity') }}</h4>
                <p>{{ date('d M Y', strtotime($coupon->coupon_validity_in_date)) }}</p>
            </div>
            <div class="hide col-xs-12">
                <h4>Coupon Type</h4>
                @if($coupon->promotion_type == 'tenant')
                    <p>{{ Lang::get('mobileci.coupon.tenant_based') }}</p>
                @elseif($coupon->promotion_type == 'mall')
                    <p>{{ Lang::get('mobileci.coupon.mall_based') }}</p>
                @endif
            </div>
            <div class="col-xs-12">
                <h4>{{ Lang::get('mobileci.coupon.tenant_redeem') }}</h4>
                <ul class="tenant-list">
                    @if($cso_exists)
                    <li>Customer Service</li>
                    @endif
                    @foreach($tenants as $tenant)
                        <li>{{ $tenant->tenant->name }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="col-xs-12 main-theme-mall product-detail where">
        @if(! empty((float) $coupon->couponRule->discount_value))
        <div class="row">
            <div class="col-xs-12 text-center">
                <h4>{{ Lang::get('mobileci.coupon.coupon_value') }}</h4>
                <p>IDR <span class="formatted-numx">{{ $coupon->couponRule->discount_value }}</span></p>
            </div>
        </div>
        @endif
        <div class="row">
            <div class="col-xs-12 text-center">
                <button class="btn btn-info btn-block" id="useBtn">{{ Lang::get('mobileci.modals.coupon_use') }}</button>
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
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.coupon.close') }}</span></button>
                <h4 class="modal-title" id="hasCouponLabel">{{ Lang::get('mobileci.coupon.use_coupon') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row select-tenant">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4>{{ Lang::get('mobileci.coupon.enter_tenant_verification_number') }}</h4>
                        <small>{{ Lang::get('mobileci.coupon.ask_our_tenant_employee') }}</small>
                        <div class="form-data">
                            <input type="text" class="form-control text-center" id="tenantverify" style="font-size:20px;">
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
                        <button type="button" id="applyCoupon" class="btn btn-info btn-block">{{ Lang::get('mobileci.coupon.validate') }}</button>
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
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.coupon.close') }}</span></button>
                <h4 class="modal-title" id="hasCouponLabel">{{ Lang::get('mobileci.coupon.use_coupon') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4 style="color:#d9534f" id="errMsg">{{ Lang::get('mobileci.coupon.wrong_verification_number') }}</h4>
                        <small>"{{ Lang::get('mobileci.coupon.please_check_tenant') }}"</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.coupon.ok') }}</button>
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
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.coupon.close') }}</span></button>
                <h4 class="modal-title" id="hasCouponLabel">{{ Lang::get('mobileci.coupon.use_coupon') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4 style="color:#33cc99">{{ Lang::get('mobileci.coupon.successful') }}</h4>
                        <small>"{{ Lang::get('mobileci.coupon.please_communicate') }}"</small>
                        <div class="form-data">
                            <input id="issuecouponno" type="text" class="form-control text-center" style="font-size:20px;" value="" disabled>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12">
                        <button type="button" id="denyCoupon" class="btn btn-info btn-block" data-dismiss="modal" disabled>{{ Lang::get('mobileci.coupon.ok') }}</button>
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
    {{ HTML::script('mobile-ci/scripts/lightslider.min.js') }}
    {{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
    <script type="text/javascript">
        $(document).ready(function(){
            @if($retailer->parent->currency == 'IDR')
            $('.formatted-numx').text(parseFloat($('.formatted-numx').text()).toFixed(0)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 0, vMin: -9999999999.99});
            @else
            $('.formatted-numx').text(parseFloat($('.formatted-numx').text()).toFixed(2)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 2, vMin: -9999999999.99});
            @endif
            $('#image-gallery').lightSlider({
                gallery:true,
                item:1,
                thumbItem:3,
                slideMargin: 0,
                speed:500,
                auto:true,
                loop:true,
                onSliderLoad: function() {
                    $('.zoom a').attr('href', $('.lslide.active img').attr('src'));
                    $('#image-gallery').removeClass('cS-hidden');
                },
                onAfterSlide: function() {
                    console.log('asd');
                    $('.zoom a').attr('href', $('.lslide.active img').attr('src'));
                }
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
                                $('#denyCoupon').html("Ok");
                                y--;
                            }, 1000);
                        });
                        $('#successCouponModal').on('hide.bs.modal', function($event){
                            window.location.replace('mallcoupons');
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
