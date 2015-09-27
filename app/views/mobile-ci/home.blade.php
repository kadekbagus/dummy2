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
        <div class="row">
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>Tenant Directory</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>Check our tenant list!</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" id="slider1">
                        <li>
                            <a class="widget-link" data-widget="" href="{{ url('customer/tenants') }}">
                                <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/lmp-widgets/lippo_mall_puri_widget_tenants.jpg') }}"/>
                            </a>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>Promotions</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>Check our latest promotions!</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" id="slider1">
                        <li>
                            <a class="widget-link" data-widget="" href="{{ url('customer/mallpromotions') }}">
                                <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/lmp-widgets/lippo_mall_puri_widget_promotion.jpg') }}"/>
                            </a>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>News</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>Our latest news</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" id="slider1">
                        <li>
                            <a class="widget-link" data-widget="" href="{{ url('customer/mallnews') }}">
                                <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/lmp-widgets/lippo_mall_puri_widget_news.jpg') }}"/>
                            </a>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>Coupons</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>Check your coupons!</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" id="slider1">
                        <li>
                            <a class="widget-link" data-widget="" href="{{ url('customer/mallcoupons') }}">
                                <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/lmp-widgets/lippo_mall_puri_widget_coupon.jpg') }}"/>
                            </a>
                        </li>
                    </ul>
                </section>
            </div>

            @if(! empty($widget_flags->enable_lucky_draw_widget) && $widget_flags->enable_lucky_draw_widget->setting_value == 'true')
            <div class="single-widget-container col-xs-12 col-sm-12">
                <header class="widget-title">
                    <div><strong>Lucky Draw</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>Check your lucky numbers!</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" id="slider1">
                        <li>
                            @if(! empty($widget_flags->enable_lucky_draw) && $widget_flags->enable_lucky_draw->setting_value == 'true')
                            <a class="widget-link" data-widget="" href="{{ url('customer/luckydraw') }}">
                            @else
                            <a class="widget-link" data-widget="" id="emptyLuck">
                            @endif
                                <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/lmp-widgets/lippo_mall_puri_widget_lucky_draw.jpg') }}"/>
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
            @endif
        </div>
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
                    <div class="col-xs-12 text-center">
                        <p style="font-size:15px;">
                            <b>ENJOY FREE</b>
                            <br>
                            @if ($active_user)
                            <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">UNLIMITED</span>
                            @else
                            <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">30 MINUTES</span>
                            @endif
                            <br>
                            <b>INTERNET</b>
                            <br><br>
                            <b>CHECK OUT OUR</b>
                            <br>
                            <b><span style="color:#0aa5d5;">PROMOTIONS</span> AND <span style="color:#0aa5d5;">COUPONS</span></b>
                        </p>
                    </div>
                </div>
                <div class="row" style="margin-left: -30px; margin-right: -30px; margin-bottom: -15px;">
                    <div class="col-xs-12">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/pop-up-banner.png') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.okay') }}</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 text-left">
                            <input type="checkbox" name="verifyModalCheck" id="verifyModalCheck" style="top:2px;position:relative;">
                            <label for="verifyModalCheck">Do not display this message again</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/responsiveslides.min.js') }}
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
    var cookie_dismiss_name = 'dismiss_verification_popup';

    @if ($active_user)
    cookie_dismiss_name = 'dismiss_verification_popup_unlimited';
    @endif

    /**
     * Get Query String from the URL
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string n - Name of the parameter
     */
    function get(n)
    {
        var half = location.search.split(n + '=')[1];
        return half !== undefined ? decodeURIComponent(half.split('&')[0]) : null;
    }

    $(document).ready(function() {
        var displayModal = false;

        // Override the content of displayModal
        if (get('internet_info') == 'yes') {
            displayModal = true;
        }

        @if(! is_null($events))
            if(!$.cookie(cookie_dismiss_name) && displayModal) {
                $('#verifyModal').on('hidden.bs.modal', function () {
                    if ($('#verifyModalCheck')[0].checked) {
                        $.cookie(cookie_dismiss_name, 't', {expires: 3650});
                    }
                }).modal();
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
            });
        @else
        if (!$.cookie(cookie_dismiss_name)) {
            if (displayModal) {
                $('#verifyModal').on('hidden.bs.modal', function () {
                    if ($('#verifyModalCheck')[0].checked) {
                        $.cookie(cookie_dismiss_name, 't', {expires: 3650});
                    }
                }).modal();
            }
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
        $('#emptyLuck').click(function(){
          $('#noModalLabel').text('{{ Lang::get('mobileci.modals.info_title') }}');
          $('#noModalText').html('{{ Lang::get('mobileci.modals.message_no_lucky_draw') }}');
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