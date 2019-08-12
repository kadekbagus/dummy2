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

                <div style="display: inline-block;margin-left: 10px;text-align: left;max-width: 70%;min-width: 240px;max-width: 320px;width: auto;">
                  <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_name') }}}:</strong> {{ $transaction['items'][0]['name'] }}
                    <br>
                    <strong>{{{ trans('email-receipt.body.transaction_labels.coupon_expired_date') }}}:</strong> {{ $couponExpiredDate }}
                    <br>
                    <div style="width: 100%;">
                      <div style="width: 85%;display: inline-block;">
                        <strong>{{{ trans('email-canceled-payment.body.transaction_labels.coupon_price', [], '', 'id') }}}</strong> {{ $transaction['items'][0]['price'] }}
                      </div>
                      <div style="width: 10%;display: inline-block;">
                        X {{ $transaction['items'][0]['quantity'] }}
                      </div>
                    </div>
                    @if (count($transaction['discounts']) > 0)
                      <br>
                      @foreach($transaction['discounts'] as $discount)
                        <div style="width: 100%;">
                          <div style="width: 85%;display: inline-block;">
                            <strong>{{{ $discount['name'] }}}</strong>: {{ $discount['price'] }}
                          </div>
                        </div>
                      @endforeach
                    @endif
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
