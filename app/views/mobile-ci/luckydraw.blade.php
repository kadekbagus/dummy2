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
        <h4 id="ldtitle">{{ $luckydraw->lucky_draw_name }}</h4>
    </div>
</div>
<div class="row counter">
    <div class="col-xs-12 text-center">
        <div class="countdown">
            <span id="clock"></span>
       </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12 vertically-spaced text-center">
        <p>Draw date &amp; time : {{ date('d/m/Y H:i:s', strtotime($luckydraw->end_date)) }}</p>
    </div>
</div>
<div class="row">
    <div class="col-xs-12 text-center">
        <small>The Winner Number will appear here while you are in the Mall.</small>
    </div>
</div>
<div class="row text-center winning-number-wrapper">
    <div class="col-xs-12">
        <b>Winning Number</b>
    </div>
</div>
<div class="row text-center lucky-number-wrapper">
    <div class="col-xs-12">
        <img src="{{ asset($retailer->parent->logo) }}" clas="img-responsive">
    </div>

    <div class="row">
        <p>&nbsp;</p>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <small>
                @if ($total_number === 0)
                    You got no Lucky Draw Number yet.
                @else
                    Here are your lucky draw numbers, you have {{ number_format($total_number) }} lucky draw number. We wish you luck!.
                    This list showing {{ $per_page }} lucky draw numbers per page.
                @endif
            </small>
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
                        <a href="{{ $prev_url }}#ln-nav" class="btn btn-info btn-block {{ ($prev_url === '#' ? 'disabled' : ''); }}">Prev</a>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-sm-6 col-lg-6">
                <div class="row">
                    <div class="col-xs-10 col-xs-offset-1 col-lg-10 col-lg-offset-1">
                        <a href="{{ $next_url }}#ln-nav" class="btn btn-info btn-block {{ ($next_url === '#' ? 'disabled' : ''); }}">Next</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xs-12 text-center">
                    <small>Page {{ $current_page }} of {{ $total_pages }}.</small>
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
                        <a href="{{ $prev_url }}#ln-nav" class="btn btn-info btn-block {{ ($prev_url === '#' ? 'disabled' : ''); }}">Prev</a>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-sm-6 col-lg-6 vertically-spaced">
                <div class="row">
                    <div class="col-xs-10 col-xs-offset-1 col-lg-10 col-lg-offset-1">
                        <a href="{{ $next_url }}#ln-nav" class="btn btn-info btn-block {{ ($next_url === '#' ? 'disabled' : ''); }}">Next</a>
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
</div>

@if ($total_number > 0)
<div class="row">
    <div class="row text-center save-btn">
        <div class="col-xs-12">
            <a href="{{ URL::route('ci-luckydrawnumber-download') }}" class="btn btn-info">Save Numbers</a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12 text-center">
        <span>To save the numbers as image on your mobile phone press the &quot;Save Numbers&quot;</span>
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
<div class="modal fade" id="lddetail" tabindex="-1" role="dialog" aria-labelledby="lddetailLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="lddetailLabel">Lucky Draw Info</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12">
                        <b>{{ $luckydraw->lucky_draw_name }}</b>
                        <br>
                        <img src="{{ asset($luckydraw->image) }}" class="img-responsive">
                        <p>{{ $luckydraw->description }}</p>
                        <p>Valid until: {{ date('d M Y H:m', strtotime($luckydraw->end_date)) }}</p>
                    </div>
                </div>
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
                until:new Date('{{ date('Y/m/d H:i:s', strtotime($luckydraw->end_date)) }}')
            }); 

            $('#datenow').text(new Date().toDateString() + ' ' + new Date().getHours() + ':' + new Date().getMinutes());
        });
    </script>
@stop
