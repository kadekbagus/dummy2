@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    <style type="text/css">
    .product-detail .tab-pane p {
        font-size: .9em;
    }
    </style>
@stop

@section('content')
<div class="slide-tab-container" style="z-index: 103;">
</div>
<div class="slide-menu-backdrop-tab"></div>

<!-- product -->
<div class="row relative-wrapper">

    <div class="col-xs-12" style="z-index: 100;">
        @if(count($service->mediaLogoOrig) === 0 && count($service->mediaImageOrig) === 0)
        <img class="img-responsive img-center" src="{{ asset('mobile-ci/images/default_services_directory.png') }}"/>
        @else
        <ul id="image-gallery" class="gallery list-unstyled cS-hidden">
            @if(!count($service->mediaLogoOrig) > 0)
            <li data-thumb="{{ asset('mobile-ci/images/default_services_directory.png') }}">
                <span class="gallery-helper"></span>
                <div class="vertical-align-middle-outer">
                    <div class="vertical-align-middle-inner">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_services_directory.png') }}"/>
                    </div>
                </div>
            </li>
            @endif
            @foreach($service->mediaLogoOrig as $media)
            <li data-thumb="{{ asset($media->path) }}">
                <span class="gallery-helper"></span>
                <div class="vertical-align-middle-outer">
                    <div class="vertical-align-middle-inner">
                        <a href="{{ asset($media->path) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer">
                            <img class="img-responsive" src="{{ asset($media->path) }}" />
                        </a>
                    </div>
                </div>
            </li>
            @endforeach
            @foreach($service->mediaImageOrig as $media)
            <li data-thumb="{{ asset($media->path) }}">
                <a href="{{ asset($media->path) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer">
                    <div class="vertical-align-middle-outer">
                        <div class="vertical-align-middle-inner">
                            <img class="img-responsive" src="{{ asset($media->path) }}" />
                        </div>
                    </div>
                </a>
            </li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
<div class="row padded" style="z-index: 101;">
    <div class="col-xs-12 font-1-3">
        <input type="checkbox" class="read-more-state" id="post-2" />
        <p>{{ nl2br(e($service->description)) }}</p>
        <ul class="where-list read-more-wrap">
            <li><span class="tenant-list-icon"><i class="fa fa-map-marker fa-lg" style="padding-left: 11px;"></i></span><p class="tenant-list-text">{{{ !empty($service->location) ? $service->location : '-' }}}</p></li>

            @if(count($service->categories) > 0)
                <li><span class="tenant-list-icon"><i class="fa fa-list-ul"></i></span></li>
                @foreach($service->categories as $idx => $category)
                    @if($idx >= 3)
                        <li class="read-more-target"><span class="tenant-list-text">{{{ $category->category_name }}}</span></li>
                    @else
                        <li><span class="tenant-list-text">{{{ $category->category_name }}}</span></li>
                    @endif
                @endforeach

                @if(count($service->categories) > 3)
                    <li><span class="tenant-list-text"><label for="post-2" class="read-more-trigger"></label></span></li>
                @endif
            @else
                <li><span class="tenant-list-icon"><i class="fa fa-list-ul"></i></span><p class="tenant-list-text">-</p></li>
            @endif
        </ul>
    </div>
</div>
<div class="row padded vertically-spaced">
    <div class="col-xs-12 font-1-3">
        @foreach($service->mediaMapOrig as $map)
            <a href="{{ asset($map->path) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive maps" src="{{ asset($map->path) }}"></a>
        @endforeach
    </div>
</div>


<!-- end of product -->
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="hasCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.activation.close') }}</span></button>
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
            // Check if browser supports LocalStorage
            if(typeof(Storage) !== 'undefined') {
                localStorage.setItem('fromSource', 'detail');
            }

            $('#image-gallery').lightSlider({
                gallery:false,
                item:1,
                slideMargin: 0,
                speed:500,
                pause:2000,
                auto:true,
                loop:true,
                onSliderLoad: function() {
                    $('.zoom a').attr('href', $('.lslide.active img').attr('src'));
                    $('#image-gallery').removeClass('cS-hidden');
                },
                onAfterSlide: function() {
                    $('.zoom a').attr('href', $('.lslide.active img').attr('src'));
                }
            });
            $('#myTab a').click(function (e) {
                e.preventDefault()
                $(this).tab('show')
            })

            // set the slide tab container so it could be scrolled
            $('.slide-tab-container').css('height', ($(window).height()-92) + 'px');
            $(window).resize(function(){
                $('.slide-tab-container').css('height', ($(window).height()-92) + 'px');
            });
            $('.slide-tab-container').click(function(){
                hideOpenTabs();
                $('.slide-tab-container').toggle('slide', {direction: 'up'}, 'slow');
                $('.slide-menu-backdrop-tab').toggle('fade', 'slow');
                $('body').toggleClass('freeze-scroll');
                tabOpen = false;
            });

        });
    </script>
@stop
