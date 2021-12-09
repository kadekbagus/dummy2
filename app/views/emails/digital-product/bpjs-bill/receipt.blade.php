@extends('emails.layouts.default')

@section('content')
  @foreach($supportedLangs as $lang)
    <tr>
      <td align="center" valign="top">

        <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF" style="background-color: transparent;">
          <tr>
            <td align="center" valign="middle" style="box-shadow: 0 0 20px #e0e0e0; border-radius:5px;background-color: #FFF;">

              <table width="640" cellpadding="0" cellspacing="0" border="0" class="container mobile-full-width">
                <tr>
                  <td align="center" valign="middle" height="184" class="greeting-title-container" style="border-radius: 5px 5px 0 0;">
                      <h1 class="greeting-title">
                        {{ trans('email-receipt.header.invoice', [], '', $lang) }}
                      </h1>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                  <td width="300" class="mobile" align="left" valign="top">
                    <h3 class="greeting-username">
                      {{ trans('email-receipt.body.greeting_products.customer_name', ['customerName' => $customerName], '', $lang) }}
                    </h3>
                    <p class="greeting-text">
                      {{ trans('email-receipt.body.greeting_products.body_1', [
                            'itemName' => $transaction['items'][0]['name'] ?: '',
                          ], '', $lang
                         )
                      }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td width="600" class="mobile greeting-text" valign="middle">

                    @include('emails.digital-product.bill-customer')

                    <br>

                    @include('emails.digital-product.bpjs-bill.bill-information')

                  </td>
                </tr>
                <tr>
                  <td width="600" class="mobile" align="left" valign="top">
                    <p class="greeting-text">
                      {{ trans('email-receipt.body.view_my_purchases', [], '', $lang) }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td align="center" class="separator">&nbsp;</td>
                </tr>
                <tr>
                  <td width="600" class="mobile reservation-actions" align="center" valign="middle">
                    <a href="{{{ $myWalletUrl }}}" class="btn btn-primary mx-4">
                      {{{ trans('email-receipt.buttons.my_purchases', [], '', $lang) }}}
                    </a>
                  </td>
                </tr>
                <tr>
                  <td height="15" align="center" class="separator">&nbsp;</td>
                </tr>
                <tr>
                  <td width="600" class="mobile" align="left" valign="top">
                    <p class="help-text">
                          {{ trans('email-receipt.body.help', ['csPhone' => $cs['phone'], 'csEmail' => $cs['email']], '', $lang) }}
                          <br>
                          <br>
                          {{{ trans('email-receipt.body.thank_you', [], '', $lang) }}}
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
@stop
