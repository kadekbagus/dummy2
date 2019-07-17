<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
    <meta charset="utf-8"> <!-- utf-8 works for most cases -->
    <meta name="viewport" content="width=device-width"> <!-- Forcing initial-scale shouldn't be necessary -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge"> <!-- Use the latest (edge) version of IE rendering engine -->
    <title>Unable to Get the Pulsa</title>
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
              <span class="text-red invoice-title" style="color:#f43d3c;font-size:28px;font-weight:bold;">{{{ trans('email-coupon-not-available-admin.header.invoice') }}}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="invoice-info" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                {{{ $transactionDateTime }}}
                <br>
                <span style="font-size:24px;font-weight:bold;">{{{ trans('email-coupon-not-available-admin.header.title') }}}</span>
            </td>
          </tr>
          <tr>
            <td colspan="2" class="invoice-body" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:80px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                {{ trans('email-coupon-not-available-admin.body.greeting') }}
              </p>
              <br>
              <table class="no-border customer" width="100%" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                <thead>
                  <tr>
                    <th class="text-left first" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;padding-left:0;">{{{ trans('email-coupon-not-available-admin.table_customer_info.header.transaction_id') }}}</th>
                    <th class="text-left first" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;padding-left:0;">{{{ trans('email-coupon-not-available-admin.table_customer_info.header.customer') }}}</th>
                    <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;">{{{ trans('email-coupon-not-available-admin.table_customer_info.header.email') }}}</th>
                    <th class="text-left" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;">{{{ trans('email-coupon-not-available-admin.table_customer_info.header.phone') }}}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="first" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;padding-left:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transaction['id'] }}}</td>
                    <td class="first" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;padding-left:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerName }}}</td>
                    <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerEmail }}}</td>
                    <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerPhone }}}</td>
                  </tr>
                </tbody>
              </table>
              <br>
              <table class="no-border transaction" width="100%" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                <thead class="bordered">
                  <tr>
                    <th class="text-left first" width="30%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;padding-left:0;">{{{ trans('email-coupon-not-available-admin.table_transaction.header.item') }}}</th>
                    <th class="text-left" width="20%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-coupon-not-available-admin.table_transaction.header.quantity') }}}</th>
                    <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-coupon-not-available-admin.table_transaction.header.price') }}}</th>
                    <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-coupon-not-available-admin.table_transaction.header.subtotal') }}}</th>
                  </tr>
                </thead>
                <tbody class="transaction-items">
                    @foreach($transaction['items'] as $item)
                      <tr class="transaction-item">
                        <td class="first" style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;padding-left:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $item['name'] }}}</td>
                        <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['quantity'] }}}</td>
                        <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['price'] }}}</td>
                        <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['total'] }}}</td>
                      </tr>
                    @endforeach
                </tbody>
                <tfoot class="transaction-footer">
                  <tr>
                    <td colspan="2" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"></td>
                    <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ trans('email-coupon-not-available-admin.table_transaction.footer.total') }}}</strong></td>
                    <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transaction['total'] }}}</td>
                  </tr>
                </tfoot>
              </table>
              <br>
              <br>
              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                {{ trans('email-coupon-not-available-admin.body.help', ['total' => $transaction['total']]) }}
                <br>
                <br>
                @if (isset($reason) && ! empty($reason))
                    {{ trans('email-coupon-not-available-admin.body.pulsa_fail_info', ['reason' => $reason]) }}
                @endif
                <br>
                <br>
              </p>
            </td>
          </tr>
        </tbody>
      </table>
    </body>
</html>
