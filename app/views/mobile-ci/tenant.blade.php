@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    {{ HTML::style('mobile-ci/stylesheet/lightslider.min.css') }}
    <style type="text/css">
    .product-detail .tab-pane p {
        font-size: .9em;
    }
    </style>
@stop

@section('content')
<!-- product -->
<div class="row product">
    <div class="col-xs-12 product-img">
        @if(count($tenant->mediaLogoOrig) > 0)
        <div class="zoom-wrapper">
            <div class="zoom"><a href="#" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img alt="" src="{{ asset('mobile-ci/images/product-zoom.png') }}" ></a></div>
        </div>
        @endif
        <ul id="image-gallery" class="gallery list-unstyled cS-hidden">
            @if(!count($tenant->mediaLogoOrig) > 0)
            <li data-thumb="{{ asset('mobile-ci/images/default_product.png') }}">
                <span class="gallery-helper"></span>
                <img class="img-responsive" src="{{ asset('mobile-ci/images/default_product.png') }}"/>
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

    <div class="col-xs-12 main-theme product-detail">
        <div class="row">
            <div class="col-xs-12">
                <h3>{{ $tenant->name }}</h3>
            </div>
            <div class="col-xs-12">
                <p>{{ $tenant->description }}</p>
            </div>
        </div>
    </div>

    <div class="col-xs-12 main-theme-mall product-detail where">
        <div class="row">
            <div class="col-xs-12">
            </div>
            <div class="col-xs-12">

                <br/>
                <p>{{ $tenant->name }}</p>
                <ul class="where-list">
                    <li><i class="fa fa-map-marker fa-lg" style="padding-left: 11px;"></i>  {{ $retailer->name }}{{{ !empty($tenant->floor) ? ' - ' . $tenant->floor : '' }}}{{{ !empty($tenant->unit) ? ' - ' . $tenant->unit : '' }}}</li>
                    <li><i class="fa fa-globe fa-lg"></i>  {{{ (($tenant->url) != '') ? 'http://'.$tenant->url : '-' }}}</li>
                    <li><i class="fa fa-phone-square fa-lg"></i>  @if($tenant->phone != '') <a href="tel:{{{ $tenant->phone }}}"> {{{ $tenant->phone }}}</a> @else - @endif</li>
                </ul>

                @if ($box_url)
                <a style="position:relative;margin-bottom:16px;" class="btn btn-danger btn-block" href="{{ $box_url }}">{{ $enter_shop_text or 'Go to Store' }}</a>
                @endif

                @foreach($tenant->mediaMapOrig as $map)
                <p>
                    <a href="{{ asset($map->path) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img class="img-responsive maps" src="{{ asset($map->path) }}"></a>
                </p>
                @endforeach
            </div>
        </div>
        <div role="tabpanel" class="">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active"><a href="#promotions" aria-controls="promotions" role="tab" data-toggle="tab">{{ Lang::get('mobileci.tenant.promotions') }}</a></li>
            <li role="presentation"><a href="#news" aria-controls="news" role="tab" data-toggle="tab">{{ Lang::get('mobileci.tenant.news') }}</a></li>
        </ul>
        <!-- Tab panes -->
        <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="promotions">
                @if(sizeof($tenant->newsPromotions) > 0)
                    @foreach($tenant->newsPromotions as $tenant->newsPromotions)
                        <div class="main-theme-mall catalogue" id="promotions-{{$tenant->newsPromotions->promotion_id}}">
                            <div class="row catalogue-top">
                                <div class="col-xs-3 catalogue-img">
                                    @if(!empty($tenant->newsPromotions->image))
                                    <a href="{{ asset($tenant->newsPromotions->image) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer text-left"><img class="img-responsive" alt="" src="{{ asset($tenant->newsPromotions->image) }}"></a>
                                    @else
                                    <img class="img-responsive" src="{{ asset('mobile-ci/images/default_product.png') }}"/>
                                    @endif
                                </div>
                                <div class="col-xs-6">
                                    <h4>{{ $tenant->newsPromotions->news_name }}</h4>
                                    @if (strlen($tenant->newsPromotions->description) > 120)
                                    <p>{{{ mb_substr($tenant->newsPromotions->description, 0, 120, 'UTF-8') }}} [<a href="{{ url('customer/mallpromotion?id='.$tenant->newsPromotions->news_id) }}">...</a>] </p>
                                    @else
                                    <p>{{{ $tenant->newsPromotions->description }}}</p>
                                    @endif
                                </div>
                                <div class="col-xs-3" style="margin-top:20px">
                                    <div class="circlet btn-blue detail-btn pull-right">
                                        <a href="{{ url('customer/mallpromotion?id='.$tenant->newsPromotions->news_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="row padded">
                        <div class="col-xs-12">
                            <p>{{ Lang::get('mobileci.tenant.check_our_new_promo') }}</p>
                        </div>
                    </div>
                @endif
            </div>
            <div role="tabpanel" class="tab-pane" id="news">
                @if(sizeof($tenant->news) > 0)
                    @foreach($tenant->news as $tenant->news)
                        <div class="main-theme-mall catalogue" id="news-{{$tenant->news->promotion_id}}">
                            <div class="row catalogue-top">
                                <div class="col-xs-3 catalogue-img">
                                    @if(!empty($tenant->news->image))
                                    <a href="{{ asset($tenant->news->image) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer text-left"><img class="img-responsive" alt="" src="{{ asset($tenant->news->image) }}"></a>
                                    @else
                                    <img class="img-responsive" src="{{ asset('mobile-ci/images/default_product.png') }}"/>
                                    @endif
                                </div>
                                <div class="col-xs-6">
                                    <h4>{{ $tenant->news->news_name }}</h4>
                                    @if (strlen($tenant->news->description) > 120)
                                    <p>{{{ mb_substr($tenant->news->description, 0, 120, 'UTF-8') }}} [<a href="{{ url('customer/mallnewsdetail?id='.$tenant->news->news_id) }}">...</a>] </p>
                                    @else
                                    <p>{{{ $tenant->news->description }}}</p>
                                    @endif
                                </div>
                                <div class="col-xs-3" style="margin-top:20px">
                                    <div class="circlet btn-blue detail-btn pull-right">
                                        <a href="{{ url('customer/mallnewsdetail?id='.$tenant->news->news_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="row padded">
                        <div class="col-xs-12">
                            <p>{{ Lang::get('mobileci.tenant.check_our_latest_news') }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
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
    {{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
    {{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
    {{ HTML::script('mobile-ci/scripts/lightslider.min.js') }}
    {{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
    <script type="text/javascript">
        $(document).ready(function(){
            $('#image-gallery').lightSlider({
                gallery:true,
                item:1,
                thumbItem:4,
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
        });
    </script>
@stop
