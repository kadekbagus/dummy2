@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
	<script type="text/javascript">
       	$(document).ready(function()
        {
            var d = new Date();
            d = d.getTime();
            if ($('#reloadValue').val().length == 0)
            {
                $('#reloadValue').val(d);
                $('body').show();
                $(window).scrollTop($.cookie('lastpos')); 
            }
            else
            {
                $('#reloadValue').val('');
                location.reload();
            }
        });
   </script>
@stop

@section('content')
<div onunload="">
	@if($hasFamily == 'no')
		<ul class="family-list">
		@foreach($families as $family) 
			<li data-family-container="{{ $family->category_id }}" data-family-container-level="{{ $family->category_level }}"><a class="family-a" data-family-id="{{ $family->category_id }}" data-family-level="{{ $family->category_level }}" data-family-isopen="0"><div class="family-label">{{ $family->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
				<div class="product-list"></div>
			</li>
		@endforeach
		</ul>
	@else
		<ul class="family-list">

		@foreach($families as $family)
			<li data-family-container="{{ $family->category_id }}" data-family-container-level="{{ $family->category_level }}"><a class="family-a" data-family-id="{{ $family->category_id }}" data-family-level="{{ $family->category_level }}" data-family-isopen="@if($family->category_id == Session::get('f1')){{1}}@else{{0}}@endif"><div class="family-label">{{ $family->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
				<div class="product-list">
					@if($family->category_id == Session::get('f1'))
						@foreach($lvl1->records as $product)
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
											<div class="zoom"><a href="{{ asset($product->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
										</div>
										<a href="{{ asset($product->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product->image) }}"></a>
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
									<div class="col-xs-2 col-xs-offset-1 catalogue-control price ">
										<div class="circlet btn-blue cart-btn text-center">
											<a class="product-add-to-cart" data-hascoupon="{{$product->on_coupons}}" data-product-id="{{ $product->product_id }}" data-product-variant-id="{{ $product->variants[0]->product_variant_id }}" >
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
						<ul>
						@if(! is_null($lvl1->subfamilies))
							@foreach($lvl1->subfamilies as $subfamily)
								<li data-family-container="{{ $subfamily->category_id }}" data-family-container-level="{{ $subfamily->category_level }}"><a class="family-a" data-family-id="{{ $subfamily->category_id }}" data-family-level="{{ $subfamily->category_level }}" data-family-isopen="@if($subfamily->category_id == Session::get('f2')){{1}}@else{{0}}@endif" ><div class="family-label">{{ $subfamily->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
									<div class="product-list">
										@if($subfamily->category_id == Session::get('f2'))
											@foreach($lvl2->records as $product2)
												<div class="main-theme catalogue" id="product-{{$product2->product_id}}">
													<div class="row row-xs-height catalogue-top">
														<div class="col-xs-6 catalogue-img col-xs-height col-middle">
															<div>
																<?php $x2 = 1;?>
																@if($product2->on_promo)
																<div class="ribbon-wrapper-green ribbon{{$x2}}">
																	<div class="ribbon-green">{{ Lang::get('mobileci.catalogue.promo_ribbon') }}</div>
																</div>
																<?php $x2++;?>
																@endif
																@if($product2->is_new)
																<div class="ribbon-wrapper-red ribbon{{$x2}}">
																	<div class="ribbon-red">{{ Lang::get('mobileci.catalogue.new_ribbon') }}</div>
																</div>
																<?php $x2++;?>
																@endif
																@if($product2->on_coupons)
																<div class="ribbon-wrapper-yellow ribbon{{$x2}}">
																	<div class="ribbon-yellow">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
																</div>
																<?php $x2++;?>
																@endif
																@if($product2->on_couponstocatch)
																<div class="ribbon-wrapper-yellow-dash ribbon{{$x2}}">
																	<div class="ribbon-yellow-dash">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
																</div>
																<?php $x2++;?>
																@endif
															</div>
															<div class="zoom-wrapper">
																<div class="zoom"><a href="{{ asset($product2->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
															</div>
															<a href="{{ asset($product2->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product2->image) }}"></a>
														</div>
														<div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
															<div class="row">
																<div class="col-xs-12">
																	<h3>{{ $product2->product_name }}</h3>
																</div>
																<div class="col-xs-12">
																	<h4>{{ Lang::get('mobileci.catalogue.code') }} : {{ $product2->upc_code }}</h4>
																</div>
																<div class="col-xs-12 price">
																	@if(count($product2->variants) > 1)
																	<small>{{ Lang::get('mobileci.catalogue.starting_from') }}</small>
																	@endif
																	@if($product2->on_promo)
																		<h3 class="currency currency-promo"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="strike formatted-num">{{ $product2->min_price }}</span></h3>
																		<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product2->priceafterpromo }}</span></h3>
																	@else
																	<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product2->min_price }}</span></h3>
																	@endif
																</div>
															</div>
														</div>
													</div>
													<div class="row catalogue-control-wrapper">
														<div class="col-xs-6 catalogue-short-des ">
															<p>{{ $product2->short_description }}</p>
														</div>
														<div class="col-xs-2 catalogue-control text-center">
															<div class="circlet btn-blue detail-btn">
																<a href="{{ url('customer/product?id='.$product2->product_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
															</div>
														</div>
														@if(count($product2->variants) <= 1)
														<div class="col-xs-2 col-xs-offset-1 catalogue-control price ">
															<div class="circlet btn-blue cart-btn text-center">
																<a class="product-add-to-cart" data-hascoupon="{{$product2->on_coupons}}" data-product-id="{{ $product2->product_id }}" data-product-variant-id="{{ $product2->variants[0]->product_variant_id }}" >
																	<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
																</a>
															</div>
														</div>
														@else
														<div class="col-xs-2 col-xs-offset-1 catalogue-control price">
															<div class="circlet btn-blue cart-btn text-center">
																<a class="product-add-to-cart" href="{{ url('customer/product?id='.$product2->product_id.'#select-attribute') }}">
																	<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
																</a>
															</div>
														</div>
														@endif
													</div>
												</div>
											@endforeach
											<ul>
											@if(! is_null($lvl2->subfamilies))
												@foreach($lvl2->subfamilies as $subfamily2)
													<li data-family-container="{{ $subfamily2->category_id }}" data-family-container-level="{{ $subfamily2->category_level }}"><a class="family-a" data-family-id="{{ $subfamily2->category_id }}" data-family-level="{{ $subfamily2->category_level }}" data-family-isopen="@if($subfamily2->category_id == Session::get('f2')){{1}}@else{{0}}@endif" ><div class="family-label">{{ $subfamily2->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
														<div class="product-list">
															@if($subfamily2->category_id == Session::get('f3'))
																@foreach($lvl3->records as $product3)
																	<div class="main-theme catalogue" id="product-{{$product3->product_id}}">
																		<div class="row row-xs-height catalogue-top">
																			<div class="col-xs-6 catalogue-img col-xs-height col-middle">
																				<div>
																					<?php $x3 = 1;?>
																					@if($product3->on_promo)
																					<div class="ribbon-wrapper-green ribbon{{$x3}}">
																						<div class="ribbon-green">{{ Lang::get('mobileci.catalogue.promo_ribbon') }}</div>
																					</div>
																					<?php $x3++;?>
																					@endif
																					@if($product3->is_new)
																					<div class="ribbon-wrapper-red ribbon{{$x3}}">
																						<div class="ribbon-red">{{ Lang::get('mobileci.catalogue.new_ribbon') }}</div>
																					</div>
																					<?php $x3++;?>
																					@endif
																					@if($product3->on_coupons)
																					<div class="ribbon-wrapper-yellow ribbon{{$x3}}">
																						<div class="ribbon-yellow">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
																					</div>
																					<?php $x3++;?>
																					@endif
																					@if($product3->on_couponstocatch)
																					<div class="ribbon-wrapper-yellow-dash ribbon{{$x3}}">
																						<div class="ribbon-yellow-dash">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
																					</div>
																					<?php $x3++;?>
																					@endif
																				</div>
																				<div class="zoom-wrapper">
																					<div class="zoom"><a href="{{ asset($product3->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
																				</div>
																				<a href="{{ asset($product3->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product3->image) }}"></a>
																			</div>
																			<div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
																				<div class="row">
																					<div class="col-xs-12">
																						<h3>{{ $product3->product_name }}</h3>
																					</div>
																					<div class="col-xs-12">
																						<h4>{{ Lang::get('mobileci.catalogue.code') }} : {{ $product3->upc_code }}</h4>
																					</div>
																					<div class="col-xs-12 price">
																						@if(count($product3->variants) > 1)
																						<small>{{ Lang::get('mobileci.catalogue.starting_from') }}</small>
																						@endif
																						@if($product3->on_promo)
																							<h3 class="currency currency-promo"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="strike formatted-num">{{ $product3->min_price }}</span></h3>
																							<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product3->priceafterpromo }}</span></h3>
																						@else
																						<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product3->min_price }}</span></h3>
																						@endif
																					</div>
																				</div>
																			</div>
																		</div>
																		<div class="row catalogue-control-wrapper">
																			<div class="col-xs-6 catalogue-short-des ">
																				<p>{{ $product3->short_description }}</p>
																			</div>
																			<div class="col-xs-2 catalogue-control text-center">
																				<div class="circlet btn-blue detail-btn">
																					<a href="{{ url('customer/product?id='.$product3->product_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
																				</div>
																			</div>
																			@if(count($product3->variants) <= 1)
																			<div class="col-xs-2 col-xs-offset-1 catalogue-control price ">
																				<div class="circlet btn-blue cart-btn text-center">
																					<a class="product-add-to-cart" data-hascoupon="{{$product3->on_coupons}}" data-product-id="{{ $product3->product_id }}" data-product-variant-id="{{ $product3->variants[0]->product_variant_id }}" >
																						<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
																					</a>
																				</div>
																			</div>
																			@else
																			<div class="col-xs-2 col-xs-offset-1 catalogue-control price">
																				<div class="circlet btn-blue cart-btn text-center">
																					<a class="product-add-to-cart" href="{{ url('customer/product?id='.$product3->product_id.'#select-attribute') }}">
																						<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
																					</a>
																				</div>
																			</div>
																			@endif
																		</div>
																	</div>
																@endforeach
																<ul>
																@if(! is_null($lvl3->subfamilies))
																	@foreach($lvl3->subfamilies as $subfamily3)
																		<li data-family-container="{{ $subfamily3->category_id }}" data-family-container-level="{{ $subfamily3->category_level }}"><a class="family-a" data-family-id="{{ $subfamily3->category_id }}" data-family-level="{{ $subfamily3->category_level }}" data-family-isopen="@if($subfamily3->category_id == Session::get('f2')){{1}}@else{{0}}@endif" ><div class="family-label">{{ $subfamily3->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
																			<div class="product-list">
																				@if($subfamily3->category_id == Session::get('f4'))
																					@foreach($lvl4->records as $product4)
																						<div class="main-theme catalogue" id="product-{{$product4->product_id}}">
																							<div class="row row-xs-height catalogue-top">
																								<div class="col-xs-6 catalogue-img col-xs-height col-middle">
																									<div>
																										<?php $x4 = 1;?>
																										@if($product4->on_promo)
																										<div class="ribbon-wrapper-green ribbon{{$x4}}">
																											<div class="ribbon-green">{{ Lang::get('mobileci.catalogue.promo_ribbon') }}</div>
																										</div>
																										<?php $x4++;?>
																										@endif
																										@if($product4->is_new)
																										<div class="ribbon-wrapper-red ribbon{{$x4}}">
																											<div class="ribbon-red">{{ Lang::get('mobileci.catalogue.new_ribbon') }}</div>
																										</div>
																										<?php $x4++;?>
																										@endif
																										@if($product4->on_coupons)
																										<div class="ribbon-wrapper-yellow ribbon{{$x4}}">
																											<div class="ribbon-yellow">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
																										</div>
																										<?php $x4++;?>
																										@endif
																										@if($product4->on_couponstocatch)
																										<div class="ribbon-wrapper-yellow-dash ribbon{{$x4}}">
																											<div class="ribbon-yellow-dash">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
																										</div>
																										<?php $x4++;?>
																										@endif
																									</div>
																									<div class="zoom-wrapper">
																										<div class="zoom"><a href="{{ asset($product4->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
																									</div>
																									<a href="{{ asset($product4->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product4->image) }}"></a>
																								</div>
																								<div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
																									<div class="row">
																										<div class="col-xs-12">
																											<h3>{{ $product4->product_name }}</h3>
																										</div>
																										<div class="col-xs-12">
																											<h4>{{ Lang::get('mobileci.catalogue.code') }} : {{ $product4->upc_code }}</h4>
																										</div>
																										<div class="col-xs-12 price">
																											@if(count($product4->variants) > 1)
																											<small>{{ Lang::get('mobileci.catalogue.starting_from') }}</small>
																											@endif
																											@if($product4->on_promo)
																												<h3 class="currency currency-promo"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="strike formatted-num">{{ $product4->min_price }}</span></h3>
																												<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product4->priceafterpromo }}</span></h3>
																											@else
																											<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product4->min_price }}</span></h3>
																											@endif
																										</div>
																									</div>
																								</div>
																							</div>
																							<div class="row catalogue-control-wrapper">
																								<div class="col-xs-6 catalogue-short-des ">
																									<p>{{ $product4->short_description }}</p>
																								</div>
																								<div class="col-xs-2 catalogue-control text-center">
																									<div class="circlet btn-blue detail-btn">
																										<a href="{{ url('customer/product?id='.$product4->product_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
																									</div>
																								</div>
																								@if(count($product4->variants) <= 1)
																								<div class="col-xs-2 col-xs-offset-1 catalogue-control price ">
																									<div class="circlet btn-blue cart-btn text-center">
																										<a class="product-add-to-cart" data-hascoupon="{{$product4->on_coupons}}" data-product-id="{{ $product4->product_id }}" data-product-variant-id="{{ $product4->variants[0]->product_variant_id }}" >
																											<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
																										</a>
																									</div>
																								</div>
																								@else
																								<div class="col-xs-2 col-xs-offset-1 catalogue-control price">
																									<div class="circlet btn-blue cart-btn text-center">
																										<a class="product-add-to-cart" href="{{ url('customer/product?id='.$product4->product_id.'#select-attribute') }}">
																											<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
																										</a>
																									</div>
																								</div>
																								@endif
																							</div>
																						</div>
																					@endforeach
																					<ul>
																					@if(! is_null($lvl4->subfamilies))
																						@foreach($lvl4->subfamilies as $subfamily4)
																							<li data-family-container="{{ $subfamily4->category_id }}" data-family-container-level="{{ $subfamily4->category_level }}"><a class="family-a" data-family-id="{{ $subfamily4->category_id }}" data-family-level="{{ $subfamily4->category_level }}" data-family-isopen="@if($subfamily4->category_id == Session::get('f2')){{1}}@else{{0}}@endif" ><div class="family-label">{{ $subfamily4->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
																								<div class="product-list">
																									@if($subfamily4->category_id == Session::get('f5'))
																										@foreach($lvl5->records as $product5)
																											<div class="main-theme catalogue" id="product-{{$product5->product_id}}">
																												<div class="row row-xs-height catalogue-top">
																													<div class="col-xs-6 catalogue-img col-xs-height col-middle">
																														<div>
																															<?php $x5 = 1;?>
																															@if($product5->on_promo)
																															<div class="ribbon-wrapper-green ribbon{{$x5}}">
																																<div class="ribbon-green">{{ Lang::get('mobileci.catalogue.promo_ribbon') }}</div>
																															</div>
																															<?php $x5++;?>
																															@endif
																															@if($product5->is_new)
																															<div class="ribbon-wrapper-red ribbon{{$x5}}">
																																<div class="ribbon-red">{{ Lang::get('mobileci.catalogue.new_ribbon') }}</div>
																															</div>
																															<?php $x5++;?>
																															@endif
																															@if($product5->on_coupons)
																															<div class="ribbon-wrapper-yellow ribbon{{$x5}}">
																																<div class="ribbon-yellow">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
																															</div>
																															<?php $x5++;?>
																															@endif
																															@if($product5->on_couponstocatch)
																															<div class="ribbon-wrapper-yellow-dash ribbon{{$x5}}">
																																<div class="ribbon-yellow-dash">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
																															</div>
																															<?php $x5++;?>
																															@endif
																														</div>
																														<div class="zoom-wrapper">
																															<div class="zoom"><a href="{{ asset($product5->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
																														</div>
																														<a href="{{ asset($product5->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product5->image) }}"></a>
																													</div>
																													<div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
																														<div class="row">
																															<div class="col-xs-12">
																																<h3>{{ $product5->product_name }}</h3>
																															</div>
																															<div class="col-xs-12">
																																<h4>{{ Lang::get('mobileci.catalogue.code') }} : {{ $product5->upc_code }}</h4>
																															</div>
																															<div class="col-xs-12 price">
																																@if(count($product5->variants) > 1)
																																<small>{{ Lang::get('mobileci.catalogue.starting_from') }}</small>
																																@endif
																																@if($product5->on_promo)
																																	<h3 class="currency currency-promo"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="strike formatted-num">{{ $product5->min_price }}</span></h3>
																																	<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product5->priceafterpromo }}</span></h3>
																																@else
																																<h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product5->min_price }}</h3>
																																@endif
																															</div>
																														</div>
																													</div>
																												</div>
																												<div class="row catalogue-control-wrapper">
																													<div class="col-xs-6 catalogue-short-des ">
																														<p>{{ $product5->short_description }}</p>
																													</div>
																													<div class="col-xs-2 catalogue-control text-center">
																														<div class="circlet btn-blue detail-btn">
																															<a href="{{ url('customer/product?id='.$product5->product_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
																														</div>
																													</div>
																													@if(count($product5->variants) <= 1)
																													<div class="col-xs-2 col-xs-offset-1 catalogue-control price ">
																														<div class="circlet btn-blue cart-btn text-center">
																															<a class="product-add-to-cart" data-hascoupon="{{$product5->on_coupons}}" data-product-id="{{ $product5->product_id }}" data-product-variant-id="{{ $product5->variants[0]->product_variant_id }}" >
																																<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
																															</a>
																														</div>
																													</div>
																													@else
																													<div class="col-xs-2 col-xs-offset-1 catalogue-control price">
																														<div class="circlet btn-blue cart-btn text-center">
																															<a class="product-add-to-cart" href="{{ url('customer/product?id='.$product5->product_id.'#select-attribute') }}">
																																<span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
																															</a>
																														</div>
																													</div>
																													@endif
																												</div>
																											</div>
																										@endforeach
																										{{-- - --}}
																									@endif
																								</div>
																							</li>
																						@endforeach
																					@endif
																					</ul>
																				@endif
																			</div>
																		</li>
																	@endforeach
																@endif
																</ul>
															@endif
														</div>
													</li>
												@endforeach
											@endif
											</ul>
										@endif
									</div>
								</li>
							@endforeach
						@endif
						</ul>
					@endif
				</div>
			</li>
		@endforeach
		</ul>
	@endif
</div>

<input id="reloadValue" type="hidden" name="reloadValue" value="" />
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
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
{{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
<script type="text/javascript">
	@if($retailer->parent->currency == 'IDR')
    $('.formatted-num').each(function(index){
      $(this).text(parseFloat($(this).text()).toFixed(0)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 0, vMin: -9999999999.99});
    });
    @else
    $('.formatted-num').each(function(index){
      $(this).text(parseFloat($(this).text()).toFixed(2)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 2, vMin: -9999999999.99});
    });
    @endif
	var timeout = null;
    $(window).scroll(function () {
	    if (!timeout) {
	        timeout = setTimeout(function () {
	            $.cookie('lastpos', $(window).scrollTop(), { expires: 300 });
	            clearTimeout(timeout);
	            timeout = null;
	        }, 250);
	    }
	});

	$(document).ready(function(){
		$('.family-list').on('click', 'a.family-a', function(event){
			if($(this).data('family-isopen') == 0){
				var families = [];
				// last clicked family history
				@if(!empty(Session::get('f1')))
					families[0] = {{ Session::get('f1') }};
					@if(!empty(Session::get('f2')))
						families[1] = {{ Session::get('f2') }};
						@if(!empty(Session::get('f3')))
							families[2] = {{ Session::get('f3') }};
							@if(!empty(Session::get('f4')))
								families[3] = {{ Session::get('f4') }};
								@if(!empty(Session::get('f5')))
									families[4] = {{ Session::get('f5') }};
								@endif
							@endif
						@endif
					@endif
				@endif
				var open_level = $(this).data('family-level');
				$('li[data-family-container-level="'+open_level+'"] .product-list').css('display','visible').slideUp('slow');
				$('li[data-family-container-level="'+open_level+'"] .family-label i').attr('class', 'fa fa-chevron-circle-down');
				$('li[data-family-container-level="'+open_level+'"] .family-a').data('family-isopen', 0);
				$('li[data-family-container-level="'+open_level+'"] .family-a').attr('data-family-isopen', 0);
				$(this).data('family-isopen', 1);
				$(this).attr('data-family-isopen', 1);

				var a = $(this);
				var family_id = $(this).data('family-id');
				var family_level = $(this).data('family-level');

				var aopen = $('a[data-family-isopen="1"]');
				var families = [];
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
						family_level: family_level
					}
				}).done(function(data){
					if(data == 'Invalid session data.'){
						location.replace('/customer');
					} else {
						a.parent('[data-family-container="'+ family_id +'"]').children("div.product-list").css('display', 'none').html(data).slideDown('slow');
						$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-chevron-circle-up');
						@if($retailer->parent->currency == 'IDR')
					        a.parent('[data-family-container="'+ family_id +'"]').find('.formatted-num').each(function(index){
						      $(this).text(parseFloat($(this).text()).toFixed(0)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 0, vMin: -9999999999.99});
						    });
					    @else
					        a.parent('[data-family-container="'+ family_id +'"]').find('.formatted-num').each(function(index){
						      $(this).text(parseFloat($(this).text()).toFixed(2)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 2, vMin: -9999999999.99});
						    });
					    @endif
					}
				});
			} else {
				$(this).data('family-isopen', 0);
				$(this).attr('data-family-isopen', 0);
				var family_id = $(this).data('family-id');
				var family_level = $(this).data('family-level');
				$('*[data-family-container="'+ family_id +'"]').children("div.product-list").slideUp('slow');
				$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-chevron-circle-down');
			}
			
		});
		// add to cart
		$('.family-list').off('click', 'a.product-add-to-cart').on('click', 'a.product-add-to-cart', function(event){
			$('#hasCouponModal .modal-body p').html('');
			var cc = 0;
			var used_coupons = [];
			var anchor = $(this);
			var hasCoupon = $(this).data('hascoupon');
			var prodid = $(this).data('product-id');
			var prodvarid = $(this).data('product-variant-id');
			var img = $(this).children('i');
			var cart = $('#shopping-cart');
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
						if(cc == 0) {
							cc++;
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
						}
					});
					
					$('#hasCouponModal').on('hide.bs.modal', function(){
						anchor.show();
						$('.cart-spinner').hide();
					});

					$('#hasCouponModal').off('click', '#denyCoupon').on('click', '#denyCoupon', function($event){
						if(cc == 0) {
							cc++;
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
						}
					});
				} else {
					if(cc == 0) {
						cc++;
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
							}
						});
					}
				}
			}
			// $('.family-list').unbind('click');
		});
	});
</script>
@stop
