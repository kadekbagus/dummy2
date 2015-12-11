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
        <h4>{{ Lang::get('mobileci.lucky_draw.description') }}</h4>
        <p>
            {{ $luckydraw->description }}
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
                <a href="{{ url('/customer/luckydraw-announcement?id=' . $luckydraw->lucky_draw_id) }}" class="btn btn-info btn-block">{{ Lang::get('mobileci.lucky_draw.winning_number') }}</a>
            </div>
        </div>
        @endif
    @else
        @if(! empty($luckydraw->prizes))
        <div class="row text-center vertically-spaced">
            <div class="col-xs-12">
                <a href="{{ url('/customer/luckydraw-announcement?id=' . $luckydraw->lucky_draw_id) }}" class="btn btn-info btn-block">{{ Lang::get('mobileci.lucky_draw.see_prizes') }}</a>
            </div>
        </div>
        @endif
    @endif
@endif
<div class="row counter">
    <div class="col-xs-12 text-center">
        <div class="countdown @if($luckydraw->status == 'active' && $total_number > 0) @if(strtotime($servertime) < strtotime($luckydraw->end_date)) active-countdown @else inactive-countdown @endif @elseif($luckydraw->status == 'active' && $total_number === 0) danger-countdown @else danger-countdown @endif">
            <span id="clock" @if(empty($luckydraw)) class="no-luck" @endif></span>
        </div>
    </div>
</div>
<div class="row text-center lucky-number-wrapper">
    @if(!empty($luckydraw))
    <div class="row">
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

    <div class="row">
        <p>&nbsp;</p>
    </div>

    <div class="row">
        <div class="col-xs-12">
            @if ($total_pages > 1)
            <div class="col-xs-6 col-sm-6 col-lg-6">
                <div class="row">
                    <div class="col-xs-10 col-xs-offset-1">
                        <a href="{{ $prev_url }}#ln-nav" class="btn btn-info btn-block {{ ($prev_url === '#' ? 'disabled' : ''); }}">{{ Lang::get('mobileci.lucky_draw.prev') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-sm-6 col-lg-6">
                <div class="row">
                    <div class="col-xs-10 col-xs-offset-1 col-lg-10 col-lg-offset-1">
                        <a href="{{ $next_url }}#ln-nav" class="btn btn-info btn-block {{ ($next_url === '#' ? 'disabled' : ''); }}">{{ Lang::get('mobileci.lucky_draw.next') }}</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xs-12 text-center">
                    <small>{{ Lang::get('mobileci.lucky_draw.page') }} {{ $current_page }} {{ Lang::get('mobileci.lucky_draw.of') }} {{ $total_pages }}.</small>
                </div>
            </div>
            @endif

            @foreach($numbers as $i=>$number)
            <div class="col-xs-6 col-sm-6 col-lg-6">
                <div class="lucky-number-container" data-number="{{$number->lucky_draw_number_id}}">{{ $number->lucky_draw_number_code }}</div>
            </div>
            @endforeach

            @if ($total_number % 2 !== 0)
            <div class="col-xs-12 col-sm-6 col-lg-6">
                <div class="lucky-number-container" data-number=""></div>
            </div>
            @endif

            @if ($total_pages > 1)
            <div class="row ">
            <div class="col-xs-6 col-sm-6 col-lg-6 vertically-spaced">
                <div class="row">
                    <div class="col-xs-10 col-xs-offset-1">
                        <a href="{{ $prev_url }}#ln-nav" class="btn btn-info btn-block {{ ($prev_url === '#' ? 'disabled' : ''); }}">{{ Lang::get('mobileci.lucky_draw.prev') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-sm-6 col-lg-6 vertically-spaced">
                <div class="row">
                    <div class="col-xs-10 col-xs-offset-1 col-lg-10 col-lg-offset-1">
                        <a href="{{ $next_url }}#ln-nav" class="btn btn-info btn-block {{ ($next_url === '#' ? 'disabled' : ''); }}">{{ Lang::get('mobileci.lucky_draw.next') }}</a>
                    </div>
                </div>
            </div>
            </div>
            <div class="row">
                <div class="col-xs-12 text-center">
                    <small>Page {{ $current_page }} of {{ $total_pages }}.</small>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>

@if ($total_number > 0)
<div class="row">
    <div class="row text-center save-btn">
        <div class="col-xs-12">
            <a href="{{ URL::route('ci-luckydrawnumber-download') }}" class="btn btn-info btn-block">{{ Lang::get('mobileci.lucky_draw.save_numbers') }}</a>
        </div>
    </div>
</div>
@endif

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
                start:new Date('{{$servertime}}'),
                @if(!empty($luckydraw))
                until:new Date('{{ date('Y/m/d H:i:s', strtotime($luckydraw->end_date)) }}'),
                layout: '<span class="countdown-row countdown-show4"><span class="countdown-section"><span class="countdown-amount">{dn}</span><span class="countdown-period">{dl}</span></span><span class="countdown-section"><span class="countdown-amount">{hn}</span><span class="countdown-period">{hl}</span></span><span class="countdown-section"><span class="countdown-amount">{mn}</span><span class="countdown-period">{ml}</span></span><span class="countdown-section"><span class="countdown-amount">{sn}</span><span class="countdown-period">{sl}</span></span></span>'
                @else
                layout: '<span class="countdown-row countdown-show4"><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">{dl}</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">{hl}</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">{ml}</span></span><span class="countdown-section"><span class="countdown-amount">0</span><span class="countdown-period">{sl}</span></span></span>'
                @endif
            });

            $('#datenow').text(new Date().toDateString() + ' ' + new Date().getHours() + ':' + new Date().getMinutes());
        });
    </script>
@stop
