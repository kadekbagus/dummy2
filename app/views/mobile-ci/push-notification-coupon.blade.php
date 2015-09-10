<!-- Modal -->
<div class="modal fade" id="orbit-push-modal-{{ $inbox->inbox_id }}" tabindex="-1" role="dialog" aria-labelledby="orbit-push-modal-title-{{ $inbox->inbox_id }}" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="orbit-push-modal-title">{{{ $subject }}}</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced">
                        <h4 style="color:#d9534f">Halo {{ $fullName }},</h4>
                        <p>{{ Lang::get('mobileci.coupon.congratulations_you_get') }} {{ $numberOfCoupon }} {{ Lang::get('mobileci.coupon.coupon_here_is_coupon_you') }}:
                        </p>

                        <ol>
                        @foreach ($coupons as $couponName=>$couponNumbers)
                            <li><strong>{{ $couponName }}</strong></li>
                        @endforeach
                        </ol>

                        <p style="margin-top:1em">
                            {{ Lang::get('mobileci.coupon.happy_shopping') }}</br>
                            <strong>{{ $mallName }}</strong>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.coupon.close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
