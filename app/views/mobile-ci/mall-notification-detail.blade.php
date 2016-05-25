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

        var dateUtc = '{{ $inbox->created_at }}';
        var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

        var printDate = function (date) {
            if (date instanceof Date) {
                var day = date.getDate().toString();
                var mth = monthNames[date.getMonth()];
                var yr = date.getFullYear();
                var hr = date.getHours().toString();
                var min = date.getMinutes().toString();
                var sec = date.getSeconds().toString();

                return (day[1]?day:"0"+day[0]) + ' ' + mth + ' ' + yr + ' ' + (hr[1]?hr:"0"+hr[0]) + ':' + (min[1]?min:"0"+min[0]) + ':' + (sec[1]?sec:"0"+sec[0]);
            }
            return null;
        }

        var parseDate = function (strDate) {
            var parts = strDate.match(/([0-9]+)/g);

            // This is still in UTC
            var date = new Date(parts[0], parts[1], parts[2], parts[3], parts[4], parts[5]);

            // Convert it to local browser
            date.setMinutes(date.getMinutes() - (new Date()).getTimezoneOffset());

            return date;
        };

        $(function() {
            var date = parseDate(dateUtc);
            $('#created_at').text(printDate(date));
        });
    </script>
@stop