@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    {{ HTML::style('mobile-ci/stylesheet/lightslider.min.css') }}
@stop

@section('content')
<!-- product -->
<div class="row product">
    <div class="col-xs-12 product-img">
        <div class="zoom-wrapper">
            <div class="zoom"><a href="#" data-featherlight="image"><img alt="" src="{{ asset('mobile-ci/images/product-zoom.png') }}" ></a></div>
        </div>
        <a href="{{ asset($product->promo_image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product->promo_image) }}" ></a>
    </div>
    <div class="col-xs-12 main-theme product-detail">
        <div class="row">
            <div class="col-xs-12">
                <h3>{{ $product->promotion_name }}</h3>
            </div>
            <div class="col-xs-12">
                <p>{{ $product->description }}</p>
            </div>
        </div>
    </div>
    <div class="col-xs-12 main-theme-mall product-detail where">
        <button class="btn btn-info btn-block" id="useBtn">Use</button>
    </div>
</div>
<!-- end of product -->
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="hasCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="hasCouponLabel">Use Coupon</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4>Enter Tenant's Verification Number</h4>
                        <small>(Ask our tenant employee)</small>
                        <div class="form-data">
                            <input type="text" class="form-control text-center" style="font-size:20px;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <input type="hidden" name="detail" id="detail" value="">
                    <div class="col-xs-12">
                        <button type="button" id="applyCoupon" class="btn btn-info btn-block">{{ Lang::get('mobileci.modals.coupon_use') }}</button>
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
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="hasCouponLabel">Use Coupon</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4 style="color:#d9534f">Wrong Verification Number</h4>
                        <small>"Please check the tenant employee or mall customer service"</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12">
                        <button type="button" id="denyCoupon" class="btn btn-info btn-block">Ok</button>
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
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="hasCouponLabel">Use Coupon</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced text-center">
                        <h4 style="color:#33cc99">Successful</h4>
                        <small>"Please communicate the following number to tenant employee"</small>
                        <div class="form-data">
                            <input type="text" class="form-control text-center" style="font-size:20px;" value="1326541" disabled>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12">
                        <button type="button" id="denyCoupon" class="btn btn-info btn-block">Ok</button>
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
                $('#successCouponModal').modal();
            });
        });
    </script>
@stop
