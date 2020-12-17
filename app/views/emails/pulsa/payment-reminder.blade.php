@extends('emails.layouts.default')

@section('title')
Complete Your Payment | Gotomalls.com
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
                        {{{ trans('email-before-transaction-expired.header.invoice', [], '', $lang) }}}
                      </h1>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                  <td width="600" align="right" valign="top" class="transaction-date"><strong>{{{ $transactionDateTime }}}</strong></td>
                </tr>
                <tr>
                  <td height="20" align="center">&nbsp;</td>
                </tr>
                <tr>
                  <td>
                    <table>
                      <tr>
                        <td colspan="2" class="invoice-body" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:80px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                          <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                            {{ trans('email-before-transaction-expired.body.greeting_pulsa', ['customerName' => $customerName], '', $lang) }}
                          </p>
                          <br>

                          <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                            <strong>{{{ trans('email-before-transaction-expired.body.transaction_labels.transaction_id', [], '', $lang) }}}</strong> {{ $transaction['id'] }}
                            <br>
                            <strong>{{{ trans('email-before-transaction-expired.body.transaction_labels.transaction_date', [], '', $lang) }}}</strong> {{ $transaction['date'] }}
                            <br>
                            <strong>{{{ trans('email-before-transaction-expired.body.transaction_labels.customer_name', [], '', $lang) }}}</strong> {{ $customerName }}
                            <br>
                            <strong>{{{ trans('email-before-transaction-expired.body.transaction_labels.email', [], '', $lang) }}}</strong> {{ $customerEmail }}
                            <br>
                            <br>
                            <strong>{{{ trans('email-before-transaction-expired.body.transaction_labels.pulsa_phone_number', [], '', $lang) }}}</strong> {{ $pulsaPhoneNumber }}
                            <br>
                            <strong>{{{ trans('email-before-transaction-expired.body.transaction_labels.pulsa_name', [], '', $lang) }}}</strong> {{ $transaction['items'][0]['name'] }}
                            <br>
                            <div style="width: 100%;">
                              <strong>{{{ trans('email-before-transaction-expired.body.transaction_labels.pulsa_price', [], '', $lang) }}}</strong> {{ $transaction['items'][0]['price'] }}
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
                            <strong>{{{ trans('email-before-transaction-expired.body.transaction_labels.total_amount', [], '', $lang) }}}</strong> {{ $transaction['total'] }}
                            <br>
                          </p>
                          <br>

                          @if ($lang === 'id')
                            @include('emails.pending-payment.payment-info-pulsa', compact('paymentInfo', 'paymentExpiration', 'myPurchasesUrl', 'cancelUrl', 'hideExpiration'))
                          @elseif ($lang === 'en')
                            @include('emails.pending-payment.payment-info-pulsa-en', compact('paymentInfo', 'paymentExpiration', 'myPurchasesUrl', 'cancelUrl', 'hideExpiration'))
                          @endif

                          <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                            <br>
                            {{ trans('email-before-transaction-expired.body.payment-info-line-3', [], '', $lang) }}
                          </p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td height="20" align="center">&nbsp;</td>
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
