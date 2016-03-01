@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    <style type="text/css">
        .modal-spinner{
            display: none;
            font-size: 2.5em;
            color: #fff;
            position: absolute;
            top: 50%;
            margin: 0 auto;
            width: 100%;
        }
        .tenant-list{
            margin:0;padding:0;
        }
        .tenant-list li{
            list-style: none;
        }
    </style>
@stop

@section('fb_scripts')
@if(! empty($facebookInfo))
@if(! empty($facebookInfo['version']) && ! empty($facebookInfo['app_id']))
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version={{$facebookInfo['version']}}&appId={{$facebookInfo['app_id']}}";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
@endif
@endif
@stop


@section('content')
<div class="row">
    <div class="col-xs-12  main-theme product-detail">
        @if(($coupon->image!='mobile-ci/images/default_coupon.png'))
        <a href="{{{ asset($coupon->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive" alt="" src="{{{ asset($coupon->image) }}}" ></a>
        @else
        <img class="img-responsive" alt="" src="{{{ asset($coupon->image) }}}" >
        @endif
    </div>
</div>
<div class="row product-info padded">
    <div class="col-xs-12">
        <div class="row">
            <div class="col-xs-12">
                <p>{{{ $coupon->description }}}</p>
            </div>
            <div class="col-xs-12">
                <p>{{{ $coupon->long_description }}}</p>
            </div>
            <div class="col-xs-12">
                <h4><strong>{{{ Lang::get('mobileci.coupon_detail.validity_label') }}}</strong></h4>
                <p>{{{ date('d M Y', strtotime($coupon->begin_date)) }}} - {{{ date('d M Y', strtotime($coupon->end_date)) }}}</p>
            </div>
            @if(! empty($coupon->facebook_share_url))
            <div class="col-xs-12">
                <div class="fb-share-button" data-href="{{$coupon->facebook_share_url}}" data-layout="button_count"></div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="row vertically-spaced">
    <div class="col-xs-12 padded">
        @if(count($tenants) > 0)
        <div class="row vertically-spaced">
            <div class="col-xs-12 text-center">
                <a href="{{{ url('customer/tenants?coupon_id='.$coupon->promotion_id) }}}" class="btn btn-info btn-block">{{{ Lang::get('mobileci.tenant.see_tenants') }}}</a>
            </div>
        </div>
        @endif
    </div>
</div>
<!-- end of product -->
@stop

@section('ext_script_bot')
    {{ HTML::script(Config::get('orbit.cdn.featherlight.1_0_3', 'mobile-ci/scripts/featherlight.min.js')) }}
    {{-- Script fallback --}}
    <script>
        if (typeof $().featherlight === 'undefined') {
            document.write('<script src="{{asset('mobile-ci/scripts/featherlight.min.js')}}">\x3C/script>');
        }
    </script>
    {{-- End of Script fallback --}}
    <script type="text/javascript">
        $(document).ready(function(){
            $(window).scroll(function(){
                s = $(window).scrollTop();
                $('.product-detail img').css('-webkit-transform', 'translateY('+(s/3)+'px)');
            });
        });
    </script>
@stop
