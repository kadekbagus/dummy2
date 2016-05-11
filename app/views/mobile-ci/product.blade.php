@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<!-- product -->
<div class="row product">
    <div class="col-xs-12 product-img">
        <!-- <div ng-include="product.ribbon"></div> -->
        <div>
            <?php $x=1; ?>
            @if(count($promotions)>0)
            <div class="ribbon-wrapper-green ribbon{{$x}}">
                <div class="ribbon-green">{{ Lang::get('mobileci.catalogue.promo_ribbon') }}</div>
            </div>
            <?php $x++;?>
            @endif
            @if($product->new_from <= \Carbon\Carbon::now() && $product->new_until >= \Carbon\Carbon::now())
            <div class="ribbon-wrapper-red ribbon{{$x}}">
                <div class="ribbon-red">{{ Lang::get('mobileci.catalogue.new_ribbon') }}</div>
            </div>
            <?php $x++;?>
            @endif
            @if(count($coupons)>0)
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
            <div class="zoom"><a href="{{ asset($product->image) }}" data-featherlight="image"><img alt="" src="{{ asset('mobile-ci/images/product-zoom.png') }}" ></a></div>
        </div>
        <a href="{{ asset($product->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product->image) }}"></a>
    </div>
    <div class="col-xs-12 product-detail">
        <div class="row">
            <div class="col-xs-12">
                <h3>{{ $product->product_name }}</h3>
            </div>
            <div class="col-xs-12">
                <p>{{ $product->long_description }}</p>
            </div>
            @if(!empty($product->in_store_localization))
            <div class="col-xs-12">
                <h4>{{ Lang::get('mobileci.catalogue.in_store_location') }}</h4>
                <p>{{ $product->in_store_localization }}</p>
            </div>
            @endif
        </div>
        @if(count($promotions)>0)
        <div class="col-xs-12">
            <h3>{{ Lang::get('mobileci.product_detail.promo_discount') }}</h3>
        </div>
        @foreach($promotions as $promotion)
        <div class="additional-detail">
            <div class="row">
                <div class="col-xs-12">
                    <p>
                    <b>
                    @if($promotion->rule_type === 'product_discount_by_percentage')
                    {{ $promotion->discount_value * 100 + 0 }}%
                    @elseif($promotion->rule_type === 'new_product_price')
                    New Price <small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $promotion->discount_value + 0 }}</span>
                    @else
                    <small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $promotion->discount_value + 0 }}</span>
                    @endif
                    | {{ $promotion->promotion_name }}</b>
                    </p>
                </div>
            </div>
            <div class="row additional-dates">
                <div class="col-xs-12 col-sm-12">
                    <p>
                    @if($promotion->is_permanent == 'Y')
                    {{ date('j M Y', strtotime($promotion->begin_date)) }}
                    @else
                    {{ date('j M Y', strtotime($promotion->begin_date)) }}
                    {{ Lang::get('mobileci.product_detail.to') }}
                    {{ date('j M Y', strtotime($promotion->end_date)) }}
                    @endif
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-12">
                    <p>{{ $promotion->description }}</p>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        @if($product->on_couponstocatch)
        <div class="col-xs-12">
            <h3>{{ Lang::get('mobileci.product_detail.get_coupon') }}</h3>
        </div>
        @foreach($couponstocatchs as $couponstocatch)
        <div class="additional-detail">
            <div class="row">
                <div class="col-xs-12">
                    <p>
                        <b>
                        @if($couponstocatch->rule_type === 'product_discount_by_percentage' || $couponstocatch->rule_type === 'cart_discount_by_percentage')
                        {{ $couponstocatch->discount_value * 100 + 0 }}%
                        @elseif($couponstocatch->rule_type === 'new_product_price')
                        New Price <small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $couponstocatch->discount_value + 0 }}</span>
                        @else
                        <small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $couponstocatch->discount_value + 0 }}</span>
                        @endif
                        | {{ $couponstocatch->promotion_name }}</b>
                    </p>
                </div>
            </div>
            <div class="row additional-dates">
                <div class="col-xs-12 col-sm-12">
                    <p>
                    @if($couponstocatch->is_permanent == 'Y')
                    {{ date('j M Y', strtotime($couponstocatch->begin_date)) }}
                    @else
                    {{ date('j M Y', strtotime($couponstocatch->begin_date)) }}
                    {{ Lang::get('mobileci.product_detail.to') }}
                    {{ date('j M Y', strtotime($couponstocatch->end_date)) }}
                    @endif
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-12">
                    <p><small>{{ $couponstocatch->description }}</small></p>
                </div>
            </div>
        </div>
        @endforeach
        @endif
    </div>
    <!-- <pre>{{ var_dump($product->attribute1) }}</pre> -->
    <div class="col-xs-12 product-attributes" id="select-attribute">
        <div class="row">
            @if(! is_null($product->attribute1))
            <div class="col-xs-4 main-theme-text">
                <div class="radio-container">
                    <h5>{{ $product->attribute1->product_attribute_name }}</h5>
                    <ul id="attribute1">
                        <?php $attr_val = array();?>
                        @foreach($attributes as $attribute)
                        @if($attribute->attr1 === $product->attribute1->product_attribute_name && !in_array($attribute->value1, $attr_val))
                        <li><input type="radio" data-attr-lvl="1" class="attribute_value_id" id="attribute_id{{$attribute->attr_val_id1}}" name="product_attribute_value_id1" value="{{$attribute->attr_val_id1}}" ><label for="attribute_id{{$attribute->attr_val_id1}}"><span class="attribute-title">{{ $attribute->value1 }}</span></label></li>
                        <?php $attr_val[] = $attribute->value1;?>
                        @endif
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
            @if(! is_null($product->attribute2))
            <div class="col-xs-4 main-theme-text">
                <div class="radio-container">
                    <h5>{{ $product->attribute2->product_attribute_name }}</h5>
                    <ul id="attribute2">
                        
                    </ul>
                </div>
            </div>
            @endif
            @if(! is_null($product->attribute3))
            <div class="col-xs-4 main-theme-text">
                <div class="radio-container">
                    <h5>{{ $product->attribute3->product_attribute_name }}</h5>
                    <ul id="attribute3">
                        
                    </ul>
                </div>
            </div>
            @endif
        </div>
        <div class="row">
            @if(! is_null($product->attribute4))
            <div class="col-xs-4 main-theme-text">
                <div class="radio-container">
                    <h5>{{ $product->attribute4->product_attribute_name }}</h5>
                    <ul id="attribute4">
                        
                    </ul>
                </div>
            </div>
            @endif
            @if(! is_null($product->attribute5))
            <div class="col-xs-4 main-theme-text">
                <div class="radio-container">
                    <h5>{{ $product->attribute5->product_attribute_name }}</h5>
                    <ul id="attribute5">
                        
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
    <div class="col-xs-12 product-bottom main-theme ">
        <div class="row">
            <div class="col-xs-6">
                <h4>{{ Lang::get('mobileci.catalogue.code') }} : {{ $product->upc_code }}</h4>
            </div>
            <div class="col-xs-6 text-right" id="starting-from">
                <h4><small>{{ Lang::get('mobileci.catalogue.starting_from') }} :</small></h4>
            </div>
        </div>
        <div class="row price-tags">
            <?php $discount=0;?>
            @if(count($promotions)>0)
                <div class="col-xs-6 strike" id="price-before">
                    <h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product->min_price }}</span></h3>
                </div>
                <div class="col-xs-6 pull-right text-right" id="price">
                    <h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product->min_promo_price }}</span></h3>
                </div>
            @else
            <div class="col-xs-6 pull-right text-right" id="price">
                <h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product->min_price + 0 }}</span></h3>
            </div>
            @endif
        </div>
        <div class="row text-center product-control">
            <!-- <div class="col-xs-2 col-xs-offset-8 col-ms-1 col-ms-offset-10 col-md-1 col-md-offset-10 col-sm-1 col-sm-offset-10 col-lg-1 col-lg-offset-10"> -->
            <div class="col-xs-2  col-ms-1  col-md-1  col-sm-1  col-lg-1">
                <div class="circlet back-btn btn-blue" id="backBtnProduct">
                    <span class="link-spanner"></span><i class="fa fa-mail-reply"></i>
                </div>
            </div>
            <div class="col-xs-2 col-ms-1 col-md-1 col-sm-1 col-lg-1 pull-right">
                <div class="circlet cart-btn btn-blue pull-right add-to-cart-button btn-disabled" data-hascoupon="{{$product->on_coupons}}">
                    <span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
                </div>
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
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.activation.close') }}</span></button>
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
        function num_format(num){
          @if($retailer->parent->currency == 'IDR')
          var num = parseFloat(num).toFixed(0);
          var partnum = num.toString().split('.');
          var part1 = partnum[0].replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
          var newnum = part1;
          @else
          var num = parseFloat(num).toFixed(2);
          var partnum = num.toString().split('.');
          var part1 = partnum[0].replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
          var part2 = partnum[1];
          var newnum = part1+'.'+part2;
          @endif
          return newnum;
        }
        @if($retailer->parent->currency == 'IDR')
        $('.formatted-num').each(function(index){
          $(this).text(parseFloat($(this).text()).toFixed(0)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 0, vMin: -9999999999.99});
        });
        @else
        $('.formatted-num').each(function(index){
          $(this).text(parseFloat($(this).text()).toFixed(2)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 2, vMin: -9999999999.99});
        });
        @endif
        var indexOf = function(needle) {
            if(typeof Array.prototype.indexOf === 'function') {
                indexOf = Array.prototype.indexOf;
            } else {
                indexOf = function(needle) {
                    var i = -1, index = -1;

                    for(i = 0; i < this.length; i++) {
                        if(this[i] === needle) {
                            index = i;
                            break;
                        }
                    }

                    return index;
                };
            }

            return indexOf.call(this, needle);
        };
        var variants = {{ json_encode($product->variants) }};
        var promotions = {{ json_encode($promotions) }};
        var attributes = {{ json_encode($attributes) }};
        var product = {{ json_encode($product) }};
        var itemReady = [];
        $(document).ready(function(){
            if(variants.length < 2){
                var itemReady = [];
                itemReady = variants;
                $('.add-to-cart-button').removeClass('btn-disabled').attr('id', 'addToCartButton');
                var pricebefore = parseFloat(itemReady[0].price);
                var priceafter = parseFloat(itemReady[0].promo_price);
                $('#starting-from').hide();
                $('#price-before span').text(num_format(pricebefore));
                $('#price span').text(num_format(priceafter));
            }
            var selectedVariant = {};
            var selectedLvl, selectedVal;
            selectedVariant.attr1 = undefined;
            selectedVariant.attr2 = undefined;
            selectedVariant.attr3 = undefined;
            selectedVariant.attr4 = undefined;
            selectedVariant.attr5 = undefined;
            $('.product-attributes').on('change', '.attribute_value_id', function($e){
                selectedVal = $(this).val();
                selectedLvl = $(this).data('attr-lvl');
                var attrArr = [];
                var filteredAttr = $.grep(attributes, function(n, i){
                    switch(selectedLvl){
                        case 1:
                            selectedVariant.attr1 = selectedVal;
                            selectedVariant.attr2 = undefined;
                            selectedVariant.attr3 = undefined;
                            selectedVariant.attr4 = undefined;
                            selectedVariant.attr5 = undefined;
                            return n.attr_val_id1 == selectedVal;
                        case 2:
                            selectedVariant.attr2 = selectedVal;
                            selectedVariant.attr3 = undefined;
                            selectedVariant.attr4 = undefined;
                            selectedVariant.attr5 = undefined;
                            return n.attr_val_id2 == selectedVal;
                        case 3:
                            selectedVariant.attr3 = selectedVal;
                            selectedVariant.attr4 = undefined;
                            selectedVariant.attr5 = undefined;
                            return n.attr_val_id3 == selectedVal;
                        case 4:
                            selectedVariant.attr4 = selectedVal;
                            selectedVariant.attr5 = undefined;
                            return n.attr_val_id4 == selectedVal;
                        case 5:
                            selectedVariant.attr5 = selectedVal;
                            return n.attr_val_id5 == selectedVal;
                    }
                });
                for(var i= selectedLvl+1;i<=5;i++){
                    $('#attribute'+i).html('');
                }
                for(var i=0; i<filteredAttr.length; i++){
                    switch(selectedLvl){
                        case 1:
                            if(indexOf.call(attrArr, filteredAttr[i].attr_val_id2) < 0){
                                $('#attribute'+ (selectedLvl+1)).append('<li><input type="radio" data-attr-lvl="'+ (selectedLvl+1) +'"  class="attribute_value_id" id="attribute_id'+filteredAttr[i].attr_val_id2+'" name="product_attribute_value_id'+ (selectedLvl+1) +'" value="'+ filteredAttr[i].attr_val_id2 +'" ><label for="attribute_id'+filteredAttr[i].attr_val_id2+'"><span class="attribute-title">'+ filteredAttr[i].value2 +'</span></label></li>')
                                attrArr.push(filteredAttr[i].attr_val_id2);
                            }
                            break;
                        case 2:
                            if(indexOf.call(attrArr, filteredAttr[i].attr_val_id3) < 0){
                                $('#attribute'+ (selectedLvl+1)).append('<li><input type="radio" data-attr-lvl="'+ (selectedLvl+1) +'"  class="attribute_value_id" id="attribute_id'+filteredAttr[i].attr_val_id3+'" name="product_attribute_value_id'+ (selectedLvl+1) +'" value="'+ filteredAttr[i].attr_val_id3 +'" ><label for="attribute_id'+filteredAttr[i].attr_val_id3+'"><span class="attribute-title">'+ filteredAttr[i].value3 +'</span></label></li>')
                                attrArr.push(filteredAttr[i].attr_val_id3);
                            }
                            break;
                        case 3:
                            if(indexOf.call(attrArr, filteredAttr[i].attr_val_id4) < 0){
                                $('#attribute'+ (selectedLvl+1)).append('<li><input type="radio" data-attr-lvl="'+ (selectedLvl+1) +'"  class="attribute_value_id" id="attribute_id'+filteredAttr[i].attr_val_id4+'" name="product_attribute_value_id'+ (selectedLvl+1) +'" value="'+ filteredAttr[i].attr_val_id4 +'" ><label for="attribute_id'+filteredAttr[i].attr_val_id4+'"><span class="attribute-title">'+ filteredAttr[i].value4 +'</span></label></li>')
                                attrArr.push(filteredAttr[i].attr_val_id4);
                            }
                            break;
                        case 4:
                            if(indexOf.call(attrArr, filteredAttr[i].attr_val_id5) < 0){
                                $('#attribute'+ (selectedLvl+1)).append('<li><input type="radio" data-attr-lvl="'+ (selectedLvl+1) +'"  class="attribute_value_id" id="attribute_id'+filteredAttr[i].attr_val_id5+'" name="product_attribute_value_id'+ (selectedLvl+1) +'" value="'+ filteredAttr[i].attr_val_id5 +'" ><label for="attribute_id'+filteredAttr[i].attr_val_id5+'"><span class="attribute-title">'+ filteredAttr[i].value5 +'</span></label></li>')
                                attrArr.push(filteredAttr[i].attr_val_id5);
                            }
                            break;
                    }
                }
                
                itemReady = $.grep(variants, function(n, i){
                    return (n.product_attribute_value_id1 == selectedVariant.attr1) && (n.product_attribute_value_id2 == selectedVariant.attr2) && (n.product_attribute_value_id3 == selectedVariant.attr3) && (n.product_attribute_value_id4 == selectedVariant.attr4) && (n.product_attribute_value_id5 == selectedVariant.attr5);
                });
                var pricebefore, priceafter;
                if(itemReady.length > 0){
                    pricebefore = parseFloat(itemReady[0].price);
                    priceafter = parseFloat(itemReady[0].promo_price);
                    $('.add-to-cart-button').removeClass('btn-disabled').attr('id', 'addToCartButton');
                    $('#starting-from').hide();
                }else{
                    pricebefore = parseFloat(product.min_price);
                    priceafter = parseFloat(product.min_promo_price);
                    $('#starting-from').show();
                    $('.add-to-cart-button').addClass('btn-disabled').removeAttr('id');
                }
                $('#price-before span').text(num_format(pricebefore));
                $('#price span').text(num_format(priceafter));
            });
            
            $('#backBtnProduct').click(function(){
                window.history.back()
            });

            $('body').off('click', '#addToCartButton').on('click', '#addToCartButton', function($event){
                // add to cart
                $('#hasCouponModal .modal-body p').html('');
                var prodid = itemReady[0].product_id;
                var prodvarid = itemReady[0].product_variant_id;
                var img = $(this).children('i');
                var cart = $('#shopping-cart');
                var hasCoupon = $(this).data('hascoupon');
                var used_coupons = [];
                var anchor = $(this);
                anchor.hide();
                $('<div class="circlet btn-blue detail-btn cart-spinner pull-right"><a><span class="link-spanner"></span><i class="fa fa-circle-o-notch fa-spin"></i></a></div>').insertAfter(anchor);

                if(hasCoupon){
                    $.ajax({
                        url: apiPath+'customer/productcouponpopup',
                        method: 'POST',
                        data: {
                            productid: prodid,
                            productvariantid: prodvarid
                        }
                    }).done(function(data){
                        if(data.data.length > 0){
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
                                // console.log(data);
                            }
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
                                console.log('withoutcoupon');
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
                                console.log('apply');
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
                                console.log('danycoupon');
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
                        console.log('nocoupon');
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
            });
        });
    </script>
@stop
