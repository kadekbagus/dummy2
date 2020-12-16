@extends('emails.layouts.default')

@section('title')
Transaction Aborted | Gotomalls.com
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
                        {{ trans('email-aborted-payment.header.email-type', [], '', $lang) }}
                      </h1>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                  <td width="600" align="right" valign="top" class="transaction-date">
                    <strong>
                      {{{ $transactionDateTime }}}
                    </strong>
                  </td>
                </tr>
                <tr>
                  <td height="20" align="center">&nbsp;</td>
                </tr>
                <tr>
                  <td colspan="2" class="invoice-body" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                    <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                      {{ trans('email-aborted-payment.body.greeting', ['customerName' => $customerName], '', $lang) }}
                    </p>
                    <br>
                    <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                      <strong>{{{ trans('email-aborted-payment.body.transaction_labels.pulsa_name', [], '', $lang) }}}</strong> {{ $transaction['items'][0]['name'] }}
                      <br>
                      <strong>{{{ trans('email-aborted-payment.body.transaction_labels.pulsa_phone_number', [], '', $lang) }}}</strong> {{ $pulsaPhoneNumber }}
                      <br>
                      <strong>{{{ trans('email-aborted-payment.body.transaction_labels.transaction_id', [], '', $lang) }}}</strong> {{ $transaction['id'] }}
                      <br>
                    </p>

                    @if ($lang === 'id')

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        <br>
                        {{ trans('email-aborted-payment.body.payment-info-line-1-pulsa', ['transactionDateTime' => $transactionDateTime], '', $lang) }}
                      </p>

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        <br>
                        {{ trans('email-aborted-payment.body.payment-info-line-2-pulsa', $cs, '', $lang) }}
                      </p>
                    @elseif ($lang === 'en')
                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        <br>
                        {{ trans('email-aborted-payment.body.payment-info-line-1') }}
                      </p>
                      <br>

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        {{ trans('email-aborted-payment.body.payment-info-line-2') }}
                      </p>
                      <br>

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        {{ trans('email-aborted-payment.body.payment-info-line-3') }}
                      </p>
                      <br>

                      <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                        {{ trans('email-aborted-payment.body.payment-info-line-4-pulsa') }}
                      </p>
                    @endif

                    <p style="text-align: center">
                        <br>
                        <a href="{{{ $buyUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;background-color:#f43d3c;color:#fff;font-weight:bold;font-size:16px;display:inline-block;padding:10px 20px;text-decoration:none;">
                          {{{ trans('email-aborted-payment.body.buttons.buy_pulsa', [], '', $lang) }}}
                        </a>
                    </p>

                    <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                      <br>
                      {{ trans('email-aborted-payment.body.regards', [], '', $lang) }}
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


