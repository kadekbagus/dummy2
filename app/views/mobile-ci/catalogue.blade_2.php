@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<div>
	<ul class="family-list">
	@foreach($families as $family)
		<li data-family-container="{{ $family->category_id }}" data-family-container-level="{{ $family->category_level }}"><a class="family-a" data-family-id="{{ $family->category_id }}" data-family-level="{{ $family->category_level }}" data-family-isopen="0"><div class="family-label">{{ $family->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
			<div class="product-list"></div>
		</li>
	@endforeach
	</ul>
</div>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="hasCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.activation.close') }}</span></button>
          <h4 class="modal-title" id="hasCouponLabel">{{ Lang::get('mobileci.coupon.my_coupon') }}</h4>
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
                <button type="button" id="applyCoupon" class="btn btn-success btn-block">{{ Lang::get('mobileci.coupon.use') }}</button>
              </div>
              <div class="col-xs-6">
                <button type="button" id="denyCoupon" class="btn btn-danger btn-block">{{ Lang::get('mobileci.coupon.next_time') }}</button>
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
	{{ HTML::script('mobile-ci/scripts/jquery.storageapi.min.js') }}
	<script type="text/javascript">
		// window.onunload = function(){};
		$(window).bind("pageshow", function(event) {
		    if (event.originalEvent.persisted) {
		        window.location.reload() 
		    }
		});
		$(document).ready(function(){

			$('.family-list').on('click', 'a.family-a', function(event){
				var families = [];
				var open_level = $(this).data('family-level');
				$('li[data-family-container-level="'+open_level+'"] .product-list').css('display','visible').slideUp('slow');
				$('li[data-family-container-level="'+open_level+'"] .family-label i').attr('class', 'fa fa-chevron-circle-down');
				$('li[data-family-container-level="'+open_level+'"] .family-a').data('family-isopen', 0);
				$('li[data-family-container-level="'+open_level+'"] .family-a').attr('data-family-isopen', 0);
				// $("div.product-list").html('');
				// $('.family-label > i').attr('class', 'fa fa-chevron-circle-down');
				// $("a").data('family-isopen', 0);

				if($(this).data('family-isopen') == 0){
					$(this).data('family-isopen', 1);
					$(this).attr('data-family-isopen', 1);

					var a = $(this);
					var family_id = $(this).data('family-id');
					var family_level = $(this).data('family-level');

					var aopen = $('a[data-family-isopen="1"]');
					
					$.each(aopen, function(index, value) {
						families.push($(value).attr('data-family-id'));
					});
					$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-circle-o-notch fa-spin');
					$.ajax({
						url: apiPath+'customer/products',
						method: 'GET',
						data: {
							families: families,
							family_id: family_id,
							family_level: family_level,
						}
					}).done(function(data){
						if(data == 'Invalid session data.'){
							location.replace('/customer');
						} else {
							a.parent('[data-family-container="'+ family_id +'"]').children("div.product-list").css('display', 'none').html(data).slideDown('slow');
							$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-chevron-circle-up');
						}
					});
				} else {
					$(this).data('family-isopen', 0);
					$(this).attr('data-family-isopen', 0);
					var family_id = $(this).data('family-id');
					var family_level = $(this).data('family-level');
					$('*[data-family-container="'+ family_id +'"]').children("div.product-list").html('');
					$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-chevron-circle-down');
				}
				
			});
			// add to cart
			$('.family-list').on('click', 'a.product-add-to-cart', function(event){
				$('#hasCouponModal .modal-body p').html('');
				var used_coupons = [];
				var anchor = $(this);
				var hasCoupon = $(this).data('hascoupon');
				var prodid = $(this).data('product-id');
				var prodvarid = $(this).data('product-variant-id');
				var img = $(this).children('i');
				var cart = $('#shopping-cart');
				if(hasCoupon){
					$.ajax({
						url: apiPath+'customer/productcouponpopup',
						method: 'POST',
						data: {
							productid: prodid
						}
					}).done(function(data){
						if(data.status == 'success'){
					        for(var i = 0; i < data.data.length; i++){
					        	var disc_val;
					        	if(data.data[i].rule_type == 'product_discount_by_percentage') disc_val = '-' + (data.data[i].discount_value * 100) + '% off';
					        	else if(data.data[i].rule_type == 'product_discount_by_value') disc_val = '- {{ $retailer->parent->currency }} ' + parseFloat(data.data[i].discount_value) +' off';
					        	$('#hasCouponModal .modal-body p').html($('#hasCouponModal .modal-body p').html() + '<div class="row vertically-spaced"><div class="col-xs-2"><input type="checkbox" class="used_coupons" name="used_coupons" value="'+ data.data[i].issued_coupon_id +'"></div><div class="col-xs-4"><img style="width:64px;" class="img-responsive" src="'+ data.data[i].promo_image +'"></div><div class="col-xs-6">'+data.data[i].promotion_name+'<br>'+ disc_val +'</div></div>');
					        }
					        $('#hasCouponModal').modal();
				        }else{
				          	console.log(data);
				        }
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
					
					$('#hasCouponModal').on('click', '#applyCoupon', function($event){
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

					$('#hasCouponModal').on('click', '#denyCoupon', function($event){
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
							qty:1,
							coupons : []
						}
					}).done(function(data){
						// animate cart
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
					});
				}
			});
		});
	</script>
@stop
