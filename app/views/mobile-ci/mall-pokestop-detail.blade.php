@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    <!-- product -->
    <div class="row relative-wrapper">
        <div class="col-xs-12 product-detail" style="z-index: 100;">
            <div class="vertical-align-middle-outer">
                <div class="vertical-align-middle-inner">
                    <a href="{{{ asset($pokestop->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer">
                        <img src="{{{ asset($pokestop->image) }}}">
                        <span class="zoom-info">
                            Click to zoom
                            <i class="fa fa-expand"> </i>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
    <div class="col-xs-12">
        <ul class="legends list-unstyled">
            <li>
                <span class="legend">
                    <img src="{{ asset('mobile-ci/images/pokegym_gtm.png') }}" />
                </span>
                <span class="info">
                    Pokegym
                </span>
            </li>
            <li>
                <span class="legend">
                    <img src="{{ asset('mobile-ci/images/pokestops_gtm.png') }}" />
                </span>
                <span class="info">
                    Pokestop
                </span>
            </li>
        </ul>
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
            localStorage.setItem('fromSource', 'mall-pokestop-detail');

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
