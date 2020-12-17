@extends('emails.layouts.default')

@section('title')
Pending Payment
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
                        {{ trans('email-pending-payment.header.invoice', [], '', $lang) }}
                      </h1>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                  <td width="600" align="right" valign="top" class="transaction-date">
                    <strong>{{{ $transactionDateTime }}}</strong>
                  </td>
                </tr>
                <tr>
                  <td height="20" align="center">&nbsp;</td>
                </tr>

                <tr>
                  <td width="300" class="mobile" align="left" valign="top">
                    <h3 class="greeting-username">
                      {{ trans('email-pending-payment.body.greeting_digital_product.customer_name', [
                          'customerName' => $customerName,
                        ], '', $lang) }}
                    </h3>
                    <p class="greeting-text">
                      {{ trans('email-pending-payment.body.greeting_digital_product.body', [
                        'productName' => $transaction['items'][0]['name'],
                        'paymentMethod' => $paymentMethod,
                        ], '', $lang) }}
                    </p>
                  </td>
                </tr>

                <tr>
                  <td width="600" class="mobile" valign="middle">
                    <p class="transaction-details">
                      <strong>
                        {{{ trans('email-pending-payment.body.transaction_labels.transaction_id', [], '', $lang) }}}
                      </strong> {{ $transaction['id'] }}
                      <br>
                      <strong>
                        {{{ trans('email-pending-payment.body.transaction_labels.transaction_date', [], '', $lang) }}}
                      </strong> {{ $transactionDateTime }}
                      <br>
                      <strong>
                        {{{ trans('email-pending-payment.body.transaction_labels.customer_name', [], '', $lang) }}}
                      </strong> {{ $customerName }}
                      <br>
                      <strong>
                        {{{ trans('email-pending-payment.body.transaction_labels.email', [], '', $lang) }}}
                      </strong> {{ $customerEmail }}
                      <br>
                      <br>
                      <strong>
                        {{{ trans('email-pending-payment.body.transaction_labels.product', [], '', $lang) }}}
                      </strong> {{ $transaction['items'][0]['name'] }}
                      <br>
                      <strong>
                        {{{ trans('email-pending-payment.body.transaction_labels.pulsa_price', [], '', $lang) }}}
                      </strong> {{ $transaction['items'][0]['price'] }}
                      <span style="margin-left: 20px;">X {{ $transaction['items'][0]['quantity'] }}</span>
                      <br>
                      @if (count($transaction['discounts']) > 0)
                        @foreach($transaction['discounts'] as $discount)
                          <strong>{{{ $discount['name'] }}}:</strong> {{ $discount['price'] }}
                        @endforeach
                      @endif
                      <strong>
                        {{{ trans('email-pending-payment.body.transaction_labels.total_amount', [], '', $lang) }}}
                      </strong> {{ $transaction['total'] }}
                      <br>
                    </p>
                  </td>
                </tr>

                <tr>
                  <td height="30" align="center" class="separator">&nbsp;</td>
                </tr>

                <tr>
                  <td width="600" class="mobile" align="center" valign="middle">
                    <a href="{{{ $myWalletUrl }}}" class="btn btn-block mx-4">
                      {{{ trans('email-pending-payment.body.btn_my_wallet', [], '', $lang) }}}
                    </a>
                    <a href="{{{ $cancelUrl }}}" class="btn btn-light btn-block mx-4">
                      {{{ trans('email-pending-payment.body.btn_cancel_purchase', [], '', $lang) }}}
                    </a>
                  </td>
                </tr>

                <tr>
                  <td height="30" align="center" class="separator">&nbsp;</td>
                </tr>
                <tr>
                  <td width="600" class="mobile" align="left" valign="top">
                    <p class="greeting-text">
                      {{ trans('email-pending-payment.body.payment-info-line-3', [], '', $lang) }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td height="30" align="center" class="separator">&nbsp;</td>
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
