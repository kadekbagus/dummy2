              @if (! empty($paymentInfo))
                <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                  {{{ trans('email-pending-payment.body.payment-info-line-1') }}}
                </p>

                <br>

                <table class="no-border customer" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                  <tbody>
                    @if ($paymentInfo['payment_type'] === 'echannel')

                      <tr>
                        <td style="text-align: left; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{ trans('email-pending-payment.body.payment-info.payment-method') }}</td>
                        <td style="text-align: right; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{ trans('email-pending-payment.body.payment-info.payment-method-echannel', ['bank' => $paymentInfo['bank_detail']['label']]) }}</strong></td>
                      </tr>

                      <tr>
                        <td style="text-align: left; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{ trans('email-pending-payment.body.payment-info.biller_code') }}</td>
                        <td style="text-align: right; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{ $paymentInfo['biller_code'] }}</strong></td>
                      </tr>
                      <tr>
                        <td style="text-align: left; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{ trans('email-pending-payment.body.payment-info.bill_key') }}</td>
                        <td style="text-align: right; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ $paymentInfo['bill_key'] }}}</strong></td>
                      </tr>

                    @elseif ($paymentInfo['payment_type'] === 'bank_transfer')

                      <tr>
                        <td style="text-align: left; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{ trans('email-pending-payment.body.payment-info.payment-method') }}</td>
                        <td style="text-align: right; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{ trans('email-pending-payment.body.payment-info.payment-method-bank-transfer', ['bank' => $paymentInfo['bank_detail']['label']]) }}</strong></td>
                      </tr>
                      <tr>
                        <td style="text-align: left; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{ trans('email-pending-payment.body.payment-info.bank_code') }}</td>
                        <td style="text-align: right; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{ $paymentInfo['bank_detail']['code'] }}</strong></td>
                      </tr>
                      <tr>
                        <td style="text-align: left; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{ trans('email-pending-payment.body.payment-info.bank_account_number') }}</td>
                        <td style="text-align: right; font-family:'Roboto', 'Arial', sans-serif;padding:5px 0px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ $paymentInfo['va_number'] }}}</strong></td>
                      </tr>

                    @endif
                  </tbody>
                </table>

                @if (isset($paymentInfo['pdf_url']) && ! empty($paymentInfo['pdf_url']))
                  <br>
                  <p class="text-center" style="font-family:'Roboto', 'Arial', sans-serif;margin:0;text-align:center;">
                    <a href="{{{ $paymentInfo['pdf_url'] }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;background-color:#f43d3c;color:#fff;font-weight:bold;font-size:16px;display:inline-block;padding:10px 20px;text-decoration:none;">{{{ trans('email-pending-payment.body.btn_payment_instruction') }}}</a>
                  </p>
                @endif
                <br>
              @endif
