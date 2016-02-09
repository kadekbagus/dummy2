@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    @if($data->status === 1)
        @if(sizeof($data->records) > 0)
            @if(sizeof($data->records) > 1)
            <div id="search-tool">
                <div class="row">
                    <div class="col-xs-6 search-tool-col">
                        <input type="hidden" name="keyword" value="{{ Input::get('keyword') }}">
                        <a href="{{ url('/customer/search?keyword='.Input::get('keyword').'&sort_by=price&sort_mode=asc&new='.Input::get('new').'&promo='.Input::get('promo').'&coupon='.Input::get('coupon')) }}" id="sort-by-price-up">
                            <span class="fa-stack">
                                <i class="fa fa-square fa-stack-2x"></i><i class="fa fa-chevron-up fa-stack-1x sort-chevron"></i>
                            </span>
                        </a> 
                        <a href="{{ url('/customer/search?keyword='.Input::get('keyword').'&sort_by=price&sort_mode=desc&new='.Input::get('new').'&promo='.Input::get('promo').'&coupon='.Input::get('coupon')) }}" id="sort-by-price-down">
                            <span class="fa-stack">
                                <i class="fa fa-square fa-stack-2x"></i><i class="fa fa-chevron-down fa-stack-1x sort-chevron"></i>
                            </span>
                        </a>
                        <span class="sort-lable">{{ $retailer->parent->currency_symbol }}</span>
                    </div>
                    <div class="col-xs-5 search-tool-col">
                        <a href="{{ url('/customer/search?keyword='.Input::get('keyword').'&sort_by=product_name&sort_mode=asc&new='.Input::get('new').'&promo='.Input::get('promo').'&coupon='.Input::get('coupon')) }}" id="sort-by-name-up">
                            <span class="fa-stack">
                                <i class="fa fa-square fa-stack-2x"></i><i class="fa fa-chevron-up fa-stack-1x sort-chevron"></i>
                            </span>
                        </a> 
                        <a href="{{ url('/customer/search?keyword='.Input::get('keyword').'&sort_by=product_name&sort_mode=desc&new='.Input::get('new').'&promo='.Input::get('promo').'&coupon='.Input::get('coupon')) }}" id="sort-by-name-down">
                            <span class="fa-stack">
                                <i class="fa fa-square fa-stack-2x"></i><i class="fa fa-chevron-down fa-stack-1x sort-chevron"></i>
                            </span>
                        </a>
                        <span class="sort-lable">A-Z</span>
                    </div>
                    <div class="col-xs-1 search-tool-col text-right">
                        <a href="{{ url('/customer/home') }}"><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-close fa-stack-1x sort-chevron"></i></span></a>
                    </div>
                </div>
            </div>
            @endif
            @foreach($data->records as $product)
                <div class="main-theme catalogue" id="product-{{$product->product_id}}">
                    <div class="row row-xs-height catalogue-top">
                        <div class="col-xs-6 catalogue-img col-xs-height col-middle">
                            <div>
                                <?php $x = 1;?>
                                @if($product->on_promo)
                                <div class="ribbon-wrapper-green ribbon{{$x}}">
                                    <div class="ribbon-green">{{ Lang::get('mobileci.catalogue.promo_ribbon') }}</div>
                                </div>
                                <?php $x++;?>
                                @endif
                                @if($product->is_new)
                                <div class="ribbon-wrapper-red ribbon{{$x}}">
                                    <div class="ribbon-red">{{ Lang::get('mobileci.catalogue.new_ribbon') }}</div>
                                </div>
                                <?php $x++;?>
                                @endif
                                @if($product->on_coupons)
                                <div class="ribbon-wrapper-yellow ribbon{{$x}}">
                                    <div class="ribbon-yellow">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
                                </div>
                                <?php $x++;?>
                                @endif
                                @if($product->on_couponstocatch)
                                <div class="ribbon-wrapper-yellow-dash ribbon{{$x}}">
                                    <div class="ribbon-yellow-dash">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
                                </div>
                                <?php $x++;?>
                                @endif
                            </div>
                            <div class="zoom-wrapper">
                                <div class="zoom"><a href="{{ asset($product->image) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
                            </div>
                            <a href="{{ asset($product->image) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive" alt="" src="{{ asset($product->image) }}"></a>
                        </div>
                        <div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
                            <div class="row">
                                <div class="col-xs-12">
                                    <h3>{{ $product->product_name }}</h3>
                                </div>
                                <div class="col-xs-12">
                                    <h4>{{ Lang::get('mobileci.catalogue.code') }} : {{ $product->upc_code }}</h4>
                                </div>                  
                                <div class="col-xs-12 price">
                                    @if(count($product->variants) > 1)
                                    <small>{{ Lang::get('mobileci.catalogue.starting_from') }}</small>
                                    @endif
                                    @if($product->on_promo)
                                        <h3 class="currency currency-promo"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="strike formatted-num">{{ $product->min_price }}</span></h3>
                                        <h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product->priceafterpromo }}</span></h3>
                                    @else
                                    <h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product->min_price }}</span></h3>
                                    @endif
                                </div>
                                    
                            </div>
                        </div>
                    </div>
                    <div class="row catalogue-control-wrapper">
                        <div class="col-xs-6 catalogue-short-des ">
                            <p>{{ $product->short_description }}</p>
                        </div>
                        <div class="col-xs-2 catalogue-control text-center">
                            <div class="circlet btn-blue detail-btn">
                                <a href="{{ url('customer/product?id='.$product->product_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                            </div>
                        </div>
                        @if(count($product->variants) <= 1)
                        <div class="col-xs-2 col-xs-offset-1 catalogue-control price">
                            <div class="circlet btn-blue cart-btn text-center">
                                <a class="product-add-to-cart" data-hascoupon="{{$product->on_coupons}}" data-product-id="{{ $product->product_id }}" data-product-variant-id="{{ $product->variants[0]->product_variant_id }}">
                                    <span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
                                </a>
                            </div>
                        </div>
                        @else
                        <div class="col-xs-2 col-xs-offset-1 catalogue-control price">
                            <div class="circlet btn-blue cart-btn text-center">
                                <a class="product-add-to-cart" href="{{ url('customer/product?id='.$product->product_id.'#select-attribute') }}">
                                    <span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @endforeach
        @else
            <div class="row padded">
                <div class="col-xs-12">
                    <h4>{{ Lang::get('mobileci.search.no_item') }}</h4>
                </div>
            </div>
        @endif
    @else
        <div class="row padded">
            <div class="col-xs-12">
                <h4>{{ Lang::get('mobileci.search.too_much_items') }}</h4>
            </div>
        </div>
    @endif
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
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
{{ HTML::script(Config::get('orbit.cdn.featherlight.1_0_3', 'mobile-ci/scripts/featherlight.min.js')) }}
{{-- Script fallback --}}
<script>
    if (typeof $().featherlight === 'undefined') {
        document.write('<script src="{{asset('mobile-ci/scripts/featherlight.min.js')}}">\x3C/script>');
    }
</script>
{{-- End of Script fallback --}}
{{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
<script type="text/javascript">
    // window.onunload = function(){};
    $(window).bind("pageshow", function(event) {
        if (event.originalEvent.persisted) {
            window.location.reload() 
        }
    });
    @if($retailer->parent->currency == 'IDR')
    $('.formatted-num').each(function(index){
      $(this).text(parseFloat($(this).text()).toFixed(0)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 0, vMin: -9999999999.99});
    });
    @else
    $('.formatted-num').each(function(index){
      $(this).text(parseFloat($(this).text()).toFixed(2)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 2, vMin: -9999999999.99});
    });
    @endif
    $(document).ready(function(){
        if(window.location.hash){
            var hash = window.location.hash;
            var producthash = "#product-"+hash.replace(/^.*?(#|$)/,'');
            console.log(producthash);
            var hashoffset = $(producthash).offset();
            var hashoffsettop = hashoffset.top-68;
            setTimeout(function() {
                $(window).scrollTop(hashoffsettop);
            }, 1);
        }
        // add to cart          
        $('body').off('click', 'a.product-add-to-cart').on('click', 'a.product-add-to-cart', function(event){
            $('#hasCouponModal .modal-body p').html('');
            var prodid = $(this).data('product-id');
            var prodvarid = $(this).data('product-variant-id');
            var img = $(this).children('i');
            var cart = $('#shopping-cart');
            var hasCoupon = $(this).data('hascoupon');
            var used_coupons = [];
            var anchor = $(this);
            if(prodid){
                anchor.hide();
                $('<div class="circlet btn-blue detail-btn cart-spinner"><a><span class="link-spanner"></span><i class="fa fa-circle-o-notch fa-spin"></i></a></div>').insertAfter(anchor);
                if(hasCoupon){
                    $.ajax({
                        url: apiPath+'customer/productcouponpopup',
                        method: 'POST',
                        data: {
                            productid: prodid,
                            productvariantid: prodvarid
                        }
                    }).done(function(data){
                        if(data.status == 'success'){
                            for(var i = 0; i < data.data.length; i++){
                                var disc_val;
                                if(data.data[i].rule_type == 'product_discount_by_percentage' || data.data[i].rule_type == 'cart_discount_by_percentage') disc_val = '-' + (data.data[i].discount_value * 100) + '% off';
                                else if(data.data[i].rule_type == 'product_discount_by_value' || data.data[i].rule_type == 'cart_discount_by_value') disc_val = '- {{ $retailer->parent->currency }} ' + parseFloat(data.data[i].discount_value) +' off';
                                else if(data.data[i].rule_type == 'new_product_price') disc_val = '{{ Lang::get('mobileci.modals.new_product_price') }} {{ $retailer->parent->currency }} <span class="formatted-numx'+i+'">' + parseFloat(data.data[i].discount_value) + '</span>';
                                $('#hasCouponModal .modal-body p').html($('#hasCouponModal .modal-body p').html() + '<div class="row vertically-spaced"><div class="col-xs-2"><input type="checkbox" class="used_coupons" name="used_coupons" value="'+ data.data[i].issued_coupon_id +'"></div><div class="col-xs-4"><img style="width:64px;" class="img-responsive" src="{{asset("'+ data.data[i].promo_image +'")}}"></div><div class="col-xs-6">'+data.data[i].promotion_name+'<br>'+ disc_val +'</div></div>');
                                @if($retailer->parent->currency == 'IDR')
                                $('.formatted-numx'+i).text(parseFloat($('.formatted-numx'+i).text()).toFixed(0)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 0, vMin: -9999999999.99});
                                @else
                                $('.formatted-numx'+i).text(parseFloat($('.formatted-numx'+i).text()).toFixed(2)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 2, vMin: -9999999999.99});
                                @endif
                            }
                            $('#hasCouponModal').modal();
                        }else{
                            console.log(data);
                        }
                    });
                    
                    $('#hasCouponModal').on('hide.bs.modal', function(){
                        anchor.show();
                        $('.cart-spinner').hide();
                    });

                    $('#hasCouponModal').on('change', '.used_coupons', function($event){
                        var coupon = $(this).val();
                        if($(this).is(':checked')){
                            used_coupons.push(coupon);
                        }else{
                            used_coupons = $.grep(used_coupons, function(val){
                                return val != coupon;
                            });
                        }
                    });
                    
                    $('#hasCouponModal').off('click', '#applyCoupon').on('click', '#applyCoupon', function($event){
                        $.ajax({
                            url: apiPath+'customer/addtocart',
                            method: 'POST',
                            data: {
                                productid: prodid,
                                productvariantid: prodvarid,
                                qty:1,
                                coupons : used_coupons
                            }
                        }).done(function(data){
                            // animate cart
                            if(data.status == 'success'){
                                anchor.show();
                                $('.cart-spinner').hide();
                                if(data.data.available_coupons.length < 1){
                                    anchor.data('hascoupon', '');
                                }
                                $('#hasCouponModal').modal('hide');
                                if(prodid){
                                    var imgclone = img.clone().offset({
                                        top: img.offset().top,
                                        left: img.offset().left
                                    }).css({
                                        'color': '#fff',
                                        'opacity': '0.5',
                                        'position': 'absolute',
                                        'height': '20px',
                                        'width': '20px',
                                        'z-index': '100'
                                    }).appendTo($('body')).animate({
                                        'top': cart.offset().top + 10,
                                        'left': cart.offset().left + 10,
                                        'width': '10px',
                                        'height': '10px',
                                    }, 1000);

                                    setTimeout(function(){
                                        cart.effect('shake', {
                                            times:2,
                                            distance:4,
                                            direction:'up'
                                        }, 200)
                                    }, 1000);

                                    imgclone.animate({
                                        'width': 0,
                                        'height': 0
                                    }, function(){
                                        $(this).detach();
                                        $('.cart-qty').css('display', 'block');
                                        var cartnumber = parseInt($('#cart-number').attr('data-cart-number'));
                                        cartnumber = cartnumber + 1;
                                        if(cartnumber <= 9){
                                            $('#cart-number').attr('data-cart-number', cartnumber);
                                            $('#cart-number').text(cartnumber);
                                        }else{
                                            $('#cart-number').attr('data-cart-number', '9+');
                                            $('#cart-number').text('9+');
                                        }
                                    });
                                }
                            }
                        });
                    });

                    $('#hasCouponModal').off('click', '#denyCoupon').on('click', '#denyCoupon', function($event){
                        $.ajax({
                            url: apiPath+'customer/addtocart',
                            method: 'POST',
                            data: {
                                productid: prodid,
                                productvariantid: prodvarid,
                                qty:1,
                                coupons : []
                            }
                        }).done(function(data){
                            // animate cart
                            if(data.status == 'success'){
                                anchor.show();
                                $('.cart-spinner').hide();
                                if(data.data.available_coupons.length < 1){
                                    anchor.data('hascoupon', '');
                                }
                                $('#hasCouponModal').modal('hide');
                                if(prodid){
                                    var imgclone = img.clone().offset({
                                        top: img.offset().top,
                                        left: img.offset().left
                                    }).css({
                                        'color': '#fff',
                                        'opacity': '0.5',
                                        'position': 'absolute',
                                        'height': '20px',
                                        'width': '20px',
                                        'z-index': '100'
                                    }).appendTo($('body')).animate({
                                        'top': cart.offset().top + 10,
                                        'left': cart.offset().left + 10,
                                        'width': '10px',
                                        'height': '10px',
                                    }, 1000);

                                    setTimeout(function(){
                                        cart.effect('shake', {
                                            times:2,
                                            distance:4,
                                            direction:'up'
                                        }, 200)
                                    }, 1000);

                                    imgclone.animate({
                                        'width': 0,
                                        'height': 0
                                    }, function(){
                                        $(this).detach();
                                        $('.cart-qty').css('display', 'block');
                                        var cartnumber = parseInt($('#cart-number').attr('data-cart-number'));
                                        cartnumber = cartnumber + 1;
                                        if(cartnumber <= 9){
                                            $('#cart-number').attr('data-cart-number', cartnumber);
                                            $('#cart-number').text(cartnumber);
                                        }else{
                                            $('#cart-number').attr('data-cart-number', '9+');
                                            $('#cart-number').text('9+');
                                        }
                                    });
                                }
                            }
                        });
                    });
                } else {
                    $.ajax({
                        url: apiPath+'customer/addtocart',
                        method: 'POST',
                        data: {
                            productid: prodid,
                            productvariantid: prodvarid,
                            qty:1
                        }
                    }).done(function(data){
                        // animate cart
                        anchor.show();
                        $('.cart-spinner').hide();
                        var imgclone = img.clone().offset({
                            top: img.offset().top,
                            left: img.offset().left
                        }).css({
                            'color': '#fff',
                            'opacity': '0.5',
                            'position': 'absolute',
                            'height': '20px',
                            'width': '20px',
                            'z-index': '100'
                        }).appendTo($('body')).animate({
                            'top': cart.offset().top + 10,
                            'left': cart.offset().left + 10,
                            'width': '10px',
                            'height': '10px',
                        }, 1000);

                        setTimeout(function(){
                            cart.effect('shake', {
                                times:2,
                                distance:4,
                                direction:'up'
                            }, 200)
                        }, 1000);

                        imgclone.animate({
                            'width': 0,
                            'height': 0
                        }, function(){
                            $(this).detach();
                            $('.cart-qty').css('display', 'block');
                            var cartnumber = parseInt($('#cart-number').attr('data-cart-number'));
                            cartnumber = cartnumber + 1;
                            if(cartnumber <= 9){
                                $('#cart-number').attr('data-cart-number', cartnumber);
                                $('#cart-number').text(cartnumber);
                            }else{
                                $('#cart-number').attr('data-cart-number', '9+');
                                $('#cart-number').text('9+');
                            }
                        });

                    });
                }
            }
        });
    });
</script>
@stop