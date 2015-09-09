<!-- Modal -->
<div class="modal fade" id="orbit-push-modal-{{ $inbox->inbox_id }}" tabindex="-1" role="dialog" aria-labelledby="orbit-push-modal-title-{{ $inbox->inbox_id }}" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.lucky_draw.close') }}</span></button>
                <h4 class="modal-title" id="orbit-push-modal-title">{{{ $subject }}}</h4>
            </div>
            <div class="modal-body">
                <div class="row ">
                    <div class="col-xs-12 vertically-spaced">
                        <h4 style="color:#d9534f">{{ Lang::get('mobileci.lucky_draw.hello') }} {{ $fullName }},</h4>
                        <p>{{ Lang::get('mobileci.lucky_draw.congratulation') }} {{ $numberOfLuckyDraw }} {{ Lang::get('mobileci.lucky_draw.no_lucky_draw') }} <strong>{{ $luckyDrawCampaign }}</strong>.
                        {{ Lang::get('mobileci.lucky_draw.lucky_draw_info_1') }} {{ $dateIssued }}.
                        </p>

                        <ol>
                        @foreach ($numbers as $number)
                            <li>{{ $number->lucky_draw_number_code }}</li>
                        @endforeach
                        </ol>

                        @if ($numberOfLuckyDraw > $maxShown)
                        <p>
                        {{ Lang::get('mobileci.lucky_draw.lucky_draw_info_2') }} {{ $maxShown }} {{ Lang::get('mobileci.lucky_draw.lucky_draw_info_3') }}.
                        </p>
                        @endif

                        <p>
                        {{ Lang::get('mobileci.lucky_draw.lucky_draw_info_4') }} <strong>{{ $totalLuckyDrawNumber }}</strong> {{ Lang::get('mobileci.lucky_draw.lucky_draw_info_5') }}
                        {{ Lang::get('mobileci.lucky_draw.lucky_draw') }}.
                        </p>

                        <p style="margin-top:1em">
                            {{ Lang::get('mobileci.lucky_draw.goodluck') }}!</br>
                            <strong>{{ $mallName }}</strong>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.lucky_draw.close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
