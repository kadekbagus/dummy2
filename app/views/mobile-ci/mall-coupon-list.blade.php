@extends('mobile-ci.layout')

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
            @if($data->status === 1)
                <div class="catalogue-wrapper">
                @foreach($data->records as $coupon)
                    <div class="col-xs-12 col-sm-12 item-x" data-ids="{{$coupon->promotion_id}}"  id="item-{{$coupon->promotion_id}}">
                        <section class="list-item-single-tenant">
                            <a class="list-item-link" data-href="{{ route('ci-coupon-detail', ['id' => $coupon->promotion_id, 'name' => Str::slug($coupon->promotion_name)]) }}" href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-coupon-detail', ['id' => $coupon->promotion_id, 'name' => Str::slug($coupon->promotion_name)], $session) }}">
                                @if($is_coupon_wallet)

                                <span class="fa-stack fa-2x pull-right couponbadge-container couponbadge-shadow couponbadge-medium" data-count="{{ ($coupon->quantity > 99) ? '99+' : $coupon->quantity }}">
                                   <i class="fa fa-circle fa-stack-2x color-base"></i>
                                   <i class="fa fa-ticket fa-stack-1x color-icon couponbadge-ticket-small"></i>
                                   <i class="fa fa-certificate fa-stack-2x couponbadge color-badge couponbadge-small"></i>
                                </span>

                                @else

                                @if(!$is_coupon_wallet || !$coupon->added_to_wallet)
                                <div class="coupon-wallet pull-right">
                                    <span class="fa-stack fa-2x">
                                        <i class="fa fae-wallet fa-stack-2x"></i>
                                        <i class="fa fa-circle fa-stack-2x"></i>
                                        @if($coupon)
                                        <i class="fa fa-plus fa-stack-1x"></i>
                                        @else
                                        <i class="fa fa-check fa-stack-1x"></i>
                                        @endif
                                    </span>
                                    <span class="wallet-text">
                                        @if($coupon)
                                            {{ Lang::get('mobileci.coupon.add_wallet') }}
                                        @else
                                            {{ Lang::get('mobileci.coupon.added_wallet') }}
                                        @endif
                                    </span>
                                </div>
                                @endif

                </div>
                @if($data->returned_records < $data->total_records)
                    <div class="row">
                        <div class="col-xs-12 padded">
                            <button class="btn btn-info btn-block" id="load-more-x">{{Lang::get('mobileci.notification.load_more_btn')}}</button>
                        </div>
                    </div>
                @endif
            @else
                @if(Input::get('keyword') === null)
                <div class="row padded">
                    <div class="col-xs-12">
                        <h4>{{ Lang::get('mobileci.greetings.no_coupons_listing') }}</h4>
                    </div>
                </div>
                @else
                <div class="row padded">
                    <div class="col-xs-12">
                        <h4>{{ Lang::get('mobileci.search.no_result') }}</h4>
                    </div>
                </div>
                @endif
            @endif
            </div>
        </div>
    </div>
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="hasCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
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
<script type="text/javascript">
    $(document).ready(function(){
        var listOfIDs = [],
            helperObject = {
                'skip': 0
            };
        loadMoreX('my-coupon', listOfIDs, helperObject);

        $('body').on('click', '#load-more-x', function(){
            loadMoreX('my-coupon', listOfIDs);
        });

        var viewAvailableCoupon = function () {
            listOfIDs.length = 0;
            loadMoreX('my-coupon', listOfIDs, helperObject);
            },
            viewCouponWallet = function () {
            };

        $('.coupon-button').click(function () {
            $(".catalogue-wrapper").empty();
            $(".coupon-button").removeClass('active');
            $(this).addClass('active');

            switch ($(this).data('type')) {
                case 'available-coupon':
                    viewAvailableCoupon();
                    break;
                case 'coupon-wallet':
                    viewCouponWallet();
                    break;
            }
        });
    });
</script>
@stop