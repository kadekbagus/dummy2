@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<div class="row">
    @if(! empty($luckydraw->image))
    <a href="{{ asset($luckydraw->image) }}" data-featherlight="image" data-featherlight-close-on-esc="false" data-featherlight-close-on-click="false" class="zoomer text-left"><img src="{{ asset($luckydraw->image) }}" class="img-responsive" style="width:100%;"></a>
    @else
    <img src="{{ asset('mobile-ci/images/default_lucky_number.png') }}" class="img-responsive" style="width:100%;">
    @endif
</div>
<div class="row">
    <div class="col-xs-12 text-left">
        <p>
            {{ nl2br($luckydraw->description) }}
        </p>
    </div>
</div>
<div class="row">
    <div class="col-xs-12 text-left">
        <h4>{{ Lang::get('mobileci.lucky_draw.period') }}</h4>
        <p>
            {{ date('d M Y', strtotime($luckydraw->start_date)) }} - {{ date('d M Y', strtotime($luckydraw->end_date)) }}
        </p>
    </div>
</div>
<div class="row">
    <div class="col-xs-12 text-left">
        <h4>{{ Lang::get('mobileci.lucky_draw.draw_date') }}</h4>
        <p>
            {{ date('d M Y', strtotime($luckydraw->draw_date)) }}
        </p>
    </div>
</div>
@if(!empty($luckydraw))
    @if(strtotime($servertime) > strtotime($luckydraw->draw_date))
        @if(! empty($luckydraw->prizes))
        <div class="row text-center vertically-spaced">
            <div class="col-xs-12">
                <a href="{{ url('/customer/luckydraw-announcement?id=' . $luckydraw->lucky_draw_id) }}" class="btn btn-info btn-block">{{ Lang::get('mobileci.lucky_draw.see_prizes_and_winner') }}</a>
            </div>
        </div>
        @endif
    @else
        <div class="row text-center vertically-spaced">
            <div class="col-xs-12">
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
    @if(!empty($luckydraw) && $luckydraw->end_date >= $servertime)
    <div class="row vertically-spaced">
        <h4>{{ Lang::get('mobileci.lucky_draw.my_lucky_draw_number') }}</h4>
    </div>

    <div class="row">
        <div class="col-xs-12">    
            @if ($total_number === 0)
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
    @if ($total_number > 0)
    <div class="row text-center save-btn vertically-spaced">
        <div class="col-xs-1"></div>
        <div class="col-xs-10">
            <a href="{{ url('/customer/luckydrawnumber/download?id=' . $luckydraw->lucky_draw_id) }}" class="btn btn-info btn-block">{{ Lang::get('mobileci.lucky_draw.save_numbers') }}</a>
        </div>
        <div class="col-xs-1"></div>
    </div>
    @endif
    @if ($total_pages > 1)
        <div class="row">
            <div class="col-xs-12 text-center">
                <div class="col-xs-12">
                    <ul class="ld-pagination">
                        @if($current_page != '1')
                        <li><a href="{{ URL::route('ci-luckydraw') . '&id='. $luckydraw->lucky_draw_id . '&page=1' }}#ln-nav" class="{{ ($prev_url === '#' ? 'disabled' : ''); }}"><i class="fa fa-angle-double-left"></i></a></li>
                        @else
                        <li><a class="disabled" style="color:#dedede;"><i class="fa fa-angle-double-left"></i></a></li>
                        @endif
                        @if(! in_array(1, $paginationPage))
                        <li class="ld-pagination-ellipsis">...</li>
                        @endif
                        @foreach($paginationPage as $p)
                        <li @if($current_page == $p) class="ld-pagination-active" @endif><a href="{{ URL::route('ci-luckydraw') . '&id='. $luckydraw->lucky_draw_id . '&page=' . $p }}#ln-nav" class="{{ ($prev_url === '#' ? 'disabled' : ''); }}">{{ $p }}</a></li>
                        @endforeach
                        @if(! in_array($total_pages, $paginationPage))
                        <li class="ld-pagination-ellipsis">...</li>
                        @endif
                        @if($current_page != $total_pages)
                        <li><a href="{{ URL::route('ci-luckydraw') . '&id='. $luckydraw->lucky_draw_id . '&page=' . $total_pages }}#ln-nav" class="{{ ($prev_url === '#' ? 'disabled' : ''); }}"><i class="fa fa-angle-double-right"></i></a></li>
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
    {{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
    {{ HTML::script('mobile-ci/scripts/jquery.plugin.min.js') }}
    {{ HTML::script('mobile-ci/scripts/jquery.countdown.min.js') }}
    {{ HTML::script('mobile-ci/scripts/html2canvas.min.js') }}
    {{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
    {{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
    <script type="text/javascript">
        $(document).ready(function(){
            $('.lucky-number-container').each(function(index){
                // $(this).text(parseFloat($(this).text()).toFixed(0)).autoNumeric('init', {aSep: '-', aDec: '.', mDec: 0, vMin: -9999999999.99});
            });
            $('#ldtitle').click(function(){
                $('#lddetail').modal();
            })

            $('#clock').countdown({
                start:$.countdown.UTCDate({{ \Carbon\Carbon::now($retailer->timezone->timezone_name)->offsetHours }}, new Date('{{$servertime}}')),
                @if(!empty($luckydraw))
                until:$.countdown.UTCDate({{ \Carbon\Carbon::now($retailer->timezone->timezone_name)->offsetHours }}, new Date('{{ date('Y/m/d H:i:s', strtotime($luckydraw->end_date)) }}')),
                layout: '<span class="countdown-row countdown-show4"><span class="countdown-section"><span class="countdown-amount">{dn}</span><span class="countdown-period">{dl}</span></span><span class="countdown-section"><span class="countdown-amount">{hn}</span><span class="countdown-period">{hl}</span></span><span class="countdown-section"><span class="countdown-amount">{mn}</span><span class="countdown-period">{ml}</span></span><span class="countdown-section"><span class="countdown-amount">{sn}</span><span class="countdown-period">{sl}</span></span></span>'
                @else
                layout: '<span class="countdown-row countdown-show4"><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">{dl}</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">{hl}</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">{ml}</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">{sl}</span></span></span>'
                @endif
            });

            $('#datenow').text(new Date().toDateString() + ' ' + new Date().getHours() + ':' + new Date().getMinutes());
        });
    </script>
@stop
