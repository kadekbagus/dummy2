@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    <style type="text/css">
    .product-detail .tab-pane p {
        font-size: .9em;
    }
    </style>
@stop

@section('tenant_tab')
    {{-- todo: create flag for this tabs --}}
    @if(sizeof($tenant->newsPromotionsProfiling) > 0 || sizeof($tenant->newsProfiling) > 0 || sizeof($tenant->coupons) > 0)
    <div class="header-tenant-tab">
        <ul>
            @if(sizeof($tenant->newsPromotionsProfiling))
            <li><a id="slide-tab-promo">{{Lang::get('mobileci.page_title.promotions')}}</a></li>
            @endif
            @if(sizeof($tenant->newsProfiling))
            <li><a id="slide-tab-news">{{Lang::get('mobileci.page_title.news')}}</a></li>
            @endif
            @if(sizeof($tenant->couponsProfiling))
            <li><a id="slide-tab-coupon">{{Lang::get('mobileci.page_title.coupon_plural')}}</a></li>
            @endif
        </ul>
    </div>
    @endif
@stop

@section('content')
<div class="slide-tab-container">
    <div id="slide-tab-promo-container">
        @if(sizeof($tenant->newsPromotionsProfiling) > 0)
            @foreach($tenant->newsPromotionsProfiling as $promotab)
                <div class="col-xs-12 col-sm-12">
                    <section class="list-item-single-tenant">
                        <a class="list-item-link" href="{{ url('customer/mallpromotion?id='.$promotab->news_id) }}">
                            <div class="list-item-info">
                                <header class="list-item-title">
                                    <div><strong>{{ $promotab->news_name }}</strong></div>
                                </header>
                                <header class="list-item-subtitle">
                                    <div>
                                        {{-- Limit description per two line and 45 total character --}}
                                        <?php
                                            $desc = explode("\n", $promotab->description);
                                        ?>
                                        @if (mb_strlen($promotab->description) > 45)
                                            @if (count($desc) > 1)
                                                <?php
                                                    $two_row = array_slice($desc, 0, 1);
                                                ?>
                                                @foreach ($two_row as $key => $value)
                                                    @if ($key === 0)
                                                        {{{ $value }}} <br>
                                                    @else
                                                        {{{ $value }}} ...
                                                    @endif
                                                @endforeach
                                            @else
                                                {{{ mb_substr($promotab->description, 0, 45, 'UTF-8') . '...' }}}
                                            @endif
                                        @else
                                            @if (count($desc) > 1)
                                                <?php
                                                    $two_row = array_slice($desc, 0, 1);
                                                ?>
                                                @foreach ($two_row as $key => $value)
                                                    @if ($key === 0)
                                                        {{{ $value }}} <br>
                                                    @else
                                                        {{{ $value }}} ...
                                                    @endif
                                                @endforeach
                                            @else
                                                {{{ mb_substr($promotab->description, 0, 45, 'UTF-8') }}}
                                            @endif
                                        @endif
                                    </div>
                                </header>
                            </div>
                            <div class="list-vignette-non-tenant"></div>
                            @if(!empty($promotab->image))
                            <img class="img-responsive img-fit-tenant" src="{{ asset($promotab->image) }}" />
                            @else
                            <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_promotion.png') }}"/>
                            @endif
                        </a>
                    </section>
                </div>
            @endforeach
        @endif
    </div>
    <div id="slide-tab-news-container">
        @if(sizeof($tenant->newsProfiling) > 0)
            @foreach($tenant->newsProfiling as $newstab)
                <div class="col-xs-12 col-sm-12">
                    <section class="list-item-single-tenant">
                        <a class="list-item-link" href="{{ url('customer/mallnewsdetail?id='.$newstab->news_id) }}">
                            <div class="list-item-info">
                                <header class="list-item-title">
                                    <div><strong>{{ $newstab->news_name }}</strong></div>
                                </header>
                                <header class="list-item-subtitle">
                                    <div>
                                        {{-- Limit description per two line and 45 total character --}}
                                        <?php
                                            $desc = explode("\n", $newstab->description);
                                        ?>
                                        @if (mb_strlen($newstab->description) > 45)
                                            @if (count($desc) > 1)
                                                <?php
                                                    $two_row = array_slice($desc, 0, 1);
                                                ?>
                                                @foreach ($two_row as $key => $value)
                                                    @if ($key === 0)
                                                        {{{ $value }}} <br>
                                                    @else
                                                        {{{ $value }}} ...
                                                    @endif
                                                @endforeach
                                            @else
                                                {{{ mb_substr($newstab->description, 0, 45, 'UTF-8') . '...' }}}
                                            @endif
                                        @else
                                            @if (count($desc) > 1)
                                                <?php
                                                    $two_row = array_slice($desc, 0, 1);
                                                ?>
                                                @foreach ($two_row as $key => $value)
                                                    @if ($key === 0)
                                                        {{{ $value }}} <br>
                                                    @else
                                                        {{{ $value }}} ...
                                                    @endif
                                                @endforeach
                                            @else
                                                {{{ mb_substr($newstab->description, 0, 45, 'UTF-8') }}}
                                            @endif
                                        @endif
                                    </div>
                                </header>
                            </div>
                            <div class="list-vignette-non-tenant"></div>
                            @if(!empty($newstab->image))
                            <img class="img-responsive img-fit-tenant" src="{{ asset($newstab->image) }}" />
                            @else
                            <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_news.png') }}"/>
                            @endif
                        </a>
                    </section>
                </div>
            @endforeach
        @endif
    </div>
    <div id="slide-tab-coupon-container">
        @if(sizeof($tenant->couponsProfiling) > 0)
            @foreach($tenant->couponsProfiling as $coupontab)
                <div class="col-xs-12 col-sm-12">
                    <section class="list-item-single-tenant">
                        <a class="list-item-link" href="{{ url('customer/mallcouponcampaign?id='.$coupontab->promotion_id) }}">
                            <div class="list-item-info">
                                <header class="list-item-title">
                                    <div><strong>{{ $coupontab->promotion_name }}</strong></div>
                                </header>
                                <header class="list-item-subtitle">
                                    <div>
                                        {{-- Limit description per two line and 45 total character --}}
                                        <?php
                                            $desc = explode("\n", $coupontab->description);
                                        ?>
                                        @if (mb_strlen($coupontab->description) > 45)
                                            @if (count($desc) > 1)
                                                <?php
                                                    $two_row = array_slice($desc, 0, 1);
                                                ?>
                                                @foreach ($two_row as $key => $value)
                                                    @if ($key === 0)
                                                        {{{ $value }}} <br>
                                                    @else
                                                        {{{ $value }}} ...
                                                    @endif
                                                @endforeach
                                            @else
                                                {{{ mb_substr($coupontab->description, 0, 45, 'UTF-8') . '...' }}}
                                            @endif
                                        @else
                                            @if (count($desc) > 1)
                                                <?php
                                                    $two_row = array_slice($desc, 0, 1);
                                                ?>
                                                @foreach ($two_row as $key => $value)
                                                    @if ($key === 0)
                                                        {{{ $value }}} <br>
                                                    @else
                                                        {{{ $value }}} ...
                                                    @endif
                                                @endforeach
                                            @else
                                                {{{ mb_substr($coupontab->description, 0, 45, 'UTF-8') }}}
                                            @endif
                                        @endif
                                    </div>
                                </header>
                            </div>
                            <div class="list-vignette-non-tenant"></div>
                            @if(!empty($coupontab->image))
                            <img class="img-responsive img-fit-tenant" src="{{ asset($coupontab->image) }}" />
                            @else
                            <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_coupon.png') }}"/>
                            @endif
                        </a>
                    </section>
                </div>
            @endforeach
        @endif
    </div>
</div>
<div class="slide-menu-backdrop-tab"></div>

<!-- product -->
<div class="row header-tenant-tab-present">
    <div class="col-xs-12">
        <ul id="image-gallery" class="gallery list-unstyled cS-hidden">
            @if(!count($tenant->mediaLogoOrig) > 0)
            <li data-thumb="{{ asset('mobile-ci/images/default_tenants_directory.png') }}">
                <span class="gallery-helper"></span>
                <img class="img-responsive" src="{{ asset('mobile-ci/images/default_tenants_directory.png') }}"/>
            </li>
            @endif
            @foreach($tenant->mediaLogoOrig as $media)
            <li data-thumb="{{ asset($media->path) }}">
                <span class="gallery-helper"></span>
                <a href="{{ asset($media->path) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive" src="{{ asset($media->path) }}" /></a>
            </li>
            @endforeach
            @foreach($tenant->mediaImageOrig as $media)
            <li data-thumb="{{ asset($media->path) }}">
                <a href="{{ asset($media->path) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive" src="{{ asset($media->path) }}" /></a>
            </li>
            @endforeach
        </ul>
    </div>
</div>
<div class="row padded">
    <div class="col-xs-12 font-1-3">
        <p>{{ nl2br($tenant->description) }}</p>
        <ul class="where-list">
            <li><i class="fa fa-map-marker fa-lg" style="padding-left: 11px;"></i>  {{{ !empty($tenant->floor) ? $tenant->floor : '' }}}{{{ !empty($tenant->unit) ? ' - ' . $tenant->unit : '' }}}</li>
            <li><i class="fa fa-globe fa-lg"></i>  {{{ (($tenant->url) != '') ? 'http://'.$tenant->url : '-' }}}</li>
            <li><i class="fa fa-phone-square fa-lg"></i>  @if($tenant->phone != '') <a href="tel:{{{ $tenant->phone }}}"> {{{ $tenant->phone }}}</a> @else - @endif</li>
        </ul>
    </div>
</div>
<div class="row padded vertically-spaced">
    <div class="col-xs-12 font-1-3">
        @foreach($tenant->mediaMapOrig as $map)
            <a href="{{ asset($map->path) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive maps" src="{{ asset($map->path) }}"></a>
        @endforeach
    </div>

</div>
<div class="row padded">
    <div class="col-xs-12 font-1-3">
        @if ($box_url)
        <a style="position:relative;margin-bottom:16px;" class="btn btn-danger btn-block" href="{{ $box_url }}">{{ $enter_shop_text or 'Go to Store' }}</a>
        @endif
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
    {{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
    {{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
    <script type="text/javascript">
        $(document).ready(function(){
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
            function hideOpenTabs() {
                if($('#slide-tab-news-container').is(':visible')) {
                    $('#slide-tab-news-container').hide();
                    $('#slide-tab-news').closest('li').toggleClass('active');
                    $('#slide-tab-news').blur();
                }
                if($('#slide-tab-promo-container').is(':visible')) {
                    $('#slide-tab-promo-container').hide();
                    $('#slide-tab-promo').closest('li').toggleClass('active');
                    $('#slide-tab-promo').blur();
                }
                if($('#slide-tab-coupon-container').is(':visible')) {
                    $('#slide-tab-coupon-container').hide();
                    $('#slide-tab-coupon').closest('li').toggleClass('active');
                    $('#slide-tab-coupon').blur();
                }
            }
            // set the slide tab container so it could be scrolled
            $('.slide-tab-container').css('height', ($(window).height()-92) + 'px');
            $(window).resize(function(){
                $('.slide-tab-container').css('height', ($(window).height()-92) + 'px');
            });
            $('.slide-tab-container').click(function(){
                hideOpenTabs();
                $('.slide-tab-container').toggle('slide', {direction: 'up'}, 'slow');
                $('.slide-menu-backdrop-tab').toggle('fade', 'slow');
                $('html').toggleClass('freeze-scroll');
            });
            $('#slide-tab-promo').click(function(){
                if($('#slide-tab-news-container').is(':visible') || $('#slide-tab-coupon-container').is(':visible')) {
                    $('#slide-tab-news-container').hide();
                    $('#slide-tab-coupon-container').hide();
                    $('#slide-tab-news').closest('li').removeClass('active');
                    $('#slide-tab-coupon').closest('li').removeClass('active');
                } else {
                    $('.slide-tab-container').toggle('slide', {direction: 'up'}, 'slow');
                    $('.slide-menu-backdrop-tab').toggle('fade', 'slow');
                    $('html').toggleClass('freeze-scroll');
                }
                $('#slide-tab-promo-container').toggle('fade', 'slow');
                $('#slide-tab-promo').closest('li').toggleClass('active');
                $('#slide-tab-promo').blur();
            });
            $('#slide-tab-news').click(function(){
                if($('#slide-tab-promo-container').is(':visible') || $('#slide-tab-coupon-container').is(':visible')) {
                    $('#slide-tab-promo-container').hide();
                    $('#slide-tab-coupon-container').hide();
                    $('#slide-tab-promo').closest('li').removeClass('active');
                    $('#slide-tab-coupon').closest('li').removeClass('active');
                } else {
                    $('.slide-tab-container').toggle('slide', {direction: 'up'}, 'slow');
                    $('.slide-menu-backdrop-tab').toggle('fade', 'slow');
                    $('html').toggleClass('freeze-scroll');
                }
                $('#slide-tab-news-container').toggle('fade', 'slow');
                $('#slide-tab-news').closest('li').toggleClass('active');
                $('#slide-tab-news').blur();
            });
            $('#slide-tab-coupon').click(function(){
                if($('#slide-tab-promo-container').is(':visible') || $('#slide-tab-news-container').is(':visible')) {
                    $('#slide-tab-promo-container').hide();
                    $('#slide-tab-news-container').hide();
                    $('#slide-tab-promo').closest('li').removeClass('active');
                    $('#slide-tab-news').closest('li').removeClass('active');
                } else {
                    $('.slide-tab-container').toggle('slide', {direction: 'up'}, 'slow');
                    $('.slide-menu-backdrop-tab').toggle('fade', 'slow');
                    $('html').toggleClass('freeze-scroll');
                }
                $('#slide-tab-coupon-container').toggle('fade', 'slow');
                $('#slide-tab-coupon').closest('li').toggleClass('active');
                $('#slide-tab-coupon').blur();
            });
        });
    </script>
@stop
