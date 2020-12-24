<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{{ trans('email-reservation.made.subject') }}</title>

  @include('emails.components.styles')

</head>
<body style="margin:0; padding:0; background-color:#F2F2F2;">

  <span style="display: block; width: 640px !important; max-width: 640px; height: 1px" class="mobileOff"></span>

  <center>
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#F2F2F2">
      <tr>
        <td align="center" valign="middle">
          <img src="https://s3-ap-southeast-1.amazonaws.com/asset1.gotomalls.com/uploads/emails/gtm-logo.png" class="logo" alt="Logo">
        </td>
      </tr>

      @foreach($langs as $lang)
        <tr>
          <td align="center" valign="top">

            <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF" style="background-color: transparent;">
              <tr>
                <td align="center" valign="middle" style="box-shadow: 0 0 20px #e0e0e0; border-radius:5px;background-color: #FFF;">

                  <table width="640" cellpadding="0" cellspacing="0" border="0" class="container mobile-full-width">
                    <tr>
                      <td align="center" valign="middle" height="184" class="greeting-title-container" style="border-radius: 5px 5px 0 0;">
                          <h1 class="greeting-title">{{ trans('email-reservation.made.title', [], '', $lang) }}</h1>
                      </td>
                    </tr>
                  </table>

                  <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                    <tr>
                      <td width="300" class="mobile" align="left" valign="top">
                        <h3 class="greeting-username">
                          {{ trans('email-reservation.made.greeting', ['recipientName' => $recipientName], '', $lang) }}</h3>
                        <p class="greeting-text">
                          {{ trans('email-reservation.made.body.line-1', $store, '', $lang) }}
                        </p>
                      </td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile greeting-text" valign="middle">
                        <table width="100%">
                          <thead>
                            <tr>
                              <th colspan="2" class="uppercase reservation-table-title">
                                {{ trans('email-reservation.labels.reservation_details', [], '', $lang) }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.transaction_id', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $reservationId }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.user_email', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $customerEmail }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.store_location', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ trans('email-reservation.labels.store_location_detail', $store, '', $lang) }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.reserve_date', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $reservationTime }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.expiration_date', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $expirationTime }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.quantity', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $quantity }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.total_payment', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $totalPayment }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.status', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block text-{{ $statusColor[$status] }}">
                                  {{ trans('email-reservation.labels.status_detail.' . $status, [], '', $lang) }}
                                </span>
                              </td>
                            </tr>
                          </tbody>
                        </table>

                        <br>

                        <table width="100%">
                          <thead>
                            <tr>
                              <th colspan="2" class="uppercase reservation-table-title">
                                {{ trans('email-reservation.labels.product_details', [], '', $lang) }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.product_name', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $product['name'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.product_variant', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $product['variant'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.product_sku', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $product['sku'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-reservation.labels.product_barcode', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $product['barcode'] }}</span>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    <tr>
                      <td height="30" align="center" class="separator">&nbsp;</td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile" align="left" valign="top">
                        <p class="greeting-text">
                          {{ trans('email-reservation.made.body.line-2', [], '', $lang) }}
                        </p>
                      </td>
                    </tr>
                    <tr>
                      <td height="30" align="center" class="separator">&nbsp;</td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile" align="center" valign="middle">
                        <a href="{{{ $declineUrl }}}" class="btn btn-light mx-4">
                          {{{ trans('email-reservation.labels.btn_decline', [], '', $lang) }}}
                        </a>
                        <a href="{{{ $acceptUrl }}}" class="btn btn-primary mx-4">
                            {{{ trans('email-reservation.labels.btn_accept', [], '', $lang) }}}
                          </a>
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

      <tr>
        <td align="center" valign="top">
          @include('emails.components.new-basic-footer')
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
