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
            @if(!is_null($widget_singles->tenant))
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.tenant') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{ !empty($widget_singles->tenant->widget_slogan) ? $widget_singles->tenant->widget_slogan : '&nbsp;'}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget_singles->tenant->animation == 'horizontal') id="slider1" @endif>
                        <li>
                            <a class="widget-link" data-widget="{{ $widget_singles->tenant->widget_id }}" href="{{ url('customer/tenants') }}">
                                @if(!empty($widget_singles->tenant->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget_singles->tenant->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_tenants_directory.png') }}" />
                                @endif
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
            @else
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.tenant') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>&nbsp;</div>
                </header>
                <section class="widget-single">
                    <div class="callbacks_container">
                        <ul class="rslides">
                            <li>
                                <a class="widget-link" data-widget="" href="{{ url('customer/tenants') }}">
                                    <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_tenants_directory.png') }}"/>
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
            @endif
            @if(!is_null($widget_singles->promotion))
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.promotion') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{ !empty($widget_singles->promotion->widget_slogan) ? $widget_singles->promotion->widget_slogan : '&nbsp;'}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget_singles->promotion->animation == 'horizontal') id="slider3" @endif>
                        <li>
                            <a data-widget="{{ $widget_singles->promotion->widget_id }}" class="widget-link" href="{{ url('customer/mallpromotions') }}">
                                @if(!empty($widget_singles->promotion->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                    <img class="img-responsive text-center vcenter" src="{{ asset($widget_singles->promotion->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                    <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_promotion.png') }}" />
                                @endif
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
            @else
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.promotion') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>&nbsp;</div>
                </header>
                <section class="widget-single">
                    <div class="callbacks_container">
                        <ul class="rslides">
                            <li>
                                <a class="widget-link" data-widget="" href="{{ url('customer/mallpromotions') }}">
                                    <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_promotion.png') }}"/>
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
            @endif
        </div>
        <div class="row">
            @if(!is_null($widget_singles->news))
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.news') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{ !empty($widget_singles->news->widget_slogan) ? $widget_singles->news->widget_slogan : '&nbsp;'}}</div>
                </header>
                <section class="widget-single">
                    <ul class="rslides" @if($widget_singles->news->animation == 'horizontal') id="slider4" @endif>
                        <li>
                            <a data-widget="{{ $widget_singles->news->widget_id }}" class="widget-link" href="{{ url('customer/mallnews') }}">
                                @if(!empty($widget_singles->news->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                <img class="img-responsive text-center vcenter" src="{{ asset($widget_singles->news->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                @else
                                <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_news.png') }}" />
                                @endif
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
            @else
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.news') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>&nbsp;</div>
                </header>
                <section class="widget-single">
                    <div class="callbacks_container">
                        <ul class="rslides">
                            <li>
                                <a class="widget-link" data-widget="" href="{{ url('customer/mallnews') }}">
                                    <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_news.png') }}"/>
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
            @endif
            @if(!is_null($widget_singles->coupon))
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.coupon') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{ !empty($widget_singles->coupon->widget_slogan) ? $widget_singles->coupon->widget_slogan : '&nbsp;'}}</div>
                </header>
                <section class="widget-single">
                    <div class="callbacks_container">
                        <ul class="rslides" @if($widget_singles->coupon->animation == 'horizontal') id="slider2" @endif>
                            <li>
                                <a data-widget="{{ $widget_singles->coupon->widget_id }}" class="widget-link" href="{{ url('customer/mallcoupons') }}">
                                    @if(!empty($widget_singles->coupon->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                    <img class="img-responsive text-center vcenter" src="{{ asset($widget_singles->coupon->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                    @else
                                    <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_coupon.png') }}" />
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
            @else
            <div class="single-widget-container col-xs-6 col-sm-6">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.coupon') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>&nbsp;</div>
                </header>
                <section class="widget-single">
                    <div class="callbacks_container">
                        <ul class="rslides">
                            <li>
                                <a class="widget-link" data-widget="" href="{{ url('customer/mallcoupons') }}">
                                    <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_coupon.png') }}"/>
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
            @endif
        </div>
        @if(is_object($widget_flags->enable_lucky_draw_widget) && $widget_flags->enable_lucky_draw_widget->setting_value === 'true')
        <div class="row">
            @if(!is_null($widget_singles->luckydraw))
            <div class="single-widget-container col-xs-12 col-sm-12">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.lucky_draw') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>{{ !empty($widget_singles->luckydraw->widget_slogan) ? $widget_singles->luckydraw->widget_slogan : '&nbsp;'}}</div>
                </header>
                <section class="widget-single">
                    <div class="callbacks_container">
                        <ul class="rslides" @if($widget_singles->luckydraw->animation == 'horizontal') id="slider2" @endif>
                            <li>
                                <a data-widget="{{ $widget_singles->luckydraw->widget_id }}" class="widget-link" href="{{ url('customer/luckydraw') }}">
                                    @if(!empty($widget_singles->luckydraw->media()->where('media_name_long', 'home_widget_resized_default')->first()))
                                    <img class="img-responsive text-center vcenter" src="{{ asset($widget_singles->luckydraw->media()->where('media_name_long', 'home_widget_resized_default')->first()->path) }}" />
                                    @else
                                    <img class="img-responsive text-center vcenter" src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" />
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
            @else
            <div class="single-widget-container col-xs-12 col-sm-12">
                <header class="widget-title">
                    <div><strong>{{ Lang::get('mobileci.widgets.lucky_draw') }}</strong></div>
                </header>
                <header class="widget-title widget-subtitle">
                    <div>&nbsp;</div>
                </header>
                <section class="widget-single">
                    <div class="callbacks_container">
                        <ul class="rslides">
                            <li>
                                @if(! empty($widget_flags->enable_lucky_draw) && $widget_flags->enable_lucky_draw->setting_value == 'true')
                                <a class="widget-link" data-widget="" href="{{ url('customer/luckydraw') }}">
                                @else
                                <a class="widget-link" data-widget="" id="emptyLuck">
                                @endif
                                    <img class="img-responsive vcenter" src="{{ asset('mobile-ci/images/default_lucky_number.png') }}"/>
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
            @endif

        </div>
        @endif
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
                            <b>{{ Lang::get('mobileci.modals.enjoy_free') }}</b>
                            <br>
                            @if ($active_user)
                            <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">{{ Lang::get('mobileci.modals.unlimited') }}</span>
                            @else
                            <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">30 {{ Lang::get('mobileci.modals.minutes') }}</span>
                            @endif
                            <br>
                            <b>{{ Lang::get('mobileci.modals.internet') }}</b>
                            <br><br>
                            <b>{{ Lang::get('mobileci.modals.check_out_our') }}</b>
                            <br>
                            <b><span style="color:#0aa5d5;">{{ Lang::get('mobileci.page_title.promotion') }}</span> {{ Lang::get('mobileci.modals.and') }} <span style="color:#0aa5d5;">{{ Lang::get('mobileci.page_title.coupon_single') }}</span></b>
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
                            <label for="verifyModalCheck">{{ Lang::get('mobileci.modals.do_not_display') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="userActivationModal" tabindex="-1" role="dialog" aria-labelledby="userActivationModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="userActivationModalLabel"><i class="fa fa-envelope-o"></i> {{ Lang::get('mobileci.promotion.info') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <p style="font-size:15px;">
                            {{ Lang::get('mobileci.modals.message_user_activation') }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.okay') }}</button>
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
        $('#verifyModal').on('hidden.bs.modal', function () {
            if ($('#verifyModalCheck')[0].checked) {
                $.cookie(cookie_dismiss_name, 't', {expires: 3650});
            }
        });
        @if(! is_null($events))
        $('#promoModal').on('show.bs.modal', function() {
            $.ajax({
                url: '{{ route('display-event-popup-activity') }}',
                data: {
                    eventdata: '{{$events->event_id}}'
                },
                method: 'POST'
            });
        });
        @endif
        {{-- a sequence of modals... --}}
        var modals = [
            {
                selector: '#verifyModal',
                display: get('internet_info') == 'yes' && !$.cookie(cookie_dismiss_name)
            },
@if(! is_null($events))
            {
                selector: '#promoModal',
                display: true
            },
@endif
            {
                selector: '#userActivationModal',
                display: get('activation_popup') == 'yes'
            }
        ];
        var modalIndex;

        for (modalIndex = 0; modalIndex < modals.length; modalIndex++) {
            {{-- for each displayable modal, after it is hidden try and display the next displayable modal --}}
            if (modals[modalIndex].display) {
                $(modals[modalIndex].selector).on('hidden.bs.modal', (function(myIndex) {
                    return function() {
                        for (var i = myIndex + 1; i < modals.length; i++) {
                            if (modals[i].display) {
                                $(modals[i].selector).modal();
                                return;
                            }
                        }
                    }
                })(modalIndex));
            }
        }

        {{-- display the first displayable modal --}}
        for (modalIndex = 0; modalIndex < modals.length; modalIndex++) {
            if (modals[modalIndex].display) {
                $(modals[modalIndex].selector).modal();
                break;
            }
        }

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
