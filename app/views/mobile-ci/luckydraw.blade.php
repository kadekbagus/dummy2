@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('fb_scripts')
@if(! empty($facebookInfo))
@if(! empty($facebookInfo['version']) && ! empty($facebookInfo['app_id']))
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version={{$facebookInfo['version']}}&appId={{$facebookInfo['app_id']}}";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
@endif
@endif
@stop


@section('content')
<div class="row">
    <div class="col-xs-12 product-detail">
        @if(! empty($luckydraw->image))
        <a href="{{{ asset($luckydraw->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img src="{{ asset($luckydraw->image) }}"></a>
        @else
        <img src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" class="img-responsive" style="width:100%;">
        @endif
    </div>
</div>
<div class="row product-info padded">
    <div class="col-xs-12 text-left">
        <p>
            {{ nl2br(e($luckydraw->description)) }}
        </p>
    </div>
    <div class="col-xs-12 text-left">
        <h4><strong>{{ Lang::get('mobileci.lucky_draw.period') }}</strong></h4>
        <p>
            {{{ date('d M Y', strtotime($luckydraw->start_date)) }}} - {{{ date('d M Y', strtotime($luckydraw->end_date)) }}}
        </p>
    </div>
    <div class="col-xs-12 text-left">
        <h4><strong>{{ Lang::get('mobileci.lucky_draw.draw_date') }}</strong></h4>
        <p>
            {{{ date('d M Y', strtotime($luckydraw->draw_date)) }}}
        </p>
    </div>
    @if ($urlblock->isLoggedIn())
        @if(! empty($luckydraw->facebook_share_url))
        <div class="col-xs-12">
            <div class="fb-share-button" data-href="{{{$luckydraw->facebook_share_url}}}" data-layout="button"></div>
        </div>
        @endif
    @endif
</div>
@if(!empty($luckydraw))
    @if(strtotime($servertime) > strtotime($luckydraw->draw_date))
        @if(! empty($luckydraw->prizes))
            @if(isset($luckydraw->announcements[0]))
                @if($luckydraw->announcements[0]->status == 'active')
                <div class="row text-center vertically-spaced">
                    <div class="col-xs-12 padded">
                        <a data-href="{{ route('ci-luckydraw-announcement', ['id' => $luckydraw->lucky_draw_id]) }}" href="{{ $urlblock->blockedRoute('ci-luckydraw-announcement', ['id' => $luckydraw->lucky_draw_id]) }}" class="btn btn-info btn-block">{{ Lang::get('mobileci.lucky_draw.see_prizes_and_winner') }}</a>
                    </div>
                </div>
                @else
                <div class="row text-center vertically-spaced">
                    <div class="col-xs-12 padded">
                        <button class="btn btn-disabled-ld btn-block">{{ Lang::get('mobileci.lucky_draw.see_prizes_and_winner') }}</button>
                    </div>
                </div>
                @endif
            @else
            <div class="row text-center vertically-spaced">
                <div class="col-xs-12 padded">
                    <button class="btn btn-disabled-ld btn-block">{{ Lang::get('mobileci.lucky_draw.see_prizes_and_winner') }}</button>
                </div>
            </div>
            @endif
        @else
        <div class="row text-center vertically-spaced">
            <div class="col-xs-12 padded">
                <button class="btn btn-disabled-ld btn-block">{{ Lang::get('mobileci.lucky_draw.see_prizes_and_winner') }}</button>
            </div>
        </div>
        @endif
    @else
        <div class="row text-center vertically-spaced">
            <div class="col-xs-12 padded">
                <button class="btn btn-disabled-ld btn-block">{{ Lang::get('mobileci.lucky_draw.see_prizes_and_winner') }}</button>
            </div>
        </div>
    @endif
@endif
<div class="row counter">
    <div class="col-xs-12 text-center">
        <div class="countdown @if($luckydraw->status == 'active') @if(strtotime($servertime) < strtotime($luckydraw->end_date)) active-countdown @else inactive-countdown @endif @elseif($luckydraw->status == 'active') inactive-countdown @else inactive-countdown @endif">
            <span id="clock" @if(empty($luckydraw)) class="no-luck" @endif></span>
        </div>
    </div>
</div>
<div class="row text-center lucky-number-wrapper">
    @if(!empty($luckydraw))
        <div class="row vertically-spaced">
            @if (($total_number === 0) && ($luckydraw->end_date > \Carbon\Carbon::now($retailer->timezone->timezone_name)))
            <h4>{{ Lang::get('mobileci.lucky_draw.my_lucky_draw_number') }}</h4>
            @endif
        </div>

        <div class="row">
            <div class="col-xs-12">    
                @if (($total_number === 0) && ($luckydraw->end_date > \Carbon\Carbon::now($retailer->timezone->timezone_name)))
                    <div class="text-center">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" style="margin:0 auto" />
                    </div>
                    <h4>{{ Lang::get('mobileci.lucky_draw.no_lucky_draw_number_1') }}</h4>
                    <small>
                        {{ Lang::get('mobileci.lucky_draw.no_lucky_draw_number_2') }}
                    </small>
                @endif
                <a name="ln-nav" id="ln-nav"></a>
            </div>
        </div>

       <!--  <div class="row">
            <p>&nbsp;</p>
        </div> -->

        <div class="row">
            <div class="col-xs-12 lucky-number-row">
                @if(count($numbers) == 1)
                    <div class="col-xs-12 lucky-number-col">
                        <div class="lucky-number-container" data-number="{{$numbers[0]->lucky_draw_number_id}}">{{ $numbers[0]->lucky_draw_number_code }}</div>
                    </div>
                @else
                    @foreach($numbers as $i=>$number)
                    <div class="col-xs-6 col-sm-6 col-lg-6 lucky-number-col">
                        <div class="lucky-number-container" data-number="{{$number->lucky_draw_number_id}}">{{ $number->lucky_draw_number_code }}</div>
                    </div>
                    @endforeach

                    @if ($total_number % 2 !== 0)
                    <!-- <div class="col-xs-12 col-sm-6 col-lg-6">
                        <div class="lucky-number-container" data-number=""></div>
                    </div> -->
                    @endif
                @endif
            </div>
        </div>
        @if ($total_pages > 1)
        <div class="row">
            <div class="col-xs-12 text-center">
                <div class="col-xs-12">
                    <ul class="ld-pagination">
                        @if($current_page != '1')
                        <li><a data-href="{{ route('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'page' => 1]) }}" href="{{$urlblock->blockedRoute('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'page' => 1])}}#ln-nav" class="{{ ($prev_url === '#1' ? 'disabled' : ''); }}"><i class="fa fa-angle-double-left"></i></a></li>
                        @else
                        <li><a class="disabled" style="color:#dedede;"><i class="fa fa-angle-double-left"></i></a></li>
                        @endif
                        @if(! in_array(1, $paginationPage))
                        <li class="ld-pagination-ellipsis">...</li>
                        @endif
                        @foreach($paginationPage as $p)
                        <li @if($current_page == $p) class="ld-pagination-active" @endif><a data-href="{{ route('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'page' => $p]) }}" href="{{$urlblock->blockedRoute('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'page' => $p])}}#ln-nav" class="{{ ($prev_url === '#1' ? 'disabled' : ''); }}">{{ $p }}</a></li>
                        @endforeach
                        @if(! in_array($total_pages, $paginationPage))
                        <li class="ld-pagination-ellipsis">...</li>
                        @endif
                        @if($current_page != $total_pages)
                        <li><a data-href="{{ route('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'page' => $total_pages]) }}" href="{{$urlblock->blockedRoute('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'page' => $total_pages])}}#ln-nav" class="{{ ($prev_url === '#1' ? 'disabled' : ''); }}"><i class="fa fa-angle-double-right"></i></a></li>
                        @else
                        <li><a class="disabled" style="color:#dedede;"><i class="fa fa-angle-double-right"></i></a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
        @endif
        @if ($total_number > 0)
        <div class="row text-center save-btn vertically-spaced">
            <div class="col-xs-1"></div>
            <div class="col-xs-10">
                <a data-href="{{ route('ci-luckydrawnumber-download', ['id' => $luckydraw->lucky_draw_id]) }}" href="{{ $urlblock->blockedRoute('ci-luckydrawnumber-download', ['id' => $luckydraw->lucky_draw_id]) }}" class="btn btn-info btn-block">{{ Lang::get('mobileci.lucky_draw.save_numbers') }}</a>
            </div>
            <div class="col-xs-1"></div>
        </div>
        @endif
    @endif
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
@stop

@section('ext_script_bot')
    {{ HTML::script(Config::get('orbit.cdn.jquery_plugin.2_0_2', 'mobile-ci/scripts/jquery.plugin.min.js')) }}
    {{-- Script fallback --}}
    <script>
        if (typeof $.JQPlugin === 'undefined') {
            document.write('<script src="{{asset('mobile-ci/scripts/jquery.plugin.min.js')}}">\x3C/script>');
        }
    </script>
    {{-- End of Script fallback --}}

    {{ HTML::script(Config::get('orbit.cdn.featherlight.1_0_3', 'mobile-ci/scripts/featherlight.min.js')) }}
    {{-- Script fallback --}}
    <script>
        if (typeof $().featherlight === 'undefined') {
            document.write('<script src="{{asset('mobile-ci/scripts/featherlight.min.js')}}">\x3C/script>');
        }
    </script>    
    {{-- End of Script fallback --}}
    
    @if (!empty($luckydraw))
       {{-- we only use moment.js to display countdown timer --}}
       
       {{ HTML::script(Config::get('orbit.cdn.moment.2_13_0', 'mobile-ci/scripts/moment.min.js')) }}   
       {{-- Script fallback --}}
       <script>
          if ((typeof moment === 'undefined')) {
              document.write('<script src="{{asset('mobile-ci/scripts/moment.min.js')}}">\x3C/script>');
          }
       </script>
       {{-- End of Script fallback --}}
   
       {{ HTML::script(Config::get('orbit.cdn.moment_timezone_data.0_5_4', 'mobile-ci/scripts/moment-timezone-with-data.min.js')) }}
       {{-- Script fallback --}}
       <script>
          if ((typeof moment.tz === 'undefined')) {
              document.write('<script src="{{asset('mobile-ci/scripts/moment-timezone-with-data.min.js')}}">\x3C/script>');
          }
       </script>
       {{-- End of Script fallback --}}
    @endif
    
    <script type="text/javascript">
        $(document).ready(function(){
            $('#ldtitle').click(function(){
                $('#lddetail').modal();
            });

        @if(!empty($luckydraw))
           {{-- we only display countdown timer when there is valid lucky draw --}}
            /**
             * Custom countdown timer
             *
             * we avoid jquery.countdown plugin because countdown is changed when user changed their device date/time
             * This is because jquery.countdown use Javascript Date which use device current date/time
             * See issue OM-2009
             * Require: moment.js
             * @author zamroni@dominopos.com
             */
            var countdownTimer = function (timerData, clockElem, timeChangeCallback) {
                    
                if (timerData.startDate) {
                    timerData.startDate.add(1, 'second');
                }
                if (timerData.endDate) {
                    var diff = timerData.endDate.diff(timerData.startDate);

                    if (diff > 0) {
                        timerData.days = moment.duration(diff).days();
                        timerData.hours = moment.duration(diff).hours();
                        timerData.minutes = moment.duration(diff).minutes();
                        timerData.seconds = moment.duration(diff).seconds();
                    } else {
                        timerData.days = 0;
                        timerData.hours = 0;
                        timerData.minutes = 0;
                        timerData.seconds = 0;
                    }
                }

                timeChangeCallback(timerData, clockElem);
             };

            var timerInitData = {
                    startDate : moment.tz('{{ $servertime }}', '{{ $retailer->timezone->timezone_name }}'),
                    endDate : moment.tz('{{ $luckydraw->end_date }}', '{{ $retailer->timezone->timezone_name }}'),
                    days : 0,
                    hours: 0,
                    minutes : 0,
                    seconds : 0
            };
            
            var timeChanged = function(timerData, clockElem) {
                var days = timerData.days < 10 ? '0' + timerData.days : timerData.days;
                var hours = timerData.hours < 10 ? '0' + timerData.hours : timerData.hours;
                var minutes = timerData.minutes < 10 ? '0' + timerData.minutes : timerData.minutes;
                var seconds = timerData.seconds < 10 ? '0' + timerData.seconds : timerData.seconds;
                var template = '<span class="countdown-row countdown-show4"><span class="countdown-section"><span class="countdown-amount">'+days+'</span><span class="countdown-period">Days</span></span><span class="countdown-section"><span class="countdown-amount">' + hours + '</span><span class="countdown-period">Hours</span></span><span class="countdown-section"><span class="countdown-amount">' + minutes + '</span><span class="countdown-period">Minutes</span></span><span class="countdown-section"><span class="countdown-amount">' + seconds + '</span><span class="countdown-period">Seconds</span></span></span>';
                clockElem.html(template);
            };
            
            countdownTimer(timerInitdata, $('#clock'), timeChanged);
            setInterval(countdownTimer, 1000, timerInitData, $('#clock'), timeChanged);
          @else
              {{-- if lucky draw is empty we just display static element --}}
              var template = '<span class="countdown-row countdown-show4"><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">Days</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">Hours</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">Minutes</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">Seconds</span></span></span>';
              $('#clock').html(template);
          @endif
            

            $('#datenow').text(new Date().toDateString() + ' ' + new Date().getHours() + ':' + new Date().getMinutes());

            $(window).scroll(function(){
                s = $(window).scrollTop();
                $('.product-detail img').css('-webkit-transform', 'translateY('+(s/3)+'px)');
            });
        });
    </script>
@stop
