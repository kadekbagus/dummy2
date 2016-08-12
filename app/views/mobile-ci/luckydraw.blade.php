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


<?php
$showPrizesAndWinners = false;
if(!empty($luckydraw)) {
  if(strtotime($servertime) > strtotime($luckydraw->draw_date)) {
    if(!empty($luckydraw->prizes)) {
      if(isset($luckydraw->announcements[0])) {
        if($luckydraw->announcements[0]->status == 'active') {
          $showPrizesAndWinners = true;
        }
      }
    }
  }
}
?>

@section('content')
<div class="row relative-wrapper">
    <div class="actions-container" style="z-index: 102;">
        <div class="circle-plus action-btn">
            <div class="circle">
                <div class="horizontal"></div>
                <div class="vertical"></div>
            </div>
        </div>
        <div class="actions-panel" style="display: none;">
            <ul class="list-unstyled">
                <li>
                    @if($showPrizesAndWinners)
                    <a data-href="{{ route('ci-luckydraw-announcement', ['id' => $luckydraw->lucky_draw_id]) }}" href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-luckydraw-announcement', ['id' => $luckydraw->lucky_draw_id], $session) }}">
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-trophy fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{ Lang::get('mobileci.lucky_draw.see_prizes_and_winner') }}</span>
                    </a>
                    @else
                        <!-- Not showing prizes and winners -->
                    <a class="disabled">
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-trophy fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{ Lang::get('mobileci.lucky_draw.see_prizes_and_winner') }}</span>
                    </a>
                    @endif
                </li>
                @if($total_number > 0)
                <li>
                    <a data-href="{{ route('ci-luckydrawnumber-download', ['id' => $luckydraw->lucky_draw_id]) }}" href="{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-luckydrawnumber-download', ['id' => $luckydraw->lucky_draw_id], $session) }}">
                        <span class="fa fa-stack icon">
                            <i class="fa fa-circle fa-stack-2x"></i>
                            <i class="fa fa-download fa-inverse fa-stack-1x"></i>
                        </span>
                        <span class="text">{{ Lang::get('mobileci.lucky_draw.save_numbers') }}</span>
                    </a>
                </li>
                @endif
                @if ($is_logged_in)
                    @if(! empty($luckydraw->facebook_share_url))
                    <li>
                        <div class="fb-share-button" data-href="{{$luckydraw->facebook_share_url}}" data-layout="button"></div>
                    </li>
                    @endif
                @endif
            </ul>
        </div>
    </div>
    <div class="col-xs-12 product-detail img-wrapper" style="z-index: 100;">
      <div class="vertical-align-middle-outer">
        <div class="vertical-align-middle-inner">
          @if(! empty($luckydraw->image))
          <a href="{{{ asset($luckydraw->image) }}}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer"><img src="{{ asset($luckydraw->image) }}"></a>
          @else
          <img src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" class="img-responsive" style="width:100%;">
          @endif
        </div>
      </div>
    </div>
</div>
<div class="row product-info padded" style="z-index: 101;">
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
</div>
<div class="row counter">
    <div class="col-xs-12 text-center">
        <div class="countdown @if($luckydraw->status == 'active') @if(strtotime($servertime) < strtotime($luckydraw->end_date)) active-countdown @else inactive-countdown @endif @elseif($luckydraw->status == 'active') inactive-countdown @else inactive-countdown @endif">
            <span id="clock" @if(empty($luckydraw)) class="no-luck" @endif></span>
        </div>
    </div>
</div>
@if($luckydraw->object_type === 'auto')
<div class="row">
    @if(\Carbon\Carbon::now($retailer->timezone->timezone_name) < $luckydraw->end_date)
    <div class="col-xs-12 text-center">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_token" id="_token" value="{{$csrf_token}}">
            <div class="file-upload btn btn-info">
                <span>{{Lang::get('mobileci.lucky_draw.upload_receipt')}}</span>
                <input type="file" class="upload" name="photo" accept="image/*" id="lucky-draw-capture" capture="camera">
            </div>
        </form>
    </div>
    <div class="col-xs-12 text-center">
        <p id="upload-message"></p>
    </div>
    @else
    <div class="col-xs-12 text-center">
        <button type="button" class="btn btn-disabled-ld">{{Lang::get('mobileci.lucky_draw.upload_receipt')}}</button>
    </div>
    @endif
</div>
@endif
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
                        <li><a data-href="{{ route('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'name' => Str::slug($luckydraw->lucky_draw_name), 'page' => 1]) }}" href="{{\Orbit\Helper\Net\UrlChecker::blockedRoute('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'name' => Str::slug($luckydraw->lucky_draw_name), 'page' => 1], $session)}}#ln-nav" class="{{ ($prev_url === '#1' ? 'disabled' : ''); }}"><i class="fa fa-angle-double-left"></i></a></li>
                        @else
                        <li><a class="disabled" style="color:#dedede;"><i class="fa fa-angle-double-left"></i></a></li>
                        @endif
                        @if(! in_array(1, $paginationPage))
                        <li class="ld-pagination-ellipsis">...</li>
                        @endif
                        @foreach($paginationPage as $p)
                        <li @if($current_page == $p) class="ld-pagination-active" @endif><a data-href="{{ route('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'name' => Str::slug($luckydraw->lucky_draw_name), 'page' => $p]) }}" href="{{\Orbit\Helper\Net\UrlChecker::blockedRoute('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'name' => Str::slug($luckydraw->lucky_draw_name), 'page' => $p], $session)}}#ln-nav" class="{{ ($prev_url === '#1' ? 'disabled' : ''); }}">{{ $p }}</a></li>
                        @endforeach
                        @if(! in_array($total_pages, $paginationPage))
                        <li class="ld-pagination-ellipsis">...</li>
                        @endif
                        @if($current_page != $total_pages)
                        <li><a data-href="{{ route('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'name' => Str::slug($luckydraw->lucky_draw_name), 'page' => $total_pages]) }}" href="{{\Orbit\Helper\Net\UrlChecker::blockedRoute('ci-luckydraw-detail', ['id' => $luckydraw->lucky_draw_id, 'name' => Str::slug($luckydraw->lucky_draw_name), 'page' => $total_pages], $session)}}#ln-nav" class="{{ ($prev_url === '#1' ? 'disabled' : ''); }}"><i class="fa fa-angle-double-right"></i></a></li>
                        @else
                        <li><a class="disabled" style="color:#dedede;"><i class="fa fa-angle-double-right"></i></a></li>
                        @endif
                    </ul>
                </div>
            </div>
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
            // Set fromSource in localStorage.
            localStorage.setItem('fromSource', 'luckydraw');

            // Actions button event handler
            $('.action-btn').on('click', function() {
                $('.actions-container').toggleClass('alive');
                $('.actions-panel').slideToggle();
            });

            setTimeout(function() {
                $('.actions-container').fadeIn();
            }, 500);

            $('#ldtitle').click(function(){
                $('#lddetail').modal();
            });

        @if($luckydraw->object_type === 'auto')
            function fileSelected() {
                var count = document.getElementById('lucky-draw-capture').files.length;
                if (count > 0) {
                    var file = document.getElementById('lucky-draw-capture').files[0];
                    if (file.size > 0) {
                        return true;
                    }
                }
                return true;
            }
            $('body').on('change', '#lucky-draw-capture', function(e) {
                if(fileSelected()){
                    document.getElementById("lucky-draw-capture").disabled = true;
                    $('#upload-message').text('');
                    $('#upload-message').fadeIn();
                    $.ajax({
                        method: 'post',
                        url: '{{route('ci-luckydraw-auto-issue')}}',
                        data: {
                            lucky_draw_id: '{{$luckydraw->lucky_draw_id}}',
                            _token: $('#_token').val()
                        }
                    }).done(function(data){
                        if (data.code === 0) {
                            $('#upload-message').css('color', 'green').text("{{Lang::get('mobileci.lucky_draw.upload_congrats')}}" + data.data.lucky_draw_number_code);
                        } else {
                            $('#upload-message').css('color', 'red').text(data.message);
                        }
                        $('#_token').val(data.data.token);
                    }).fail(function(data){
                        $('#_token').val(data.responseJSON.data.token);
                        $('#upload-message').css('color', 'red').text(data.responseJSON.message);
                    }).always(function(data){
                        document.getElementById("lucky-draw-capture").disabled = false;
                        setTimeout(function(){
                            $('#upload-message').fadeOut();
                        }, 2000);
                    });
                }
            });
        @endif

        @if(!empty($luckydraw))
           {{-- we only display countdown timer when there is valid lucky draw --}}

           {{--

             *  Accurate setTimeInterval replacement
             *  See related issue OM-2009 in Dominopos JIRA
             *  Modified version of https://gist.github.com/manast/1185904

             *  Issue with original code:
             *  if user change their device date/time setting for example due
             *  timezone change, nextTick calculation may cause nextTick to
             *  become very large or very small thus interval will break.
             *  (Note: JavaScript Date always use current device date time)
             *
          --}}{{--
             *  Modified version detect if nextTick is not between threshold value then
             *  we will call syncNeededCallback() and let application handle it.
             *

           --}}

           function interval(duration, intervalCallback, syncNeededCallback) {
               //5 seconds threshold
               const THRESHOLD_MS = 5000;
               this.baseline = undefined;
               this.ended = false;

               var isWithinThreshold = function (tick) {
                   var diff = tick - THRESHOLD_MS;
                   return !((diff < -THRESHOLD_MS) || (diff > THRESHOLD_MS));
               };

               this.run = function () {
                   if (this.baseline === undefined) {
                       this.baseline = new Date().getTime()
                   };

                   var end = new Date().getTime();
                   this.baseline += duration;
                   var deltaTime = end - this.baseline;

                   intervalCallback(this, deltaTime);

                   var nextTick = duration - deltaTime;

                   if (isWithinThreshold(nextTick)) {
                       if (nextTick < 0) {
                           nextTick = 0;
                       }
                   } else {
                       syncNeededCallback();
                       //reset baseline
                       this.baseline = new Date().getTime();
                       //trigger next setTimeOut immediately
                       nextTick = 0;
                   };

                   if (this.ended === false) {
                       (function(i){
                           i.timer = setTimeout(function(){
                               i.run();
                           }, nextTick);
                       }(this));
                   };
               }

               this.stop = function(){
                   clearTimeout(this.timer);
                   this.ended = true;
               }
            };

            var timerInitData = {
                   currentDateTime : moment.tz('{{ $servertime }}', '{{ $retailer->timezone->timezone_name }}'),
                   luckydrawEndDateTime : moment.tz('{{ $luckydraw->end_date }}', '{{ $retailer->timezone->timezone_name }}'),
                   days : 0,
                   hours: 0,
                   minutes : 0,
                   seconds : 0
            };

            var timerCallback = function(timerObj, deltaTime) {
                if (timerInitData.currentDateTime) {
                    timerInitData.currentDateTime.add(1, 'second');
                }
                if (timerInitData.luckydrawEndDateTime) {
                    var diff = timerInitData.luckydrawEndDateTime.diff(timerInitData.currentDateTime);

                    if (diff > 0) {
                        timerInitData.days = moment.duration(diff).days();
                        timerInitData.hours = moment.duration(diff).hours();
                        timerInitData.minutes = moment.duration(diff).minutes();
                        timerInitData.seconds = moment.duration(diff).seconds();
                    } else {
                        timerInitData.days = 0;
                        timerInitData.hours = 0;
                        timerInitData.minutes = 0;
                        timerInitData.seconds = 0;
                        timerObj.stop();
                    }
                }
                updateTimerUI(timerInitData, $('#clock'));
            };

            var updateTimerUI = function(timerData, clockElem) {
                    var days = timerData.days < 10 ? '0' + timerData.days : timerData.days;
                    var hours = timerData.hours < 10 ? '0' + timerData.hours : timerData.hours;
                    var minutes = timerData.minutes < 10 ? '0' + timerData.minutes : timerData.minutes;
                    var seconds = timerData.seconds < 10 ? '0' + timerData.seconds : timerData.seconds;
                    var template = '<span class="countdown-row countdown-show4"><span class="countdown-section"><span class="countdown-amount">'+days+
                                   '</span><span class="countdown-period">Days</span></span><span class="countdown-section"><span class="countdown-amount">' + hours +
                                   '</span><span class="countdown-period">Hours</span></span><span class="countdown-section"><span class="countdown-amount">' + minutes +
                                   '</span><span class="countdown-period">Minutes</span></span><span class="countdown-section"><span class="countdown-amount">' + seconds +
                                   '</span><span class="countdown-period">Seconds</span></span></span>';

                    if ((timerData.days === 0) && (timerData.hours ===0) && (timerData.minutes===0) && (timerData.seconds === 0)) {
                        clockElem.parent().removeClass('active-countdown');
                        clockElem.parent().addClass('inactive-countdown');
                    }
                    clockElem.html(template);
            };

            var syncDateTimeWithServer = function () {
                $.get(apiPath + 'server-time?format=Y-m-d%20H:i:s', function (data, status) {
                    if (data.code === 0) {
                        //API always return date with UTC timezone so
                        //we need to convert to mall timezone
                        var serverTime = moment.tz(data.data, 'UTC');
                        timerInitData.currentDateTime = serverTime.tz('{{ $retailer->timezone->timezone_name }}');
                    }
                });
            };

            var countdownTimer = new interval(1000, timerCallback, syncDateTimeWithServer);
            countdownTimer.run();

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
