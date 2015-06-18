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
            @if($widget->widget_type == 'tenant')
            <div class="single-widget-container @if($i < count($widgets) - 1) col-xs-6 col-sm-6 @else @if(count($widgets)%2 == 1) col-xs-12 col-sm-12 @else col-xs-6 col-sm-6 @endif @endif">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.tenant') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{$widget->widget_slogan}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget->animation == 'horizontal') id="slider1" @endif>
                        <li>
                            <a class="widget-link" data-widget="" href="{{ url('customer/tenants') }}">
                                @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_tenants_directory.png') }}" />
                                @endif
                            </a>
                        </li>
                    </ul>
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
                    <!-- Slideshow 4 -->
                    <div class="callbacks_container">
                        <ul class="rslides" @if($widget->animation == 'horizontal') id="slider2" @endif>
                            <li>
                                <a class="widget-link" data-widget="" href="{{ url('customer/mallpromotions') }}">
                                    @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                    <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                    @else
                                    <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_promotion.png') }}" />
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
            @endif
            @if($widget->widget_type == 'news')
            <div class="single-widget-container @if($i < count($widgets) - 1) col-xs-6 col-sm-6 @else  @if(count($widgets)%2 == 1) col-xs-12 col-sm-12 @else col-xs-6 col-sm-6 @endif @endif">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.news') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{$widget->widget_slogan}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget->animation == 'horizontal') id="slider3" @endif>
                        <li>
                            <a class="widget-link" data-widget="" href="{{ url('customer/mallnews') }}">
                                @if(!empty($widget->media->path))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_no_promotion.png') }}"/>
                                @endif
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
            @endif
            @if($widget->widget_type == 'coupon')
                @if(! empty($enable_coupon) && $enable_coupon->setting_value == 'true')
            <div class="single-widget-container @if($i < count($widgets) - 1) col-xs-6 col-sm-6 @else  @if(count($widgets)%2 == 1) col-xs-12 col-sm-12 @else col-xs-6 col-sm-6 @endif @endif">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.coupon') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{$widget->widget_slogan}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget->animation == 'horizontal') id="slider4" @endif>
                        <li>
                            <a class="widget-link" data-widget="" href="{{ url('customer/mallcoupons') }}">
                                @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_coupon.png') }}"/>
                                @endif
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
                @endif
            @endif
            @if($widget->widget_type == 'lucky_draw')
                @if(! empty($enable_lucky_draw_widget) && $enable_lucky_draw_widget->setting_value == 'true')
            <div class="single-widget-container @if($i < count($widgets) - 1) col-xs-6 col-sm-6 @else  @if(count($widgets)%2 == 1) col-xs-12 col-sm-12 @else col-xs-6 col-sm-6 @endif @endif">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.lucky_draw') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{$widget->widget_slogan}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget->animation == 'horizontal') id="slider5" @endif>
                        <li>
                            <a class="widget-link" data-widget="" href="{{ url('customer/luckydraw') }}">
                                @if(!empty($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_no_coupon.png') }}"/>
                                @endif
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
                @endif
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
                    @elseif($events->link_object_type == 'retailer')
                        @if(! empty($events->image))
                        <a data-event="{{ $events->event_id }}" href="{{ url('customer/tenants?event_id='.$events->event_id) }}">
                            <img class="img-responsive" src="{{ asset($events->image) }}">
                        </a>
                        <br>
                        <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/tenants?event_id='.$events->event_id) }}">{{ $events->event_name }}</a></b> <br>
                        {{ nl2br($events->description) }}
                        @else
                        <a data-event="{{ $events->event_id }}" href="{{ url('customer/tenants?event_id='.$events->event_id) }}">
                            <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                        </a>
                        <br>
                        <b><a data-event="{{ $events->event_id }}" href="{{ url('customer/tenants?event_id='.$events->event_id) }}">{{ $events->event_name }}</a></b> <br>
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

<!-- Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1" role="dialog" aria-labelledby="verifyModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="verifyModalLabel"><i class="fa fa-envelope-o"></i> Info</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12">
                        <p>
                            Mau browsing sepuasnya?<br>
                            Atau mau ikutan undian berhadiah?<br>
                            Buka email Anda untuk verifikasi sekarang juga!!<br>
                        </p>
                    </div>
                </div>
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
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
    $(document).ready(function(){
        // $(document).on('show.bs.modal', '.modal', function (event) {
        //     var zIndex = 1040 + (10 * $('.modal:visible').length);
        //     $(this).css('z-index', zIndex);
        //     setTimeout(function() {
        //         $('.modal-backdrop').not('.modal-stack').css('z-index', 0).addClass('modal-stack');
        //     }, 0);
        // });
        
        @if(! is_null($events))
            if(!$.cookie('dismiss_verification_popup')) {
                $.cookie('dismiss_verification_popup', 't', { expires: 1 });
                $('#verifyModal').modal();
            } else {
                $('#promoModal').modal();
                $.ajax({
                    url: '{{ route('display-event-popup-activity') }}',
                    data: {
                        eventdata: {{$events->event_id}}
                    },
                    method: 'POST'
                });
            }
            $('#verifyModal').on('hide.bs.modal', function(e){
                $('#promoModal').modal();
                $.ajax({
                    url: '{{ route('display-event-popup-activity') }}',
                    data: {
                        eventdata: {{$events->event_id}}
                    },
                    method: 'POST'
                });
            })
        @else
            if(!$.cookie('dismiss_verification_popup')) {
                $.cookie('dismiss_verification_popup', 't', { expires: 1 });
                $('#verifyModal').modal();
            }
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