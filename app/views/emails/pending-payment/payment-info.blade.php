              @if (! empty($paymentInfo) && isset($paymentInfo['bank_detail']))
                <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                  {{ trans('email-pending-payment.body.payment-info-line-1', compact('paymentExpiration')) }}
                </p>
                <br>
                <table class="no-border customer" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                  <tbody>

                    @if ($paymentInfo['payment_type'] === 'echannel')
                      <p>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bank_name') }}}</strong> {{ $paymentInfo['bank_detail']['label'] }}
                        <br>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.biller_code') }}}</strong> {{ $paymentInfo['biller_code'] }}
                        <br>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bill_key') }}}</strong> {{ $paymentInfo['bill_key'] }}
                        <br>
                      </p>
                    @elseif ($paymentInfo['payment_type'] === 'bank_transfer')
                      <p>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bank_name') }}}</strong> {{ $paymentInfo['bank_detail']['label'] }}
                        <br>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bank_code') }}}</strong> {{ $paymentInfo['bank_detail']['code'] }}
                        <br>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bank_account_number') }}}</strong> {{ $paymentInfo['va_number'] }}
                        <br>
                      </p>
                    @endif

                  </tbody>
                </table>
                <br>
                <p>
                  {{{ trans('email-pending-payment.body.payment-info-line-2') }}}
                </p>

                @if (isset($paymentInfo['pdf_url']) && ! empty($paymentInfo['pdf_url']))
                  <br>
                  <p class="text-center" style="font-family:'Roboto', 'Arial', sans-serif;margin:0;text-align:center;">
                    <a href="{{{ $paymentInfo['pdf_url'] }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;background-color:#f43d3c;color:#fff;font-weight:bold;font-size:16px;display:inline-block;padding:10px 20px;text-decoration:none;">{{{ trans('email-pending-payment.body.btn_payment_instruction') }}}</a>
                    &nbsp;&nbsp;
                    <a href="{{{ $myWalletUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#f43d3c;background-color:#fff;color:#f43d3c;font-weight:bold;font-size:16px;display:inline-block;padding:10px 20px;text-decoration:none;">{{{ trans('email-pending-payment.body.btn_my_wallet') }}}</a>
                  </p>
                @endif

                <br>
              @endif
