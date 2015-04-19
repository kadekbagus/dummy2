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
        <ul id="image-gallery" class="gallery list-unstyled cS-hidden">
            @foreach($product->mediaLogoOrig as $media)
            <li data-thumb="{{ asset($media->path) }}">
                <span class="gallery-helper"></span>
                <img class="img-responsive" src="{{ asset($media->path) }}" />
            </li>
            @endforeach
            @foreach($product->mediaImageOrig as $media)
            <li data-thumb="{{ asset($media->path) }}"> 
                <img class="img-responsive" src="{{ asset($media->path) }}" />
            </li>
            @endforeach
        </ul>
    </div>
    <div class="col-xs-12 main-theme product-detail">
        <div class="row">
            <div class="col-xs-12">
                <h3>{{ $product->name }}</h3>
            </div>
            <div class="col-xs-12">
                <p>{{ $product->description }}</p>
            </div>
        </div>
    </div>
    <div class="col-xs-12 main-theme-mall product-detail where">
        <div class="row">
            <div class="col-xs-12">
                <h4>WHERE</h4>
            </div>
            <div class="col-xs-12">
                <p>{{ $product->name }} at</p>
                <p>{{ $retailer->name }} - {{ $product->floor }} - {{ $product->unit }}</p>
                <p>Phone : {{ $product->phone }}</p>
                <p>{{ $product->url }}</p>
                @foreach($product->mediaMapOrig as $map)
                <p>
                    <img class="img-responsive maps" src="{{ asset($map->path) }}">
                </p>
                @endforeach
            </div>
        </div>
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
        });
    </script>
@stop
