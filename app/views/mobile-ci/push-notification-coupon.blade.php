<!-- Modal -->
<div class="modal fade" id="orbit-push-modal-{{ $inbox->inbox_id }}" tabindex="-1" role="dialog" aria-labelledby="orbit-push-modal-title-{{ $inbox->inbox_id }}" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">|#|close|#|</span></button>
                <h4 class="modal-title" id="orbit-push-modal-title">|#|coupon_subject|#|</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12">
                        <h4 style="color:#d9534f">|#|hello|#| {{{ $fullName }}},</h4>
                        <p>|#|congratulations_you_get|#| {{ $numberOfCoupon }} @if ($numberOfCoupon > 1) |#|here_are_your_coupons|#| @else |#|here_is_your_coupon|#| @endif:
                        </p>
                        <div class="row">
                            <ol style="padding-left:16px">
                            @foreach ($coupons as $couponName=>$couponNumbers)
                                <li><strong>{{{ $couponName }}}</strong></li>
                            @endforeach
                            </ol>
                        </div>
                        <p>
                            <strong>|#|check_coupon|#|</strong>
                        </p>
                        <p>
                            |#|happy_shopping|#|</br></br>
                            <strong>{{{ $mallName }}}</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
