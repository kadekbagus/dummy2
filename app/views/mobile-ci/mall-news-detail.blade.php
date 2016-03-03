@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
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
<!-- product -->
<div class="row">
    <div class="col-xs-12 main-theme product-detail">
        @if(($news->image!='mobile-ci/images/default_news.png'))
        <a href="{{{ asset($news->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img src="{{ asset($news->image) }}"></a>
        @else
        <img class="img-responsive" src="{{ asset($news->image) }}">
        @endif
    </div>
</div>
<div class="row product-info padded">
    <div class="col-xs-12">
        <p>{{ nl2br($news->description) }}</p>
    </div>
    <div class="col-xs-12">
        <h4><strong>{{{ Lang::get('mobileci.promotion.validity') }}}</strong></h4>
        <p>{{{ date('d M Y', strtotime($news->begin_date)) }}} - {{{ date('d M Y', strtotime($news->end_date)) }}}</p>
    </div>
    @if(! empty($news->facebook_share_url))
    <div class="col-xs-12">
        <div class="fb-share-button" data-href="{{$news->facebook_share_url}}" data-layout="button_count"></div>
    </div>
    @endif
</div>
<div class="row vertically-spaced">
    @if(!$all_tenant_inactive)
        @if(count($news->tenants) > 0)
        <div class="col-xs-12 text-center padded">
            <a href="{{{ url('customer/tenants?news_id='.$news->news_id) }}}" class="btn btn-info btn-block">{{{ Lang::get('mobileci.tenant.see_tenants') }}}</a>
        </div>
        @endif
    @endif
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
