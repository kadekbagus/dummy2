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
<!-- promotion -->
<div class="row relative-wrapper">
    <div class="actions-container" style="z-index: 102;">
        <div class="circle-plus action-btn">
            <div class="circle">
                <div class="horizontal"></div>
                <div class="vertical"></div>
            </div>
        </div>
        <div class="actions-panel" style="display: none;">
            <ul class="list-unstyled">
                <li>
                    @if(count($promotion->tenants) > 0)
                        @if(count($promotion->tenants) === 1)
                        <a data-href="{{ route('ci-tenant-detail', ['id' => $promotion->tenants[0]->merchant_id, 'name' => Str::slug($promotion->tenants[0]->name)]) }}" href="{{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-tenant-detail', ['id' => $promotion->tenants[0]->merchant_id, 'name' => Str::slug($promotion->tenants[0]->name)], $session) }}}">
                        @else
                        <a data-href="{{ route('ci-tenant-list', ['promotion_id' => $promotion->news_id]) }}" href="{{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-tenant-list', ['promotion_id' => $promotion->news_id], $session) }}}">
                        @endif
                            <span class="fa fa-stack icon">
                                <i class="fa fa-circle fa-stack-2x"></i>
                                <i class="fa fa-shopping-cart fa-inverse fa-stack-1x"></i>
                            </span>
                            <span class="text">{{{ Lang::get('mobileci.tenant.see_tenants') }}} </span>
                        </a>
                    @else
                        <!-- No promotions on tenants -->
                    <a class="disabled">
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-shopping-cart fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{{ Lang::get('mobileci.tenant.see_tenants') }}} </span>
                    </a>
                    @endif
                </li>
                @if ($is_logged_in)
                    @if(! empty($promotion->facebook_share_url))
                    <li>
                        <div class="fb-share-button" data-href="{{$promotion->facebook_share_url}}" data-layout="button"></div>
                    </li>
                    @endif
                @endif
            </ul>
        </div>
    </div>
    <div class="col-xs-12 product-detail img-wrapper"  style="z-index: 100;">
      <div class="vertical-align-middle-outer">
        <div class="vertical-align-middle-inner">
            @if(($promotion->image!='mobile-ci/images/default_promotion.png'))
            <a href="{{{ asset($promotion->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img src="{{ asset($promotion->image) }}"></a>
            @else
            <img src="{{ asset($promotion->image) }}">
            @endif
        </div>
      </div>
    </div>
</div>
<div class="row product-info padded" style="z-index: 101;">
    <div class="col-xs-12">
        <p>{{ nl2br(e($promotion->description)) }}</p>
    </div>
    <div class="col-xs-12">
        <h4><strong>{{{ Lang::get('mobileci.promotion.validity') }}}</strong></h4>
        <p>{{{ date('d M Y', strtotime($promotion->begin_date)) }}} - {{{ date('d M Y', strtotime($promotion->end_date)) }}}</p>
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
            // Set fromSource in localStorage.
            localStorage.setItem('fromSource', 'mall-promotion');

            // Actions button event handler
            $('.action-btn').on('click', function() {
                $('.actions-container').toggleClass('alive');
                $('.actions-panel').slideToggle();
            });

            setTimeout(function() {
                $('.actions-container').fadeIn();
            }, 500);

            $(window).scroll(function(){
                s = $(window).scrollTop();
                $('.product-detail img').css('-webkit-transform', 'translateY('+(s/3)+'px)');
            });
        });
    </script>
@stop
