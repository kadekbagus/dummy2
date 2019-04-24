              @if (! empty($paymentInfo) && isset($paymentInfo['bank_detail']))
                @if (! isset($hideExpiration))
                  <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                    {{ trans('email-pending-payment.body.payment-info-line-1', compact('paymentExpiration'), '', 'id') }}
                  </p>
                  <br>
                @endif
                <table class="no-border customer" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                  <tbody>

                    @if ($paymentInfo['payment_type'] === 'echannel')
                      <p>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bank_name', [], '', 'id') }}}</strong> {{ $paymentInfo['bank_detail']['label'] }}
                        <br>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.biller_code', [], '', 'id') }}}</strong> {{ $paymentInfo['biller_code'] }}
                        <br>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bill_key', [], '', 'id') }}}</strong> {{ $paymentInfo['bill_key'] }}
                        <br>
                      </p>
                    @elseif ($paymentInfo['payment_type'] === 'bank_transfer')
                      <p>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bank_name', [], '', 'id') }}}</strong> {{ $paymentInfo['bank_detail']['label'] }}
                        <br>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bank_code', [], '', 'id') }}}</strong> {{ $paymentInfo['bank_detail']['code'] }}
                        <br>
                        <strong>{{{ trans('email-pending-payment.body.payment-info.bank_account_number', [], '', 'id') }}}</strong> {{ $paymentInfo['va_number'] }}
                        <br>
                      </p>
                    @endif

                  </tbody>
                </table>
                <br>
                <p>
                  {{{ trans('email-pending-payment.body.payment-info-line-2', [], '', 'id') }}}
                </p>

                <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                @if (isset($paymentInfo['pdf_url']) && ! empty($paymentInfo['pdf_url']))
                  <a href="{{{ $paymentInfo['pdf_url'] }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;background-color:#2196F3;color:#fff;font-weight:bold;font-size:16px;display:inline-block;padding:10px 0;text-decoration:none;width:35%;text-align:center;margin-right:5px;margin-top:10px;">{{{ trans('email-pending-payment.body.btn_payment_instruction', [], '', 'id') }}}</a>
                @endif
                  <a href="{{{ $myWalletUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#2196F3;background-color:#fff;color:#2196F3;font-weight:bold;font-size:16px;display:inline-block;text-decoration:none;width:30%;padding:10px 0;margin-right:5px;text-align:center;margin-top:10px;">{{{ trans('email-pending-payment.body.btn_my_wallet', [], '', 'id') }}}</a>
                  <a href="{{{ $cancelUrl }}}" class="btn-cancel" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#666666;background-color:#fff;color:#666666;font-weight:bold;font-size:16px;display:inline-block;padding:10px 0;text-decoration:none;width:30%;text-align:center;margin-top:10px;">
                      {{{ trans('email-pending-payment.body.btn_cancel_purchase', [], '', 'id') }}}
                  </a>
                </p>
                <br>
              @elseif (! empty($paymentInfo))
                <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;text-align: center;">
                @if ($paymentInfo['payment_type'] === 'gopay')
                  <a href="{{{ $myWalletUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#2196F3;background-color:#fff;color:#2196F3;font-weight:bold;font-size:16px;display:inline-block;text-decoration:none;width:30%;padding:10px 0;margin-right:5px;text-align:center;margin-top:10px;">{{{ trans('email-pending-payment.body.btn_my_wallet', [], '', 'id') }}}</a>
                  <a href="{{{ $cancelUrl }}}" class="btn-cancel" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#666666;background-color:#fff;color:#666666;font-weight:bold;font-size:16px;display:inline-block;padding:10px 0;text-decoration:none;width:30%;text-align:center;margin-top:10px;">
                      {{{ trans('email-pending-payment.body.btn_cancel_purchase', [], '', 'id') }}}
                  </a>
                @endif
                </p>
              @endif
