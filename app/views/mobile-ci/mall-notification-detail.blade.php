@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    <div class="row vertically-spaced" style="padding-right: 10px;">
        <p id="created_at" class="text-right small" style="font-style: italic;"></p>
    </div>

    {{ $inbox->content }}

    @if($inbox->inbox_type == 'lucky_draw_issuance')
    <div class="row vertically-spaced">
        <div class="col-xs-12 padded">
        <a href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-luckydraw-list', [], $session) }}" class="btn btn-block btn-info">{{ Lang::get('mobileci.notification.view_lucky_draw_btn') }}</a>
        </div>
    </div>
    @elseif($inbox->inbox_type == 'coupon_issuance')
    <div class="row vertically-spaced">
        <div class="col-xs-12 padded">
        <a href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-coupon-list', [], $session) }}" class="btn btn-block btn-info">{{ Lang::get('mobileci.notification.view_coupons_btn') }}</a>
        </div>
    </div>
    @endif
@stop

@section('ext_script_bot')
    {{ HTML::script('mobile-ci/scripts/moment.min.js') }}

    <script type="text/javascript">
        notInMessagesPage = false;
        var _createdAt = '{{ $inbox->created_at }}';

        var printDate = function (strDate) {
            // Parse to moment utc date.
            var utc = moment.utc(strDate, 'YYYY-MM-DD HH:mm:ss');

            // Parse to local date.
            var localMoment = moment(utc.toDate());

            return localMoment.format('DD MMM YYYY HH:mm:ss');
        };

        $(function() {
            $('#created_at').text(printDate(_createdAt));
        });
    </script>
@stop