@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    {{ $inbox->content }}

    @if($inbox->inbox_type == 'lucky_draw_issuance')
    <div class="row vertically-spaced">
        <div class="col-xs-12 padded">
        <a href="{{ $urlblock->blockedRoute('ci-luckydraw-list') }}" class="btn btn-block btn-info">{{ Lang::get('mobileci.notification.view_lucky_draw_btn') }}</a>
        </div>
    </div>
    @elseif($inbox->inbox_type == 'coupon_issuance')
    <div class="row vertically-spaced">
        <div class="col-xs-12 padded">
        <a href="{{ $urlblock->blockedRoute('ci-coupon-list') }}" class="btn btn-block btn-info">{{ Lang::get('mobileci.notification.view_coupons_btn') }}</a>
        </div>
    </div>
    @endif
@stop

@section('ext_script_bot')
    <script type="text/javascript">
        notInMessagesPage = false;
    </script>
@stop