@extends('mobile-ci.layout')

@section('ext_style')
    
@stop

@section('content')
<div class="row counter">
    <div class="col-xs-12 text-center">
        <div class="countdown">
            <span id="clock"></span>
       </div>
    </div>
</div>
<div class="row text-center winning-number-wrapper">
    <div class="col-xs-12">
        <b>Winning Number</b>
    </div>
</div> 
<div class="row text-center save-btn">
    <div class="col-xs-12">
        <a download="Your_lucky_draw_number.png" class="btn btn-info" id="save">Save Numbers</a>
    </div>
</div>
<div class="row text-center lucky-number-wrapper">
    <div class="col-xs-12">
        @foreach($luckydraw->numbers as $number)
        <div class="lucky-number-container" data-number="{{$number->lucky_draw_number_id}}">{{$number->lucky_draw_number_code}}</div>
        @endforeach
    </div>
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
    {{ HTML::script('mobile-ci/scripts/jquery.countdown.min.js') }}
    {{ HTML::script('mobile-ci/scripts/html2canvas.min.js') }}
    <script type="text/javascript">
        $(document).ready(function(){
            $('#clock').countdown('{{ $luckydraw->end_date }}')
                .on('update.countdown', function(event) {
                    var format = '<div class="clock-block"><div class="clock-content">%H</div><div class="clock-content clock-label">Hour%!H</div></div><div class="clock-block"><div class="clock-content">%M</div><div class="clock-content clock-label">Minute%!M</div></div><div class="clock-block"><div class="clock-content">%S</div><div class="clock-content clock-label">Second%!S</div></div>';
                    if (event.offset.days > 0) {
                        format = '<div class="clock-block"><div class="clock-content">%-d</div><div class="clock-content clock-label">Day%!d</div></div>' + format;
                    }
                    if (event.offset.weeks > 0) {
                        format = '<div class="clock-block"><div class="clock-content">%-w</div><div class="clock-content clock-label">Week%!w</div></div>' + format;
                    }
                    $(this).html(event.strftime(format));
                });
            $('.lucky-number-container').click(function(){
                $('#numberModal .modal-body').html('');
                var num = $(this).data('number');
                $.ajax({
                    url: apiPath+'customer/luckydrawnumberpopup',
                    method: 'POST',
                    data: {
                        lid: num
                    }
                }).done(function(data){
                    console.log(data);
                    for(var x = 0; x < data.data.receipts.length; x++){
                        console.log(data.data.receipts[x].receipt_amount);
                        $('#numberModal .modal-body').html($('#numberModal .modal-body').html() + '<div class="row "><div class="col-xs-12 vertically-spaced"><p><b>Date</b><br><span class="date">'+ data.data.receipts[x].receipt_date +'</span></p><p><b>Tenant</b><br><span class="tenant">'+ data.data.receipts[x].receipt_retailer.name +'</span></p><p><b>Receipt No.</b><br><span class="receiptno">'+ data.data.receipts[x].receipt_number +'</span></p><p><b>Ammount Spent</b><br><span class="ammount">Rp '+ data.data.receipts[x].receipt_amount +'</span></p></div></div>');
                    }
                    $('#numberModal').modal();
                });
            });
            html2canvas($('.lucky-number-wrapper'), {
                    background: '#fff',
                    onrendered: function(canvas) {
                        var image = canvas.toDataURL("image/png").replace("image/png", "image/octet-stream");  // here is the most important part because if you dont replace you will get a DOM 18 exception.    
                        $('#save').attr('href', image);
                    }
                });
        });
    </script>
@stop
