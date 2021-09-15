@extends('emails.layouts.default')

@section('title')
Free Game Voucher from Gotomalls.com!
@stop

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
                        {{ trans('email-purchase-rewards.free_game_voucher.title', [], '', $lang) }}
                      </h1>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                  <td width="600" class="mobile" align="left" valign="top">
                    <h3 class="greeting-username">
                      {{ trans('email-purchase-rewards.free_game_voucher.greeting', ['customerName' => $customerName], '', $lang) }}
                    </h3>
                    <p class="greeting-text" style="line-height: 1.75em;">
                      {{ trans('email-purchase-rewards.free_game_voucher.line_1', ['providerProductName' => $productName], '', $lang) }}

                      <br>

                      {{ trans('email-purchase-rewards.free_game_voucher.line_2', [], '', $lang) }}

                    </p>
                  </td>
                </tr>

                <tr>
                  <td width="600" class="mobile center" valign="middle">
                    <table width="100%" class="greeting-text">
                      <tbody>
                        <tr>
                          <td class="mobile w-25 bold reservation-table-item-label border-none">
                            <span class="p-8 block">
                                {{ trans('email-purchase-rewards.free_game_voucher.labels.transaction_id', [], '', $lang) }}
                            </span>
                          </td>
                          <td class="mobile reservation-table-item-value border-none">
                            <span class="p-8 block">
                              <span class="mobileOff">: &nbsp;&nbsp;</span>
                              {{ $transactionId }}
                            </span>
                          </td>
                        </tr>
                        <tr>
                          <td class="mobile bold reservation-table-item-label border-none">
                            <span class="p-8 block">
                                {{ trans('email-purchase-rewards.free_game_voucher.labels.transaction_datetime', [], '', $lang) }}
                            </span>
                          </td>
                          <td class="mobile reservation-table-item-value border-none">
                            <span class="p-8 block">
                              <span class="mobileOff">: &nbsp;&nbsp;</span>
                              {{ $transactionDateTime }}
                            </span>
                          </td>
                        </tr>
                        <tr>
                          <td class="mobile bold reservation-table-item-label border-none">
                            <span class="p-8 block">
                                {{ trans('email-purchase-rewards.free_game_voucher.labels.pin', [], '', $lang) }}
                            </span>
                          </td>
                          <td class="mobile reservation-table-item-value border-none">
                            <span class="p-8 block">
                                <span class="mobileOff">: &nbsp;&nbsp;</span>
                                {{ $voucher['pin'] }}
                            </span>
                          </td>
                        </tr>
                        <tr>
                          <td class="mobile bold reservation-table-item-label border-none">
                            <span class="p-8 block">
                                {{ trans('email-purchase-rewards.free_game_voucher.labels.serial_number', [], '', $lang) }}
                            </span>
                          </td>
                          <td class="mobile reservation-table-item-value border-none">
                            <span class="p-8 block">
                              <span class="mobileOff">: &nbsp;&nbsp;</span>
                              {{ $voucher['serialNumber'] }}
                            </span>
                          </td>
                        </tr>

                      </tbody>
                    </table>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                  <td width="600" class="mobile center" valign="middle" style="text-align: center;">
                    <table width="100%">
                      <tr>
                        <td colspan="2" class="greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:10px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                            <p class="help-text" style="line-height: 1.75em;">
                                {{ trans('email-purchase-rewards.free_game_voucher.line_3', [], '', $lang) }}
                                <br>
                                {{ trans('email-purchase-rewards.free_game_voucher.line_4', [], '', $lang) }}
                                &nbsp;<strong>{{ $voucher['startDate'] }} - {{ $voucher['endDate'] }}</strong>
                                <br>
                                <br>
                                {{{ trans('email-purchase-rewards.free_game_voucher.thank_you', [], '', $lang) }}}

{{--                                 <br>
                                <br> --}}
                                {{-- {{ trans('email-purchase-rewards.free_game_voucher.help', ['csPhone' => $cs['phone'], 'csEmail' => $cs['email']], '', $lang) }} --}}
                            </p>
                        </td>
                      </tr>
                    </table>
                  </td>
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
