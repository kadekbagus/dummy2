@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    @if($data->status === 1)
        @if(sizeof($data->records) > 0)
            @foreach($data->records as $promo)
                <div class="main-theme catalogue promo-list" id="promo-{{$promo->promotion_id}}">
                    <div class="row row-xs-height catalogue-top">
                        <div class="col-xs-6 catalogue-img col-xs-height col-middle">
                            <div class="zoom-wrapper">
                                <div class="zoom"><a href="{{ asset($promo->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
                            </div>
                            <a href="{{ asset($promo->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($promo->image) }}"></a>
                        </div>
                        <div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
                            <div class="row">
                                <div class="col-xs-12">
                                    <h3>{{ $promo->promotion_name }}</h3>
                                </div>
                                @if($promo->promotion_type == 'product' || $promo->promotion_type == 'cart')
                                    <div class="col-xs-12">
                                    @if($promo->promotionrule->discount_object_type == 'product')
                                        <p class="promo-item">{{ Lang::get('mobileci.promotion_list.product_label') }}: {{ $promo->promotionrule->discountproduct->product_name }}</p>
                                    @elseif($promo->promotionrule->discount_object_type == 'family')
                                        <p class="promo-item">
                                            {{ Lang::get('mobileci.promotion_list.category_label') }}: 
                                            @if(!is_null($promo->promotionrule->discountcategory1))
                                            <span>{{ $promo->promotionrule->discountcategory1->category_name }}</span>
                                            @endif
                                            @if(!is_null($promo->promotionrule->discountcategory2))
                                            <span>{{ $promo->promotionrule->discountcategory2->category_name }}</span>
                                            @endif
                                            @if(!is_null($promo->promotionrule->discountcategory3))
                                            <span>{{ $promo->promotionrule->discountcategory3->category_name }}</span>
                                            @endif
                                            @if(!is_null($promo->promotionrule->discountcategory4))
                                            <span>{{ $promo->promotionrule->discountcategory4->category_name }}</span>
                                            @endif
                                            @if(!is_null($promo->promotionrule->discountcategory5))
                                            <span>{{ $promo->promotionrule->discountcategory5->category_name }}</span>
                                            @endif
                                        </p>
                                    @endif
                                    </div>
                                @endif
                                <div class="col-xs-12">
                                    @if($promo->is_permanent == 'Y')
                                        <h4>{{ Lang::get('mobileci.catalogue.from')}}: {{ date('j M Y', strtotime($promo->begin_date)) }}</h4>
                                    @else
                                        <h4>{{ date('j M Y', strtotime($promo->begin_date)) }} - {{ date('j M Y', strtotime($promo->end_date)) }}</h4>
                                    @endif
                                </div>
                                <div class="col-xs-6 catalogue-control text-right pull-right">
                                    <div class="circlet btn-blue detail-btn pull-right vertically-spaced">
                                        <a href="{{ url('customer/promotion?promoid='.$promo->promotion_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="row padded">
                <div class="col-xs-12">
                    <h4>{{ Lang::get('mobileci.promotion_list.no_promo') }}</h4>
                </div>
            </div>
        @endif
    @endif
@stop

@section('modals')
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
                        <p>
                            <b>ENJOY FREE</b>
                            <br>
                            <span style="color:#0aa5d5; font-size:20px; font-weight: bold;">UNLIMITED</span>
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
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
    $(window).bind("pageshow", function(event) {
        if (event.originalEvent.persisted) {
            window.location.reload() 
        }
    });
    $(document).ready(function(){
        $(document).on('show.bs.modal', '.modal', function (event) {
            var zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack').css('z-index', 0).addClass('modal-stack');
            }, 0);
        });
        if(!$.cookie('dismiss_verification_popup')) {
            $.cookie('dismiss_verification_popup', 't', { expires: 1 });
            $('#verifyModal').modal();
        }
        if(window.location.hash){
            var hash = window.location.hash;
            var producthash = "#promo-"+hash.replace(/^.*?(#|$)/,'');
            console.log(producthash);
            var hashoffset = $(producthash).offset();
            var hashoffsettop = hashoffset.top-68;
            setTimeout(function() {
                $(window).scrollTop(hashoffsettop);
            }, 1);
        }
    });
</script>
@stop