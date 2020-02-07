<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Pending Payment</title>

  @include('emails.components.styles')
</head>
<body style="margin:0; padding:0; background-color:#F2F2F2;">

  <span style="display: block; width: 640px !important; max-width: 640px; height: 1px" class="mobileOff"></span>

  <center>
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#F2F2F2">
      <tr>
        <td align="center" valign="middle">
          <img src="https://s3-ap-southeast-1.amazonaws.com/asset1.gotomalls.com/uploads/emails/gtm-logo.png" class="logo" alt="Logo">
        </td>
      </tr>

      <?php
        $langs = ['id', 'en'];
      ?>

      @foreach($langs as $lang)
        <tr>
          <td align="center" valign="top">

            <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF" style="background-color: transparent;">
              <tr>
                <td align="center" valign="middle" style="box-shadow: 0 0 20px #e0e0e0; border-radius:5px;background-color: #FFF;">

                  <table width="640" cellpadding="0" cellspacing="0" border="0" class="container mobile-full-width">
                    <tr>
                      <td align="center" valign="middle" height="184" class="greeting-title-container" style="border-radius: 5px 5px 0 0;">
                          <h1 class="greeting-title">{{ trans('email-pending-payment.header.invoice', [], '', $lang) }}</h1>
                      </td>
                    </tr>
                  </table>

                  <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                    <tr>
                      <td width="300" class="mobile" align="left" valign="top">
                        <h3 class="greeting-username">
                          {{ trans('email-pending-payment.body.greeting_digital_product.customer_name', [
                              'customerName' => $customerName,
                              'paymentMethod' => 'GOJEK'
                            ], '', $lang) }}</h3>
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
                          <strong>{{{ trans('email-pending-payment.body.transaction_labels.transaction_id', [], '', $lang) }}}</strong> {{ $transaction['id'] }}
                          <br>
                          <strong>{{{ trans('email-pending-payment.body.transaction_labels.transaction_date', [], '', $lang) }}}</strong> {{ $transactionDateTime }}
                          <br>
                          <strong>{{{ trans('email-pending-payment.body.transaction_labels.customer_name', [], '', $lang) }}}</strong> {{ $customerName }}
                          <br>
                          <strong>{{{ trans('email-pending-payment.body.transaction_labels.email', [], '', $lang) }}}</strong> {{ $customerEmail }}
                          <br>
                          <br>
                          <strong>{{{ trans('email-pending-payment.body.transaction_labels.product', [], '', $lang) }}}</strong> {{ $transaction['items'][0]['name'] }}
                          <br>
                          <strong>{{{ trans('email-pending-payment.body.transaction_labels.pulsa_price', [], '', $lang) }}}</strong> {{ $transaction['items'][0]['price'] }}
                          <span style="margin-left: 20px;">X {{ $transaction['items'][0]['quantity'] }}</span>
                          <br>
                          @if (count($transaction['discounts']) > 0)
                            @foreach($transaction['discounts'] as $discount)
                              <strong>{{{ $discount['name'] }}}:</strong> {{ $discount['price'] }}
                            @endforeach
                          @endif
                          <strong>{{{ trans('email-pending-payment.body.transaction_labels.total_amount', [], '', $lang) }}}</strong> {{ $transaction['total'] }}
                          <br>
                        </p>
                      </td>
                    </tr>
                    <tr>
                      <td height="30" align="center" class="separator">&nbsp;</td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile" align="center" valign="middle">
                        <a href="{{{ $myWalletUrl }}}" class="btn btn-block">{{{ trans('email-pending-payment.body.btn_my_wallet', [], '', $lang) }}}</a>
                        <a href="{{{ $cancelUrl }}}" class="btn btn-light btn-block">{{{ trans('email-pending-payment.body.btn_cancel_purchase', [], '', $lang) }}}</a>
                      </td>
                    </tr>
                    <tr>
                      <td height="30" align="center" class="separator">&nbsp;</td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile" align="left" valign="top">
                        <p class="greeting-text">{{ trans('email-pending-payment.body.payment-info-line-3') }}</p>
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

      <tr>
        <td align="center" valign="top">
          @include('emails.components.new-basic-footer')
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
