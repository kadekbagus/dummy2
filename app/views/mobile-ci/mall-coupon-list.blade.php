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
            getCouponType = ('{{ \Input::get("type") }}' !== '' ? '{{ \Input::get("type") }}' : 'available'),
            idForAddWallet = ('{{ \Input::get("idForAddWallet") }}' !== '' ? '{{ \Input::get("idForAddWallet") }}' : ''),
            helperObject = {
                'skip': 0,
                'coupon_type': getCouponType,
                'isProgress': true
            },
            messageNotLogin = function () {
                var elementMessage = '\
                    <div class="col-xs-12 notification-message">\
                        <h4>{{ Lang::get('mobileci.coupon.login_to_show_coupon_wallet') }}</h4>\
                        <a data-href="{{ $signin_url }}" href="#" type="button" class="sign-button btn">SIGN IN</a>\
                    </div>';
                $(".catalogue-wrapper").html(elementMessage);
            },
            addToWallet = function (ids, callback) {
                var element = $("span[data-ids='" + ids +"']");
                var url = '{{ route('coupon-add-to-wallet') }}';
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        coupon_id: ids
                    }
                }).done(function (data) {
                    if(data.status === 'success') {
                        element.children('.state-icon').removeClass('fa-plus');
                        element.children('.state-icon').addClass('fa-check');
                        element.children('.fa-circle').addClass('added');
                        element.parent().siblings().html('{{ Lang::get("mobileci.coupon.added_wallet") }}');
                        element.attr('data-isaddedtowallet', true);

                        if (callback) {
                            callback();
                        }
                    }
                });
            };

        $('body').on('click', '#load-more-x', function(){
            loadMoreX('my-coupon', listOfIDs, helperObject);
        });

        $('.coupon-button').click(function () {
            if (helperObject.isProgress) {
                return;
            }

            $(".catalogue-wrapper").html('');
            listOfIDs.length = 0;
            $(".coupon-button").removeClass('active');
            $(this).addClass('active');

            helperObject.coupon_type = $(this).data('type');

            // validate user login
            if ('wallet' === helperObject.coupon_type && !Boolean({{$is_logged_in}})) {
                messageNotLogin();
                loadMoreX('my-coupon', listOfIDs, helperObject);
                helperObject.isProgress = false;
                return;
            }

            helperObject.isProgress = true;
            loadMoreX('my-coupon', listOfIDs, helperObject);
        });

        $('body').on('click', '.coupon-wallet a', function(e){
            e.preventDefault();
        });

        $('body').on('click', '.coupon-wallet .clickable', function(){
            var element = $(this),
                ids = element.data('ids');

            if (element.attr('data-isaddedtowallet') === 'true' || element.parent().attr('href') === '#') {
                return;
            }

            addToWallet(ids);
        });

        // validate user login
        if ('wallet' === helperObject.coupon_type && !Boolean({{$is_logged_in}})) {
            messageNotLogin();
            helperObject.isProgress = false;
            return;
        }

        loadMoreX('my-coupon', listOfIDs, helperObject, function () {
            if (idForAddWallet !== '') {
                addToWallet(idForAddWallet, function () {
                    history.pushState({}, '', 'mallcoupons' );
                });
            }

        });
    });
</script>
@stop