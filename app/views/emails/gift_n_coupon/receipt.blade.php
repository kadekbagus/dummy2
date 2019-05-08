<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8"> <!-- utf-8 works for most cases -->
    <meta name="viewport" content="width=device-width"> <!-- Forcing initial-scale shouldn't be necessary -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge"> <!-- Use the latest (edge) version of IE rendering engine -->
    <title>Receipt from Gotomalls.com</title>
    <!-- Web Font / @font-face : BEGIN -->
    <!-- NOTE: If web fonts are not required, lines 10 - 27 can be safely removed. -->
    <!-- All other clients get the webfont reference; some will render the font and others will silently fail to the fallbacks. More on that here: http://stylecampaign.com/blog/2015/02/webfont-support-in-email/ -->
    <!--[if !mso]><!-->
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
        <!--<![endif]-->
      <!-- Web Font / @font-face : END -->
    <style type="text/css">
        /* What it does: Stops Outlook from adding extra spacing to tables. */
        body, th, td, p, a {
            font-family: 'Roboto', 'Arial', sans-serif;
        }

        p {
            margin: 0;
        }

        body {
            text-align: left;
        }

        table.email-container {

            max-width: 640px;
        }

        table {
            line-height: 1.7em;
            font-size: 14px;
            color: #222;
            width: 100%;
            border-spacing: 0 !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin: 0 auto !important;
        }

        table.no-border  {
            border: 0;
        }

        /* What it does: Stops Outlook from adding extra spacing to tables. */
        table,
        td {
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }

        /* What it does: Uses a better rendering method when resizing images in IE. */
        img {
            -ms-interpolation-mode:bicubic;
        }

        .text-red { color: #f43d3c; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }

        thead tr.invoice-header th {
            padding-bottom: 30px;
        }

        img.logo {
            width: 80%;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
        }

        .invoice-info {
            padding-top: 20px;
            padding-bottom: 20px;
        }

        .invoice-body {
            padding-top: 10px;
            padding-bottom: 80px;
        }

        table.customer {
            margin-top: 30px;
        }

        table.customer tr th {
            padding-left: 15px;
        }

        table.customer tr td {
            padding: 15px;
        }

        table.customer tr th.first,
        table.customer tr td.first {
            padding-left: 0;
        }

        table.transaction {
            margin-top: 30px;
        }

        table.transaction tr th {
            padding: 8px;
        }

        table.transaction tr td {
            padding: 15px 8px;
        }

        table.transaction tr th.first,
        table.transaction tr td.first {
            padding-left: 0;
        }

        tr.transaction-item td {
            border-bottom: 1px solid #999;
            vertical-align: top;
        }
        tr.transaction-item:last-child td {
            border-bottom: 0;
        }

        thead.bordered tr th {
            border-top: 1px solid #999;
            border-bottom: 1px solid #999;
        }

        tfoot.transaction-footer tr td {
            border-top: 1px solid #999;
        }

        .btn-redeem {
            border-radius: 5px;
            background-color: #f43d3c;
            color: #fff;
            font-weight: bold;
            font-size: 16px;
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
        }
    </style>
  </head>
  <body style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;">
      <table border="0" cellpadding="0" cellspacing="0" class="no-border email-container" style="line-height:1.7em;font-size:14px;color:#222;width:100%;max-width:640px;border:0;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
        <thead>
          <tr class="invoice-header">
            <th width="50%" class="text-left" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-bottom:30px;">
              <img src="https://s3-ap-southeast-1.amazonaws.com/asset1.gotomalls.com/themes/default/images/logo-en.png?t=1523326836" alt="Logo" class="logo" style="-ms-interpolation-mode:bicubic;width:80%;">
            </th>
            <th class="text-right" style="font-family:'Roboto', 'Arial', sans-serif;text-align:right;padding-bottom:30px;">
              <span class="text-red invoice-title" style="color:#f43d3c;font-size:28px;font-weight:bold;">{{{ trans('email-receipt.header.invoice', [], '', 'id') }}}</span>
            </th>
          </tr>
        </thead>

        <tbody>
          <tr>
            <td class="text-left invoice-info" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ trans('email-receipt.header.order_number', ['transactionId' => $transaction['id']], '', 'id') }}}</strong></td>
            <td class="text-right invoice-info" style="font-family:'Roboto', 'Arial', sans-serif;text-align:right;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transactionDateTime }}}</td>
          </tr>
          <tr>
            <td colspan="2" class="invoice-body" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                {{ trans('email-receipt.body.greeting_giftncoupon', ['customerName' => $customerName, 'itemName' => $transaction['items'][0]['name']], '', 'id') }}
              </p>
              <br>

              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                <strong>{{{ trans('email-receipt.body.transaction_labels.transaction_id', [], '', 'id') }}}:</strong> {{ $transaction['id'] }}
                <br>
                <strong>{{{ trans('email-receipt.body.transaction_labels.transaction_date', [], '', 'id') }}}:</strong> {{ $transactionDateTime }}
                <br>
                <strong>{{{ trans('email-receipt.body.transaction_labels.customer_name', [], '', 'id') }}}:</strong> {{ $customerName }}
                <br>
                <strong>{{{ trans('email-receipt.body.transaction_labels.phone', [], '', 'id') }}}:</strong> {{ $customerPhone }}
                <br>
                <strong>{{{ trans('email-receipt.body.transaction_labels.email', [], '', 'id') }}}:</strong> {{ $customerEmail }}
                <br>
                <br>
              </p>

              <div style="border: 1px solid #bbb; padding: 15px 10px; text-align: center;border-radius: 5px;">
                <div style="display: inline-block; width: auto; height: auto; max-width: 120px; max-height: 120px;vertical-align: top;">
                  <img src="{{{ $couponImage }}}" alt="coupon_image" style="width: 100%; height: 100%;">
                </div>

                <div style="display: inline-block;margin-left: 10px;text-align: left;">
                  <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_name', [], '', 'id') }}}:</strong> {{ $transaction['items'][0]['name'] }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_expired_date') }}}:</strong> {{ $couponExpiredDate }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_price', [], '', 'id') }}}:</strong> {{ $transaction['items'][0]['price'] }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_quantity', [], '', 'id') }}}:</strong> {{ $transaction['items'][0]['quantity'] }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.total_amount', [], '', 'id') }}}:</strong> {{ $transaction['items'][0]['total'] }}
                    <br>
                    <br>
                  </p>
                </div>
              </div>

              <br>

              @if (count($redeemUrls) > 0)
                <div style="border: 1px solid #bbb; padding: 15px 10px;text-align: center;border-radius: 5px;">
                  <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                    {{{ trans('email-receipt.body.redeem_giftncoupon', [], '', 'id') }}}
                    <br>
                    @foreach($redeemUrls as $url)
                      <a style="color:#f43d3c;text-decoration:none;font-size: 18px;" href="{{{ $url }}}">{{ $url }}</a>
                      <br>
                    @endforeach
                  </p>
                </div>
              @endif

              <br>
              <p class="text-center" style="font-family:'Roboto', 'Arial', sans-serif;margin:0;text-align:center;">
                <a href="{{{ $myPurchasesUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#2196F3;background-color:#fff;color:#2196F3;font-weight:bold;font-size:16px;display:inline-block;text-decoration:none;width:30%;padding:10px 0;margin-right:5px;text-align:center;margin-top:10px;">{{{ trans('email-receipt.buttons.my_purchases', [], '', 'id') }}}</a>
              </p>
              <br>

              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                {{ trans('email-receipt.body.help', ['csPhone' => $cs['phone'], 'csEmail' => $cs['email']], '', 'id') }}
              </p>
            </td>
          </tr>

          <tr>
            <td colspan="2">
              <hr>
            </td>
          </tr>

          <tr>
            <td class="text-left invoice-info" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ trans('email-receipt.header.order_number', ['transactionId' => $transaction['id']]) }}}</strong></td>
            <td class="text-right invoice-info" style="font-family:'Roboto', 'Arial', sans-serif;text-align:right;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transactionDateTime }}}</td>
          </tr>
          <tr>
            <td colspan="2" class="invoice-body" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:80px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                {{ trans('email-receipt.body.greeting_giftncoupon', ['customerName' => $customerName, 'itemName' => $transaction['items'][0]['name']]) }}
              </p>
              <br>

              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                <strong>{{{ trans('email-receipt.body.transaction_labels.transaction_id') }}}:</strong> {{ $transaction['id'] }}
                <br>
                <strong>{{{ trans('email-receipt.body.transaction_labels.transaction_date') }}}:</strong> {{ $transactionDateTime }}
                <br>
                <strong>{{{ trans('email-receipt.body.transaction_labels.customer_name') }}}:</strong> {{ $customerName }}
                <br>
                <strong>{{{ trans('email-receipt.body.transaction_labels.phone') }}}:</strong> {{ $customerPhone }}
                <br>
                <strong>{{{ trans('email-receipt.body.transaction_labels.email') }}}:</strong> {{ $customerEmail }}
                <br>
                <br>
              </p>

              <div style="border: 1px solid #bbb; padding: 15px 10px; text-align: center;border-radius: 5px;">
                <div style="display: inline-block; width: auto; height: auto; max-width: 120px; max-height: 120px;vertical-align: top;">
                  <img src="{{{ $couponImage }}}" alt="coupon_image" style="width: 100%; height: 100%;">
                </div>

                <div style="display: inline-block;margin-left: 10px;text-align: left;max-width: 70%;">
                  <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_name') }}}:</strong> {{ $transaction['items'][0]['name'] }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_expired_date') }}}:</strong> {{ $couponExpiredDate }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_price') }}}:</strong> {{ $transaction['items'][0]['price'] }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_quantity') }}}:</strong> {{ $transaction['items'][0]['quantity'] }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.total_amount') }}}:</strong> {{ $transaction['items'][0]['total'] }}
                    <br>
                    <br>
                  </p>
                </div>
              </div>

              <br>

              @if (count($redeemUrls) > 0)
                <div style="border: 1px solid #bbb; padding: 15px 10px;text-align: center;border-radius: 5px;">
                  <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                    {{{ trans('email-receipt.body.redeem_giftncoupon') }}}
                    <br>
                    @foreach($redeemUrls as $url)
                      <a style="color:#f43d3c;text-decoration:none;font-size: 18px;" href="{{{ $url }}}">{{ $url }}</a>
                      <br>
                    @endforeach
                  </p>
                </div>
              @endif

              <br>
              <p class="text-center" style="font-family:'Roboto', 'Arial', sans-serif;margin:0;text-align:center;">
                <a href="{{{ $myPurchasesUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#2196F3;background-color:#fff;color:#2196F3;font-weight:bold;font-size:16px;display:inline-block;text-decoration:none;width:30%;padding:10px;margin-right:5px;text-align:center;margin-top:10px;">{{{ trans('email-receipt.buttons.my_purchases') }}}</a>
              </p>
              <br>

              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                {{ trans('email-receipt.body.help', ['csPhone' => $cs['phone'], 'csEmail' => $cs['email']]) }}
              </p>
            </td>
          </tr>
        </tbody>

        <tfoot>
          <tr>
            <td colspan="2" class="text-left footer" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
              <small>
                @include('emails.components.basic-footer', $cs)
              </small>
            </td>
          </tr>
        </tfoot>
    </table>
  </body>
</html>
