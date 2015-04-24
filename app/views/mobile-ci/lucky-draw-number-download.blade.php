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
            <small>You have total <strong>{{ $total_number }}</strong> lucky draw numbers. You have total {{ $total_image }} image to download,
            each image contain maximum {{ $number_per_image }} lucky draw number. It sorted by highest to lowest number.</small>
        </div>
    </div>
@else
    <div class="row">
        <div class="col-xs-12">
            Your download should be started automatically. If it doesn't try to click button below.
        </div>
    </div>
@endif

@for ($i=1; $i<=$total_image; $i++)
<div class="row save-btn text-center">
    <div class="col-xs-12">
        <a href="{{ URL::route('ci-luckydrawnumber-download') }}?mode=download&page={{ $i }}" class="btn btn-info" id="save">Download Image #{{ $i }}</a>
    </div>
</div>
@endfor

@if ($total_number <= 160)
<script>window.location.href = '{{ URL::route("ci-luckydrawnumber-download") }}?mode=download&amp;page=1'</script>
@endif

@stop
