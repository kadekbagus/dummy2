<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Transaction Expired</title>

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
                          <h1 class="greeting-title">{{ trans('email-expired-payment.header.email-type', [], '', $lang) }}</h1>
                      </td>
                    </tr>
                  </table>

                  <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                    <tr>
                      <td width="300" class="mobile" align="left" valign="top">
                        <h3 class="greeting-username">
                          {{ trans('email-expired-payment.body.greeting_digital_product.customer_name', ['customerName' => $customerName], '', $lang) }}</h3>
                        <p class="greeting-text">
                          {{ trans('email-expired-payment.body.greeting_digital_product.body', [], '', $lang) }}
                        </p>
                      </td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile" valign="middle">
                        <p class="transaction-details">
                          <strong>{{{ trans('email-expired-payment.body.transaction_labels.product_name', [], '', $lang) }}}</strong> {{ $transaction['items'][0]['name'] }}
                          <br>
                          <strong>{{{ trans('email-expired-payment.body.transaction_labels.transaction_id', [], '', $lang) }}}</strong> {{ $transaction['id'] }}
                        </p>

                        <p class="greeting-text">
                          <br>
                          {{ trans('email-expired-payment.body.payment-info-line-1', ['transactionDateTime' => $transactionDateTime], '', $lang) }}
                        </p>

                        <p class="greeting-text">
                          {{ trans('email-expired-payment.body.payment-info-line-2', $cs, '', $lang) }}
                        </p>

                        @if ($lang === 'en')
                          <p class="greeting-text">
                            {{ trans('email-expired-payment.body.payment-info-line-3', [], '', $lang) }}
                          </p>

                          <p class="greeting-text">
                            {{ trans('email-expired-payment.body.payment-info-line-4-digital-product', ['productType' => $productType], '', $lang) }}
                          </p>
                        @endif
                      </td>
                    </tr>
                    <tr>
                      <td height="30" align="center" class="separator">&nbsp;</td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile" align="center" valign="middle">
                        <a href="{{{ $buyUrl }}}" class="btn btn-block">
                          {{{ trans('email-expired-payment.body.buttons.buy_digital_product', ['productType' => $productType], '', $lang) }}}
                        </a>
                      </td>
                    </tr>
                    <tr>
                      <td height="30" align="center" class="separator">&nbsp;</td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile" align="left" valign="top">
                        <p class="greeting-text">
                          {{ trans('email-expired-payment.body.regards', [], '', $lang) }}
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

      <tr>
        <td align="center" valign="top">
          @include('emails.components.new-basic-footer')
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
