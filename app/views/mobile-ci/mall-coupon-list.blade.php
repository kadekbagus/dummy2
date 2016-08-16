@extends('mobile-ci.layout')

@section('content')
    <div class="container">
        <div class="mobile-ci list-item-container">
            <div class="row">
                <div class="catalogue-wrapper">
                <!-- scope data -->
                </div>
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
                url: apiPath + 'coupon/addtowallet',
                method: 'POST',
                data: {
                    coupon_id: id
                }
            }).done(function (data) {
                if(data.status === 'success') {
                    element.children('.wallet-text').html('{{ Lang::get("mobileci.coupon.added_wallet") }}');
                    element.attr('data-isaddedtowallet', true);
                }
            });
        });
    });
</script>
@stop