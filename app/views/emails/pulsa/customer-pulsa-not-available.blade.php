@extends('emails.layouts.default')

@section('title')
Unable to Get Pulsa for You
@stop

@section('content')
  <?php
    $langs = ['id', 'en'];
    $originalProductType = isset($productType) ? $productType : 'default';
  ?>

  @foreach($langs as $lang)
    <?php $productType = trans("email-payment.product_type.{$originalProductType}", [], '', $lang); ?>
    <tr>
      <td align="center" valign="top">

        <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF" style="background-color: transparent;">
          <tr>
            <td align="center" valign="middle" style="box-shadow: 0 0 20px #e0e0e0; border-radius:5px;background-color: #FFF;">

              <table width="640" cellpadding="0" cellspacing="0" border="0" class="container mobile-full-width">
                <tr>
                  <td align="center" valign="middle" height="184" class="greeting-title-container" style="border-radius: 5px 5px 0 0;">
                      <h1 class="greeting-title">
                        {{ trans('email-coupon-not-available.header.email-type', [], '', $lang) }}
                      </h1>
                  </td>
                </tr>
              </table>

              <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                <tr>
                    <td height="10" align="center">&nbsp;</td>
                </tr>
                <tr>
                  <td>
                    <table border="0" cellpadding="0" cellspacing="0" class="no-border email-container" style="line-height:1.7em;color:#222;width:100%;max-width:640px;border:0;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                      <tbody>
                        <tr>
                          <td class="text-left invoice-info greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ trans('email-coupon-not-available.header.order_number', ['transactionId' => $transaction['id']], '', $lang) }}}</strong></td>
                          <td class="text-right invoice-info greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;text-align:right;padding-top:20px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transactionDateTime }}}</td>
                        </tr>
                        <tr>
                          <td colspan="2" class="greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:20px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                            <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                              {{ trans('email-coupon-not-available.body.greeting_pulsa', ['customerName' => $customerName], '', $lang) }}
                            </p>
                            <br>
                            <table class="no-border customer" width="100%" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                              <thead>
                                <tr>
                                  <th class="text-left first" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;padding-left:0;">{{{ trans('email-coupon-not-available.table_customer_info.header.customer', [], '', $lang) }}}</th>
                                  <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;">{{{ trans('email-coupon-not-available.table_customer_info.header.email', [], '', $lang) }}}</th>
                                  <th class="text-left" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding-left:15px;">{{{ trans('email-coupon-not-available.table_customer_info.header.phone', [], '', $lang) }}}</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr>
                                  <td class="first" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;padding-left:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerName }}}</td>
                                  <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerEmail }}}</td>
                                  <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $customerPhone }}}</td>
                                </tr>
                              </tbody>
                            </table>
                            <br>
                            <table class="no-border transaction" width="100%" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                              <thead class="bordered">
                                <tr>
                                  <th class="text-left first" width="30%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;padding-left:0;">{{{ trans('email-coupon-not-available.table_transaction.header.item', [], '', $lang) }}}</th>
                                  <th class="text-left" width="20%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-coupon-not-available.table_transaction.header.quantity', [], '', $lang) }}}</th>
                                  <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-coupon-not-available.table_transaction.header.price', [], '', $lang) }}}</th>
                                  <th class="text-left" width="25%" style="font-family:'Roboto', 'Arial', sans-serif;text-align:left;padding:8px;border-top:1px solid #999;border-bottom:1px solid #999;">{{{ trans('email-coupon-not-available.table_transaction.header.subtotal', [], '', $lang) }}}</th>
                                </tr>
                              </thead>
                              <tbody class="transaction-items">
                                  @foreach($transaction['items'] as $item)
                                    <tr class="transaction-item">
                                      <td class="first" style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;padding-left:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ $item['name'] }}}</td>
                                      <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['quantity'] }}}</td>
                                      <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['price'] }}}</td>
                                      <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['total'] }}}</td>
                                    </tr>
                                  @endforeach
                                  @foreach($transaction['discounts'] as $item)
                                    <tr class="transaction-item">
                                      <td class="first" style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;padding-left:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;word-wrap: break-word;">{{{ trans('label.discount', [], '', $lang) }}} {{{ $item['name'] }}}</td>
                                      <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['quantity'] }}}</td>
                                      <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['price'] }}}</td>
                                      <td style="font-family:'Roboto', 'Arial', sans-serif;border-bottom:1px solid #999;vertical-align:top;padding:15px 8px;border-bottom:0;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $item['total'] }}}</td>
                                    </tr>
                                  @endforeach
                              </tbody>
                              <tfoot class="transaction-footer">
                                <tr>
                                  <td colspan="2" style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"></td>
                                  <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;"><strong>{{{ trans('email-coupon-not-available.table_transaction.footer.total', [], '', $lang) }}}</strong></td>
                                  <td style="font-family:'Roboto', 'Arial', sans-serif;padding:15px 8px;border-top:1px solid #999;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">{{{ $transaction['total'] }}}</td>
                                </tr>
                              </tfoot>
                            </table>
                            <br>
                            <br>
                            <p style="font-family:'Roboto', 'Arial', sans-serif;margin:0;">
                              {{ trans('email-coupon-not-available.body.help', $cs, '', $lang) }}
                              <br>
                              {{{ trans('email-coupon-not-available.body.thank_you', [], '', $lang) }}}
                            </p>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
                <tr>
                    <td height="20" align="center">&nbsp;</td>
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
