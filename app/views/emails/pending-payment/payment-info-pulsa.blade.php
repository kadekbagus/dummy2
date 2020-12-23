
                <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;" class="text-center">
                  {{-- <a href="{{{ $payUrl }}}" class="btn-redeem" style="font-family:'Roboto', 'Arial', sans-serif;border-radius:5px;border-width:1px;border-style:solid;border-color:#2196F3;background-color:#fff;color:#2196F3;font-weight:bold;font-size:16px;display:inline-block;text-decoration:none;width:30%;padding:10px 0;margin-right:5px;text-align:center;margin-top:10px;">{{{ trans('email-pending-payment.body.btn_pay', [], '', 'id') }}}</a> --}}
                  <a href="{{{ $myWalletUrl }}}" class="btn btn-redeem mx-4" style="">
                    {{{ trans('email-pending-payment.body.btn_my_wallet', [], '', 'id') }}}
                  </a>
                  <a href="{{{ $cancelUrl }}}" class="btn btn-cancel btn-light mx-4" style="">
                    {{{ trans('email-pending-payment.body.btn_cancel_purchase', [], '', 'id') }}}
                  </a>
                </p>
                <br>
