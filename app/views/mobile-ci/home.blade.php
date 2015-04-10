@extends('mobile-ci.layout')

@section('ext_style')
<style type="text/css">
.img-responsive{
    margin:0 auto;
}
</style>
@stop

@section('content')
<div class="container">
    <div class="mobile-ci home-widget widget-container">
        @foreach($widgets as $i => $widget)
        @if($i % 2 == 0)
        <div class="row">
            @endif
            @if($widget->widget_type == 'catalogue')
            <div class="single-widget-container @if($i < count($widgets) - 1) col-xs-6 col-sm-6 @else @if(count($widgets)%2 == 1) col-xs-12 col-sm-12 @else col-xs-6 col-sm-6 @endif @endif">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.catalogue') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{$widget->widget_slogan}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget->animation == 'horizontal') id="slider1" @endif>
                        @if($widget->animation == 'none')
                        <li>
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/catalogue') }}">
                                @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_catalogue.png') }}" />
                                @endif
                            </a>
                        </li>
                        @elseif($widget->animation == 'horizontal')
                        @if(count($random_products) > 0)
                        @foreach($random_products as $random_product)
                        <li>
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/catalogue') }}">
                                @if(!is_null($random_product->image))
                                <img class="img-responsive vcenter" src="{{ asset($random_product->image) }}"/>
                                @else
                                <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_product.png') }}"/>
                                @endif
                            </a>
                        </li>
                        @endforeach
                        @else
                        <li>
                            <img id="emptyNew" class="img-responsive" src="{{ asset('mobile-ci/images/default_catalogue.png') }}"/>
                        </li>
                        @endif
                        @endif
                    </ul>
                </section>
            </div>
            @endif
            @if($widget->widget_type == 'new_product')
            <div class="single-widget-container @if($i < count($widgets) - 1) col-xs-6 col-sm-6 @else  @if(count($widgets)%2 == 1) col-xs-12 col-sm-12 @else col-xs-6 col-sm-6 @endif @endif">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.new_product') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{$widget->widget_slogan}}</div>
                </header>
                <section class="widget-single">
                    <!-- Slideshow 4 -->
                    <div class="callbacks_container">
                        <ul class="rslides" @if($widget->animation == 'horizontal') id="slider2" @endif>
                            @if($widget->animation == 'none')
                            <li>
                                <a data-widget="{{ $widget->widget_id }}" class="widget-link" href="{{ url('customer/search?new=1') }}">
                                    @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                    <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                    @else
                                    <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_new_product.png') }}" />
                                    @endif
                                </a>
                            </li>
                            @elseif($widget->animation == 'horizontal')
                            @if(count($new_products) > 0)
                            @foreach($new_products as $new_product)
                            <li>
                                <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/search?new=1#'.$new_product->product_id) }}">
                                    @if(!is_null($new_product->image))
                                    <img class="img-responsive vcenter" src="{{ asset($new_product->image) }}"/>
                                    @else
                                    <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_new_product.png') }}"/>
                                    @endif
                                </a>
                            </li>
                            @endforeach
                            @else
                            <li>
                                <img id="emptyNew" class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_no_new_product.png') }}"/>
                            </li>
                            @endif
                            @endif
                        </ul>
                    </div>
                </section>
            </div>
            @endif
            @if($widget->widget_type == 'promotion')
            <div class="single-widget-container @if($i < count($widgets) - 1) col-xs-6 col-sm-6 @else  @if(count($widgets)%2 == 1) col-xs-12 col-sm-12 @else col-xs-6 col-sm-6 @endif @endif">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.promotion') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{$widget->widget_slogan}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget->animation == 'horizontal') id="slider3" @endif>
                        @if($widget->animation == 'none')
                        @if(count($promo_products) > 0)
                        <li>
                            <a data-widget="{{ $widget->widget_id }}" class="widget-link" href="{{ url('customer/promotions') }}">
                                @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_promotion.png') }}" />
                                @endif
                            </a>
                        </li>
                        @else
                        <li>
                            @if(!empty($widget->media->path))
                            <img class="img-responsive text-center vcenter" src="{{ asset($widget->media->path) }}" />
                            @else
                            <img id="emptyPromo" class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_no_promotion.png') }}"/>
                            @endif
                        </li>
                        @endif
                        @elseif($widget->animation == 'horizontal')
                        @if(count($promo_products) > 0)
                        @foreach($promo_products as $promo_product)
                        <li>
                            <a class="widget-link" data-widget="{{ $widget->widget_id }}" href="{{ url('customer/promotions#'.$promo_product->promotion_id) }}">
                                @if(!is_null($promo_product->image))
                                <img class="img-responsive vcenter" src="{{ asset($promo_product->image) }}"/>
                                @else
                                <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_promotion.png') }}"/>
                                @endif
                            </a>
                        </li>
                        @endforeach
                        @else
                        <li>
                            <img id="emptyPromo" class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_no_promotion.png') }}"/>
                        </li>
                        @endif
                        @endif
                    </ul>
                </section>
            </div>
            @endif
            @if($widget->widget_type == 'coupon')
            <div class="single-widget-container @if($i < count($widgets) - 1) col-xs-6 col-sm-6 @else  @if(count($widgets)%2 == 1) col-xs-12 col-sm-12 @else col-xs-6 col-sm-6 @endif @endif">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.coupon') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{$widget->widget_slogan}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget->animation == 'horizontal') id="slider4" @endif>
                        @if($widget->animation == 'none')
                        @if(count($coupons) > 0)
                        <li>
                            <a data-widget="{{ $widget->widget_id }}" class="widget-link" href="{{ url('customer/coupons') }}">
                                @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_coupon.png') }}" />
                                @endif
                            </a>
                        </li>
                        @else
                        <li>
                            @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                            <img id="emptyCoupon" class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                            @else
                            <img id="emptyCoupon" class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_no_coupon.png') }}"/>
                            @endif
                        </li>
                        @endif
                        @elseif($widget->animation == 'horizontal')
                        @if(count($coupons) > 0)
                        @foreach($coupons as $coupon)
                        <li>
                            <a data-widget="{{ $widget->widget_id }}" class="widget-link" href="{{ url('customer/coupons#'.$coupon->promotion_id) }}" >
                                @if(!empty($coupon->image))
                                <img class="img-responsive text-center vcenter" src="{{ asset($coupon->image) }}"/>
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_coupon.png') }}" />
                                @endif
                            </a>
                        </li>
                        @endforeach
                        @else
                        <li>
                            <img id="emptyCoupon" class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_no_coupon.png') }}"/>
                        </li>
                        @endif
                        @endif
                    </ul>
                </section>
            </div>
            @endif
            
            @if($i % 2 == 1)
        </div>
        @endif
        @endforeach
    </div>
    <div class="row">
        <div class="col-xs-12 text-center merchant-logo">
            <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" />
        </div>
    </div>
</div>
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="promoModal" tabindex="-1" role="dialog" aria-labelledby="promoModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="promoModalLabel">{{ Lang::get('mobileci.modals.event_title') }}</h4>
            </div>
            <div class="modal-body">
                <p id="promoModalText">
                    @if(! empty($events))
                    @if($events->event_type == 'link')
                    @if($events->link_object_type == 'product')
                    @if(! empty($events->image))
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/product?id='.$events->link_object_id1) }}">
                        <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/product?id='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @else
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/product?id='.$events->link_object_id1) }}">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/product?id='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @endif
                    @elseif($events->link_object_type == 'family')
                    @if(! empty($events->image))
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/category?'.$event_family_url_param) }}">
                        <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/category?'.$event_family_url_param) }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @else
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/category?'.$event_family_url_param) }}">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/category?id='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @endif
                    @elseif($events->link_object_type == 'promotion')
                    @if(! empty($events->image))
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/promotion?promoid='.$events->link_object_id1) }}">
                        <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/promotion?promoid='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @else
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/promotion?promoid='.$events->link_object_id1) }}">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/promotion?promoid='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @endif
                    @elseif($events->link_object_type == 'widget')
                    @if($events->widget_object_type == 'promotion')
                    @if(! empty($events->image))
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/promotions') }}">
                        <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/promotions') }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @else
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/promotions') }}">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/promotions') }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @endif
                    @elseif($events->widget_object_type == 'coupon')
                    @if(! empty($events->image))
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/coupons') }}">
                        <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/coupons') }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @else
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/coupons') }}">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/coupons') }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @endif
                    @elseif($events->widget_object_type == 'new_product')
                    @if(! empty($events->image))
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/search?new=1') }}">
                        <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/search?new=1') }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @else
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/search?new=1') }}">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/search?new=1') }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @endif
                    @elseif($events->widget_object_type == 'catalogue')
                    @if(! empty($events->image))
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/catalogue') }}">
                        <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/catalogue') }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @else
                    <a data-event="{{ $events->event_id }}" href="{{ url('customer/catalogue') }}">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br>
                    <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/catalogue') }}">{{ $events->event_name }}</a></b> <br>
                    {{ nl2br($events->description) }}
                    @endif
                    @endif
                    @endif
                    @elseif($events->event_type == 'informative')
                    @if(! empty($events->image))
                    <img class="img-responsive" src="{{ asset($events->image) }}">
                    <br>
                    <b>{{ $events->event_name }}</b> <br>
                    {{ nl2br($events->description) }}
                    @else
                    <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    <br>
                    <b>{{ $events->event_name }}</b> <br>
                    {{ nl2br($events->description) }}
                    @endif
                    @endif
                    @endif
                </p>
            </div>
            <div class="modal-footer">
                <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button></div>
            </div>
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="noModal" tabindex="-1" role="dialog" aria-labelledby="noModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="noModalLabel"></h4>
            </div>
            <div class="modal-body">
                <p id="noModalText"></p>
            </div>
            <div class="modal-footer">
                <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button></div>
            </div>
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/responsiveslides.min.js') }}
<script type="text/javascript">
    $(document).ready(function(){
        @if(! is_null($events))
          $('#promoModal').modal();
          $.ajax({
            url: '{{ route('display-event-popup-activity') }}',
            data: {
              eventdata: {{$events->event_id}}
            },
            method: 'POST'
          });
        @endif
        $('#emptyCoupon').click(function(){
          $('#noModalLabel').text('{{ Lang::get('mobileci.modals.info_title') }}');
          $('#noModalText').text('{{ Lang::get('mobileci.modals.message_no_coupon') }}');
          $('#noModal').modal();
        });
        $('#emptyNew').click(function(){
          $('#noModalLabel').text('{{ Lang::get('mobileci.modals.info_title') }}');
          $('#noModalText').text('{{ Lang::get('mobileci.modals.message_no_new_product') }}');
          $('#noModal').modal();
        });
        $('#emptyPromo').click(function(){
          $('#noModalLabel').text('{{ Lang::get('mobileci.modals.info_title') }}');
          $('#noModalText').text('{{ Lang::get('mobileci.modals.message_no_promotion') }}');
          $('#noModal').modal();
        });
        $('#promoModal a').click(function (event){ 
            var link = $(this).attr('href');
            var eventdata = $(this).data('event');

            event.preventDefault(); 
            $.ajax({
              data: {
                eventdata: eventdata
              },
              method: 'POST',
              url:apiPath+'customer/eventpopupactivity'
            }).always(function(data){
              window.location.assign(link);
            });
            return false; //for good measure
        });
        $("#slider1").responsiveSlides({
          auto: true,
          pager: false,
          nav: true,
          prevText: '<i class="fa fa-chevron-left"></i>',
          nextText: '<i class="fa fa-chevron-right"></i>',
          speed: 500
        });
        $("#slider2").responsiveSlides({
          auto: true,
          pager: false,
          nav: true,
          prevText: '<i class="fa fa-chevron-left"></i>',
          nextText: '<i class="fa fa-chevron-right"></i>',
          speed: 500
        });
        $("#slider3").responsiveSlides({
          auto: true,
          pager: false,
          nav: true,
          prevText: '<i class="fa fa-chevron-left"></i>',
          nextText: '<i class="fa fa-chevron-right"></i>',
          speed: 500
        });
        $("#slider4").responsiveSlides({
          auto: true,
          pager: false,
          nav: true,
          prevText: '<i class="fa fa-chevron-left"></i>',
          nextText: '<i class="fa fa-chevron-right"></i>',
          speed: 500
        });
        $('a.widget-link').click(function(){
          var link = $(this).attr('href');
          var widgetdata = $(this).data('widget');
          event.preventDefault(); 

          $.ajax({
            url: '{{ route('click-widget-activity') }}',
            data: {
              widgetdata: widgetdata
            },
            method: 'POST'
          }).always(function(){
            window.location.assign(link);
          });
          return false; //for good measure
        });
        $.each($('.rslides li'), function(i, v){
           $(this).css('height', $(this).width());
        });
    });
    $(window).resize(function(){
        $.each($('.rslides li'), function(i, v){
            $(this).css('height', $(this).width());
        });
    });
    $(window).ready(function(){
        $.each($('.rslides li'), function(i, v){
            $(this).css('height', $(this).width());
        });
    });
</script>
@stop