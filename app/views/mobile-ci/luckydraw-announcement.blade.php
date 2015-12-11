@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<div class="row">
    @if($ongoing)
        @if(! empty($luckydraw->image))
        <a href="{{ asset($luckydraw->image) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer text-left"><img src="{{ asset($luckydraw->image) }}" class="img-responsive" style="width:100%;"></a>
        @else
        <img src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" class="img-responsive" style="width:100%;">
        @endif
    @else
        @if(! empty($luckydraw->announcements))
            @if(! empty($luckydraw->announcements[0]->image))
            <a href="{{ asset($luckydraw->announcements[0]->image) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer text-left"><img src="{{ asset($luckydraw->announcements[0]->image) }}" class="img-responsive" style="width:100%;"></a>
            @else
            <img src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" class="img-responsive" style="width:100%;">
            @endif
        @endif
    @endif
</div>
<div class="row vertically-spaced">
    <div class="col-xs-12 text-left">
        <h4>
            @if($ongoing)
            {{ 'Lucky Draw Prizes' }}
            @else
            {{ $luckydraw->announcements[0]->title }}
            @endif
        </h4>
    </div>
</div>
@if($ongoing)
<div class="row">
    <div class="col-xs-12 text-left">
        <ul>
            @foreach($luckydraw->prizes as $prize)
            <li>{{ $prize->winner_number . ' ' . $prize->prize_name}}</li>
            @endforeach
        </ul>
    </div>
</div>
@else
<div class="row">
    <div class="col-xs-12 text-left">
        <h4>{{ Lang::get('mobileci.lucky_draw.information') }}</h4>
        <p>
            {{ $luckydraw->description }}
        </p>
        <h4 style="margin-top:20px;">{{ Lang::get('mobileci.lucky_draw.prizes_and_winners') }}</h4>
        <table class="table">
            @foreach($luckydraw->prizes as $prize)
            <tr>
                <th colspan="2">{{ $prize->prize_name }}</th>
            </tr>
            @if(! empty($prize->winners))
                @foreach($prize->winners as $winner)
                <tr>
                    <td>{{ $winner->number->user->getFullName() }}</td>
                    <td>{{ $winner->lucky_draw_winner_code }}</td>
                </tr>
                @endforeach
            @endif
            @endforeach
        </table>
    </div>
</div>
@endif

<div class="row text-center vertically-spaced">
    <div class="col-xs-12">
        <a href="{{ url('customer/luckydraws') }}" class="btn btn-info btn-block">{{ Lang::get('mobileci.modals.ok') }}</a>
    </div>
</div>

@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="numberModal" tabindex="-1" role="dialog" aria-labelledby="numberModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-body">

            </div>
        </div>
    </div>
</div>

<!-- Modal -->
@if(!empty($luckydraw))
<div class="modal fade" id="lddetail" tabindex="-1" role="dialog" aria-labelledby="lddetailLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="lddetailLabel">{{ Lang::get('mobileci.lucky_draw.lucky_draw_info') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12">
                        <b>{{ $luckydraw->lucky_draw_name }}</b>
                        <br>
                        <img src="{{ asset($luckydraw->image) }}" class="img-responsive">
                        <p>{{ nl2br($luckydraw->description) }}</p>
                        <p>{{ Lang::get('mobileci.coupon_detail.validity_label') }} : {{ date('d M Y H:m', strtotime($luckydraw->end_date)) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@stop

@section('ext_script_bot')
    {{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
    {{ HTML::script('mobile-ci/scripts/jquery.plugin.min.js') }}
    {{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
@stop
