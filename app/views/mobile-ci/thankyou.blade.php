@extends('mobile-ci.layout-headless')

@section('ext_style')
<style type="text/css">
body{
    font-family: 'Arial';
}
</style>
@stop

@section('content')
<div class="container thankyou">
    <div class="row top-space">
        <div class="col-xs-12 text-center">
            <h2>{{ Lang::get('mobileci.thank_you.thank_you') }}</h2>
            <div id="receipt" class="receipt">
                <img id="receipt-img" class="img-responsive" src="data:image/png;base64,{{$base64receipt}}">
            </div>
            <a class="btn btn-info" id="saveTicketBtn" data-transaction="{{ $transaction->transaction_id }}" href="data:image/png;base64,{{$base64receipt}}" download="receipt_{{\Carbon\Carbon::now()}}.png">{{ Lang::get('mobileci.thank_you.save_ticket_button') }}</a>
            <h3>{{ Lang::get('mobileci.thank_you.thank_you_message') }}</h3>
            @if(!empty($retailer->parent->url))
            <h5>{{ Lang::get('mobileci.thank_you.dont_forget_message') }}</h5>
            <a href="http://www.myorbit.com">www.myorbit.com</a>
            <h5>{{ Lang::get('mobileci.thank_you.promo_message') }}</h5>
            @endif
        </div>
        <div class="col-xs-12 text-center vertically-spaced">
            <a href="{{ url('/customer/logout') }}" class="btn btn-info">{{ Lang::get('mobileci.thank_you.shop_again_button') }}</a>
        </div>
    </div>
</div>
@stop

@section('footer')
<footer>
    <div class="row">
        <div class="col-xs-12 text-center">
            <img class="img-responsive orbit-footer"  src="{{ asset('mobile-ci/images/orbit_footer.png') }}">
        </div>
        <div class="text-center vertically-spaced">
            {{ 'Orbit v' . ORBIT_APP_VERSION }}
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12 text-center">
            <span class="fa-stack fa-lg offline">
              <i class="fa fa-globe fa-stack-1x globe"></i>
              <i id="offlinemark"></i>
            </span>
        </div>
    </div>
</footer>
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/html2canvas.min.js') }}
{{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
<script type="text/javascript">
  $(document).ready(function(){
    var img64 = $('#receipt-img').attr('src');
    $.ajax({
      url: '{{ route('send-ticket') }}',
      data: {
        ticketdata: img64,
        transactionid: '{{ $transaction->transaction_id }}'
      },
      method: 'POST'
    });

    @if($retailer->parent->currency == 'IDR')
    $('.formatted-num').each(function(index){
      $(this).text(parseFloat($(this).text()).toFixed(0)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 0, vMin: -9999999999.99});
    });
    @else
    $('.formatted-num').each(function(index){
      $(this).text(parseFloat($(this).text()).toFixed(2)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 2, vMin: -9999999999.99});
    });
    @endif
  
    $('.percentage-num').each(function(index){
      var num = parseFloat($(this).text());
      $(this).text(num+'%');
    });
    
    
    
    var button = document.getElementById('saveTicketBtn');
    button.addEventListener('click', function (e) {
        var transactiondata = $(this).data('transaction');

        $.ajax({
          url: '{{ route('click-save-receipt-activity') }}',
          data: {
            transactiondata: transactiondata
          },
          method: 'POST'
        });

    });
  });
</script>
@stop
