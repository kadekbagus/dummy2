@extends('emails.layouts.default')

@section('title')
Transaction Canceled | Gotomalls.com
@stop

@section('content')
  <?php
    $langs = ['id', 'en'];
    $originalProductType = isset($productType) ? $productType : 'default';
  ?>

  @foreach($langs as $lang)
    <?php $productType = trans("email-payment.product_type.{$originalProductType}", [], '', $lang); ?>
    <tr>
      <td align="center" valign="top">

        <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF" style="background-color: transparent;">
          <tr>
            <td align="center" valign="middle" style="box-shadow: 0 0 20px #e0e0e0; border-radius:5px;background-color: #FFF;">

              <table width="640" cellpadding="0" cellspacing="0" border="0" class="container mobile-full-width">
                <tr>
                  <td align="center" valign="middle" height="184" class="greeting-title-container" style="border-radius: 5px 5px 0 0;">
                      <h1 class="greeting-title">
                        {{{ trans('email-canceled-payment.header.email-type', [], '', $lang) }}}
                      </h1>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                  <td height="20" align="center">&nbsp;</td>
                </tr>

                <tr>
                  <td colspan="2" class="greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                    <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                      {{ trans('email-canceled-payment.body.greeting', ['customerName' => $customerName], '', $lang) }}
                    </p>
                    <br>
                    <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                      <strong>{{{ trans('email-canceled-payment.body.transaction_labels.transaction_id', [], '', $lang) }}}</strong> {{ $transaction['id'] }}
                      <br>
                      <strong>{{{ trans('email-canceled-payment.body.transaction_labels.transaction_date', [], '', $lang) }}}</strong> {{ $transactionDateTime }}
                      <br>
                      <strong>{{{ trans('email-canceled-payment.body.transaction_labels.customer_name', [], '', $lang) }}}</strong> {{ $customerName }}
                      <br>
                      <strong>{{{ trans('email-canceled-payment.body.transaction_labels.email', [], '', $lang) }}}</strong> {{ $customerEmail }}
                      <br>
                      <strong>{{{ trans('email-canceled-payment.body.transaction_labels.electricity_phone_number', [], '', $lang) }}}</strong> {{ $pulsaPhoneNumber }}
                      <br>
                      <br>
                      <strong>{{{ trans('email-canceled-payment.body.transaction_labels.electricity_name', [], '', $lang) }}}</strong> {{ $transaction['items'][0]['name'] }}
                      <br>
                      <div style="width: 100%;">
                        <strong>{{{ trans('email-canceled-payment.body.transaction_labels.electricity_price', [], '', $lang) }}}</strong> {{ $transaction['items'][0]['price'] }}
                        &nbsp;&nbsp;&nbsp;
                        X {{ $transaction['items'][0]['quantity'] }}
                      </div>
                      @if (count($transaction['discounts']) > 0)
                        @foreach($transaction['discounts'] as $discount)
                          <div style="width: 100%;">
                            <div style="width: 90%;display: inline-block;">
                              <strong>{{{ trans('label.discount', [], '', $lang) }}} {{{ $discount['name'] }}}</strong>: {{ $discount['price'] }}
                            </div>
                          </div>
                        @endforeach
                      @endif
                      <br>
                      <strong>{{{ trans('email-canceled-payment.body.transaction_labels.total_amount', [], '', $lang) }}}</strong> {{ $transaction['total'] }}
                      <br>
                      <strong>{{{ trans('email-canceled-payment.body.transaction_labels.status', [], '', $lang) }}} <span style="color:#f43d3c;">{{ trans('email-canceled-payment.body.transaction_labels.status_canceled', [], '', $lang) }}</span></strong>
                      <br>
                    </p>

                    @if ($lang === 'id')

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        <br>
                        {{ trans('email-canceled-payment.body.payment-info-line-1-electricity', ['transactionDateTime' => $transactionDateTime], '', $lang) }}
                      </p>

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        <br>
                        {{ trans('email-canceled-payment.body.payment-info-line-2-electricity', $cs, '', $lang) }}
                      </p>
                    @elseif ($lang === 'en')
                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        <br>
                        {{ trans('email-canceled-payment.body.payment-info-line-1') }}
                      </p>
                      <br>

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        {{ trans('email-canceled-payment.body.payment-info-line-2') }}
                      </p>
                      <br>

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        {{ trans('email-canceled-payment.body.payment-info-line-3') }}
                      </p>
                      <br>

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        {{ trans('email-canceled-payment.body.payment-info-line-4-electricity', ['productType' => $productType], '', 'en') }}
                      </p>
                      <br>
                    @endif

                    <p style="text-align: center">
                      <br>
                      <a href="{{{ $buyUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;background-color:#f43d3c;color:#fff;font-weight:bold;font-size:16px;display:inline-block;padding:10px 20px;text-decoration:none;">
                        {{{ trans('email-canceled-payment.body.buttons.buy_electricity', [], '', $lang) }}}
                      </a>
                    </p>

                    <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                      <br>
                      {{ trans('email-canceled-payment.body.regards', [], '', $lang) }}
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td height="30" align="center" class="separator">&nbsp;</td>
    </tr>
  @endforeach
@stop

