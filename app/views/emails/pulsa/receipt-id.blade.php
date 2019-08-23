          <tr>
            <td class="text-left invoice-info" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ trans('email-receipt.header.order_number', ['transactionId' => $transaction['id']], '', 'id') }}}</strong></td>
            <td class="text-right invoice-info" style="font-family:'Roboto', 'Arial', sans-serif;text-align:right;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transactionDateTime }}}</td>
          </tr>
          <tr>
            <td colspan="2" class="invoice-body" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                {{ trans('email-receipt.body.greeting', ['customerName' => $customerName, 'itemName' => $transaction['items'][0]['name']], '', 'id') }}
              </p>
              <br>
              <table class="no-border customer" width="100%" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                <thead>
                  <tr>
                    <th class="text-left first" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;padding-left:0;">{{{ trans('email-receipt.table_customer_info.header.customer', [], '', 'id') }}}</th>
                    <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;">{{{ trans('email-receipt.table_customer_info.header.email', [], '', 'id') }}}</th>
                    <th class="text-left" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;">{{{ trans('email-receipt.table_customer_info.header.phone', [], '', 'id') }}}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="first" valign="top" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;padding-left:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerName }}}</td>
                    <td  valign="top" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerEmail }}}</td>
                    <td  valign="top" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerPhone }}}</td>
                  </tr>
                </tbody>
              </table>
              <br>
              <table class="no-border transaction" width="100%" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                <thead class="bordered">
                  <tr>
                    <th class="text-left first" width="30%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;padding-left:0;">{{{ trans('email-receipt.table_transaction.header.item', [], '', 'id') }}}</th>
                    <th class="text-left" width="20%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-receipt.table_transaction.header.quantity', [], '', 'id') }}}</th>
                    <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-receipt.table_transaction.header.price', [], '', 'id') }}}</th>
                    <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-receipt.table_transaction.header.subtotal', [], '', 'id') }}}</th>
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
                    @foreach($transaction['discounts'] as $item)
                      <tr class="transaction-item">
                        <td class="first" style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;padding-left:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ trans('label.discount', [], '', 'id') }}} {{{ $item['name'] }}}</td>
                        <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['quantity'] }}}</td>
                        <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['price'] }}}</td>
                        <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['total'] }}}</td>
                      </tr>
                    @endforeach
                </tbody>
                <tfoot class="transaction-footer">
                  <tr>
                    <td colspan="2" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"></td>
                    <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ trans('email-receipt.table_transaction.footer.total', [], '', 'id') }}}</strong></td>
                    <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transaction['total'] }}}</td>
                  </tr>
                </tfoot>
              </table>
              <br>
              <br>
              <p class="text-center" style="font-family:'Roboto', 'Arial', sans-serif;margin:0;text-align:center;">
                <a href="{{{ $myWalletUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;background-color:#f43d3c;color:#fff;font-weight:bold;font-size:16px;display:inline-block;padding:10px 20px;text-decoration:none;">
                  {{{ trans('email-receipt.buttons.my_purchases', [], '', 'id') }}}
                </a>
              </p>
              <br>
              <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                {{ trans('email-receipt.body.help', ['csPhone' => $cs['phone'], 'csEmail' => $cs['email']], '', 'id') }}
                <br>
                <br>
                {{{ trans('email-receipt.body.thank_you', [], '', 'id') }}}
              </p>
            </td>
          </tr>
