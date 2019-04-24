
                <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                  <a href="{{{ $myWalletUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#2196F3;background-color:#fff;color:#2196F3;font-weight:bold;font-size:16px;display:inline-block;text-decoration:none;width:30%;padding:10px 0;margin-right:5px;text-align:center;margin-top:10px;">{{{ trans('email-pending-payment.body.btn_my_wallet', [], '', 'id') }}}</a>
                  <a href="{{{ $cancelUrl }}}" class="btn-cancel" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#666666;background-color:#fff;color:#666666;font-weight:bold;font-size:16px;display:inline-block;padding:10px 0;text-decoration:none;width:30%;text-align:center;margin-top:10px;">
                      {{{ trans('email-pending-payment.body.btn_cancel_purchase', [], '', 'id') }}}
                  </a>
                </p>
                <br>
