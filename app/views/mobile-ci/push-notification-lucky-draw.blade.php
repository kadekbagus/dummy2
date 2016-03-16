<!-- Modal -->
<div class="modal fade" id="orbit-push-modal-{{ $inbox->inbox_id }}" tabindex="-1" role="dialog" aria-labelledby="orbit-push-modal-title-{{ $inbox->inbox_id }}" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">|#|close|#|</span></button>
                <h4 class="modal-title" id="orbit-push-modal-title">|#|lucky_draw_subject|#|</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced">
                        <h4 style="color:#d9534f">|#|hello|#| {{{ $fullName }}},</h4>
                        <p>|#|ld_congratulations_you_get|#| {{ $numberOfLuckyDraw }} |#|no_lucky_draw|#| <strong>{{{ $luckyDrawCampaign }}}</strong>.
                        |#|lucky_draw_info_1|#| {{{ $dateIssued }}}.
                        </p>

                        <ol>
                        @foreach ($numbers as $number)
                            <li>{{ $number->lucky_draw_number_code }}</li>
                        @endforeach
                        </ol>

                        @if ($numberOfLuckyDraw > $maxShown)
                        <p>
                        |#|lucky_draw_info_2|#| {{ $maxShown }} |#|lucky_draw_info_3|#|.
                        </p>
                        @endif

                        <p>
                        |#|lucky_draw_info_4|#| <strong>{{ $totalLuckyDrawNumber }}</strong> |#|lucky_draw_info_5|#|
                        |#|lucky_draw|#|
                        </p>

                        <p style="margin-top:1em">
                            |#|goodluck|#|!</br>
                            <strong>{{{ $mallName }}}</strong>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">|#|close|#|</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
