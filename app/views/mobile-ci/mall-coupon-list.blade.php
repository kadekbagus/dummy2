@extends('mobile-ci.layout')

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
            @if($data->status === 1)
                <div class="catalogue-wrapper">
                <!-- scope data -->
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
                'skip': 0,
                'coupon_type': 'available'
            };
        loadMoreX('my-coupon', listOfIDs, helperObject);

        $('body').on('click', '#load-more-x', function(){
            loadMoreX('my-coupon', listOfIDs);
        });

        $('.coupon-button').click(function () {
            $(".catalogue-wrapper").empty();
            $(".coupon-button").removeClass('active');
            $(this).addClass('active');

            listOfIDs.length = 0;
            helperObject.coupon_type = $(this).data('type');
            loadMoreX('my-coupon', listOfIDs, helperObject);
        });

        $('body').on('click', '.coupon-wallet', function(){
            var element = $(this),
                id = element.data('ids');

            if (element.attr('data-isaddedtowallet') === 'true') {
                return;
            }

            $.ajax({
                url: apiPath + 'my-coupon' + '/load-more',
                method: 'GET',
                data: {
                    take: 25,
                    skip: 0
                }
            }).done(function (data) {
                console.log(element);
                element.children('.wallet-text').html('{{ Lang::get("mobileci.coupon.added_wallet") }}');
                element.attr('data-isaddedtowallet', true);
            });
        });
    });
</script>
@stop