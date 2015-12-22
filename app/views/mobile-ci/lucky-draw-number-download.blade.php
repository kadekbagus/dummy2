@extends('mobile-ci.layout')

@section('ext_style')
    <style type="text/css">
        #ldtitle{
            cursor: pointer;
        }
    </style>
@stop

@section('content')
<div class="row">
    <div class="col-xs-12 text-center">
        <h4 id="ldtitle">{{{ $luckydraw->lucky_draw_name }}}</h4>
    </div>
</div>

@if ($total_number > 160)
    <div class="row counter">
        <div class="col-xs-12 text-center">
            <div class="countdown">
                <span id="clock"></span>
           </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <small>{{ Lang::get('mobileci.lucky_draw.lucky_draw_total_info_1') }} <strong>{{ $total_number }}</strong> {{ Lang::get('mobileci.lucky_draw.lucky_draw_total_info_2') }} {{ $total_image }} {{ Lang::get('mobileci.lucky_draw.lucky_draw_total_info_3') }}
            {{ Lang::get('mobileci.lucky_draw.lucky_draw_total_info_4') }} {{ $number_per_image }} {{ Lang::get('mobileci.lucky_draw.lucky_draw_total_info_5') }}</small>
        </div>
    </div>
@else
    <div class="row">
        <div class="col-xs-12">
            {{ Lang::get('mobileci.lucky_draw.lucky_draw_total_info_6') }}
        </div>
    </div>
@endif

<div class="row lucky-number-wrapper">
    <div class="col-xs-12 text-center">
        @for ($i=1; $i<=$total_image; $i++)
        <div class="col-xs-6 col-sm-6 col-lg-6 vertically-spaced">
            <a href="{{ URL::route('ci-luckydrawnumber-download') }}&mode=download&page={{ $i }}&id={{ $lucky_draw_id }}" class="btn btn-info" id="save">{{ Lang::get('mobileci.lucky_draw.download_image') }} #{{ $i }}</a>
        </div>
        @endfor
    </div>
</div>

@if ($total_number <= 160)
<script>window.location.href = '{{ URL::route("ci-luckydrawnumber-download") }}&mode=download&page=1&id={{ $lucky_draw_id }}'</script>
@endif

@stop
