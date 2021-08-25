<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{{ trans('email-order.pickup-order.subject') }}</title>

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

      @foreach($supportedLangs as $lang)
        <tr>
          <td align="center" valign="top">

            <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF" style="background-color: transparent;">
              <tr>
                <td align="center" valign="middle" style="box-shadow: 0 0 20px #e0e0e0; border-radius:5px;background-color: #FFF;">

                  <table width="640" cellpadding="0" cellspacing="0" border="0" class="container mobile-full-width">
                    <tr>
                      <td align="center" valign="middle" height="184" class="greeting-title-container" style="border-radius: 5px 5px 0 0;">
                          <h1 class="greeting-title">{{ trans('email-order.pickup-order.title', [], '', $lang) }}</h1>
                      </td>
                    </tr>
                  </table>

                  <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                    <tr>
                      <td width="300" class="mobile" align="left" valign="top">
                        <h3 class="greeting-username">
                          {{ trans('email-order.pickup-order.greeting', ['recipientName' => $recipientName], '', $lang) }}</h3>
                        <p class="greeting-text">
                        @if ($type === 'user')
                          {{ trans('email-order.pickup-order.body.line-user', [], '', $lang) }}
                        @else
                          {{ trans('email-order.pickup-order.body.line-admin', [], '', $lang) }}
                        @endif
                        </p>

                        @if ($type === 'user')
                        <div align="center">
                          <h1 style="color: #FF0000;">{{ $pickUpCode }}</h1>
                        </div>
                        @endif

                      </td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile greeting-text" valign="middle">
                        <table width="100%">
                          <thead>
                            <tr>
                              <th colspan="2" class="uppercase reservation-table-title">
                                {{ trans('email-order.labels.order_details', [], '', $lang) }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-order.labels.order_id', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transaction['orderId'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-order.labels.order_date', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transactionDateTime }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-order.labels.customer_name', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $customer['name'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-order.labels.customer_email', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $customer['email'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-order.labels.customer_phone', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $customer['phone'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-order.labels.store_location', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">
                                  {{ $store }}
                                </span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-order.labels.total_payment', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transaction['total'] }}</span>
                              </td>
                            </tr>
                          </tbody>
                        </table>

                        <br>

                        <table width="100%">
                          <thead>
                            <tr>
                              <th colspan="3" class="uppercase reservation-table-title">
                                {{ trans('email-order.labels.product_details', [], '', $lang) }}
                              </th>
                            </tr>
                            <tr>
                              <th class="reservation-table-item-label text-left product-details-subtitle" width="50%">
                                {{ trans('email-order.labels.product_name', [], '', $lang) }}
                              </th>
                              {{-- <th>{{ trans('email-order.labels.product_variant', [], '', $lang) }}</th> --}}
                              {{-- <th>{{ trans('email-order.labels.product_sku', [], '', $lang) }}</th> --}}
                              <th class="reservation-table-item-label product-details-subtitle">
                                {{ trans('email-order.labels.quantity', [], '', $lang) }}
                              </th>
                              <th class="reservation-table-item-label product-details-subtitle" style="border-right: 1px solid #ddd;">
                                {{ trans('email-order.labels.product_price', [], '', $lang) }}
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            @foreach($transaction['items'] as $item)
                              <tr>
                                <td class="reservation-table-item-value" style="border-left: 1px solid #ddd;">
                                  <span class="p-8 block">
                                    {{ $item['name'] }}
                                    <br>
                                    <em>
                                      <small>
                                        {{ trans('email-order.labels.product_variant', [], '', $lang) }}:
                                        {{ $item['variant'] }} {{-- $item['sku'] --}}
                                      </small>
                                    </em>
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block text-center">{{ $item['quantity'] }}</span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block text-center">{{ $item['total'] }}</span>
                                </td>
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    <tr>
                      <td height="30" align="center" class="separator">&nbsp;</td>
                    </tr>
                    <tr>
                      <td width="600" class="mobile reservation-actions" align="center" valign="middle">
                        <a href="{{{ $transaction['followUpUrl'] }}}" class="btn btn-primary mx-4">
                        @if ($type === 'user')
                          {{{ trans('email-order.pickup-order.user', [], '', $lang) }}}
                        @else
                          {{{ trans('email-order.pickup-order.admin', [], '', $lang) }}}
                        @endif
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
