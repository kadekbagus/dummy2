@extends('mobile-ci.layout')
@section('content')
<div class="container vertically-spaced">
    <div class="row">
        <div class="col-xs-12">
            <h4>{{ Lang::get('mobileci.payment.total_to_pay_label') }} : {{$retailer->parent->currency_symbol}} <span class="formatted-num">{{ $cartdata->cartsummary->total_to_pay }}</span></h4>
            <form role="form" name="paymentForm" method="POST" action="{{ url('/customer/savetransaction') }}">
                <input type="hidden" name="payment_method" value="online_payment">
                <div class="form-group">
                    <label for="exampleInputEmail1">{{ Lang::get('mobileci.payment.name_label') }}</label>
                    <input type="text" class="form-control" id="exampleInputEmail1" placeholder="{{ Lang::get('mobileci.payment.name_placeholder') }}">
                </div>
                <div class="form-group">
                    <label for="exampleInputEmail1">{{ Lang::get('mobileci.payment.card_type_label') }}</label>
                    <select class="form-control">
                        <option value="1">Visa</option>
                        <option value="2">Master Card</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="exampleInputEmail1">{{ Lang::get('mobileci.payment.card_number_label') }}</label>
                    <input type="text" class="form-control" id="exampleInputEmail1" placeholder="{{ Lang::get('mobileci.payment.card_number_placeholder') }}">
                </div>
                <div class="form-group">
                    <label for="exampleInputEmail1">{{ Lang::get('mobileci.payment.expire_label') }}</label>
                    <div class="row">
                        <div class="col-xs-6">
                            <select class="form-control">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                                <option value="9">9</option>
                                <option value="10">10</option>
                                <option value="11">11</option>
                                <option value="12">12</option>
                            </select>
                        </div>
                        <div class="col-xs-6">
                            <select class="form-control">
                                @for($c = 0; $c < 10; $c++)
                                <option value="{{ \Carbon\Carbon::now()->addYears($c)->year }}">{{ \Carbon\Carbon::now()->addYears($c)->year }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="exampleInputEmail1">{{ Lang::get('mobileci.payment.ccv_label') }}</label>
                    <div class="row">
                        <div class="col-xs-6">
                            <input type="text" class="form-control" id="exampleInputEmail1" placeholder="{{ Lang::get('mobileci.payment.ccv_placeholder') }}">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-xs-6">
                            <button type="submit" class="btn btn-success">{{ Lang::get('mobileci.payment.submit_button') }}</button>
                        </div>
                        <div class="col-xs-6 text-right">
                            <a href="{{ url('customer/cart') }}" class="btn btn-info">{{ Lang::get('mobileci.payment.cancel_button') }}</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12 text-center merchant-logo">
            <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" style="margin: 0 auto;" />
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
<script type="text/javascript">
    @if($retailer->parent->currency == 'IDR')
    $('.formatted-num').each(function(index){
      $(this).text(parseFloat($(this).text()).toFixed(0)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 0, vMin: -9999999999.99});
    });
    @else
    $('.formatted-num').each(function(index){
      $(this).text(parseFloat($(this).text()).toFixed(2)).autoNumeric('init', {aSep: ',', aDec: '.', mDec: 2, vMin: -9999999999.99});
    });
    @endif
</script>
@stop