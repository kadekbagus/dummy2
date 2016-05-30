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
<div class="row relative-wrapper">
    <div class="actions-container" style="z-index: 102;">
        <a class="action-btn">
            <span class="fa fa-stack fa-2x">
                <i class="fa fa-plus fa-inverse fa-stack-2x"> </i>
                <i class="fa fa-plus-circle fa-stack-2x"> </i>
            </span>
        </a>
        <div class="actions-panel" style="display: none;">
            <ul class="list-unstyled">
                <li>
                    @if(count($news->tenants) > 0)
                    <a data-href="{{ route('ci-tenant-list', ['news_id' => $news->news_id]) }}" href="{{{ $urlblock->blockedRoute('ci-tenant-list', ['news_id' => $news->news_id]) }}}">
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-th-list fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{{ Lang::get('mobileci.tenant.see_tenants') }}}</span>
                    </a>
                    @else
                        <!-- No news on tenants -->
                    <a class="disabled">
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-th-list fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{{ Lang::get('mobileci.tenant.see_tenants') }}}</span>
                    </a>
                    @endif
                </li>
                @if ($urlblock->isLoggedIn())
                    @if(! empty($news->facebook_share_url))
                    <li>
                        <div class="fb-share-button" data-href="{{$news->facebook_share_url}}" data-layout="button"></div>
                    </li>
                    @endif
                @endif
            </ul>
        </div>
    </div>
    <div class="col-xs-12 product-detail" style="z-index: 100;">
        @if(($news->image!='mobile-ci/images/default_news.png'))
        <a href="{{{ asset($news->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img src="{{ asset($news->image) }}"></a>
        @else
        <img class="img-responsive" src="{{ asset($news->image) }}">
        @endif
    </div>
</div>
<div class="row product-info padded" style="z-index: 101;">
    <div class="col-xs-12">
        <p>{{ nl2br(e($news->description)) }}</p>
    </div>
    <div class="col-xs-12">
        <h4><strong>{{{ Lang::get('mobileci.promotion.validity') }}}</strong></h4>
        <p>{{{ date('d M Y', strtotime($news->begin_date)) }}} - {{{ date('d M Y', strtotime($news->end_date)) }}}</p>
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
            localStorage.setItem('fromSource', 'mall-news-detail');

            // Actions button event handler
            $('.action-btn').on('click', function() {
                $('.actions-container').toggleClass('alive');
                $('.actions-panel').slideToggle();
            });

            $(window).scroll(function(){
                s = $(window).scrollTop();
                $('.product-detail img').css('-webkit-transform', 'translateY('+(s/3)+'px)');
            });
        });
    </script>
@stop
