@extends('emails.layouts.default')

@section('title')
Refund Notice
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
                        {{{ trans('email-customer-refund.header.invoice', [], '', $lang) }}}
                      </h1>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                  <td height="10" align="center">&nbsp;</td>
                </tr>
                <tr>
                  <td width="600">
                    <table border="0" cellpadding="0" cellspacing="0" class="no-border email-container" style="line-height:1.7em;color:#222;width:100%;max-width:640px;border:0;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                      <tbody>
                        <tr>
                          <td class="text-left invoice-info greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ trans('email-coupon-not-available.header.order_number', ['transactionId' => $transaction['id']], '', $lang) }}}</strong></td>
                          <td class="text-right invoice-info greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;text-align:right;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transactionDateTime }}}</td>
                        </tr>
                        <tr>
                          <td colspan="2" class="greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                            <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                              {{ trans('email-customer-refund.body.greeting_pulsa', ['customerName' => $customerName], '', $lang) }}
                            </p>
                            <br>

                            <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                              <strong>{{{ trans('email-customer-refund.body.transaction_labels.transaction_id', [], '', $lang) }}}</strong> {{ $transaction['id'] }}
                              <br>
                              <strong>{{{ trans('email-customer-refund.body.transaction_labels.phone', [], '', $lang) }}}</strong> {{ $pulsaPhone }}
                              <br>
                              <strong>{{{ trans('email-customer-refund.body.transaction_labels.amount', [], '', $lang) }}}</strong> {{ $transaction['total'] }}
                              <br>
                              @if (! empty($reason))
                                <strong>{{{ trans('email-customer-refund.body.transaction_labels.reason', [], '', $lang) }}}</strong> {{ $reason }}
                                <br>
                              @endif
                              <br>
                            </p>

                            <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                              {{ trans('email-customer-refund.body.content_1', [], '', $lang) }}
                              <br>
                              {{ trans('email-customer-refund.body.content_2', [], '', $lang) }}
                              <br>
                              <br>
                              {{{ trans('email-customer-refund.body.thank_you', [], '', $lang) }}}
                              <br>
                              <br>
                              {{ trans('email-customer-refund.body.cs_name') }}
                            </p>
                          </td>
                        </tr>
                      </tbody>
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


