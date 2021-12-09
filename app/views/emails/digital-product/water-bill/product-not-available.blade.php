@extends('emails.layouts.default')

@section('title')
Product Not Available
@stop

@section('content')
  <?php
    $originalProductType = isset($productType) ? $productType : 'default';
  ?>

  @foreach($supportedLangs as $lang)
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
                    <td width="600" align="right" valign="top" class="transaction-date"><strong>{{{ $transactionDateTime }}}</strong></td>
                </tr>
                <tr>
                    <td height="20" align="center">&nbsp;</td>
                </tr>

                <tr>
                  <td width="600" class="mobile" align="left" valign="top">
                    <h3 class="greeting-username">
                      {{ trans('email-coupon-not-available.body.greeting_digital_product.customer_name', ['customerName' => $customerName], '', $lang) }}
                    </h3>
                    <p class="greeting-text">
                        {{ trans('email-coupon-not-available.body.greeting_digital_product.body', ['productType' => $productType], '', $lang) }}
                    </p>
                  </td>
                </tr>
                <tr>
                    <td height="20" align="center">&nbsp;</td>
                </tr>
                <tr>
                  <td width="600" class="mobile center" valign="middle" style="text-align: center;">
                    <table width="100%">
                      <tr>
                        <td colspan="2" class="greeting-text" style="font-family:'Roboto', 'Arial', sans-serif;padding-top:10px;padding-bottom:10px;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                          <table class="no-border customer" width="100%" style="line-height:1.7em;font-size:14px;color:#222;width:100%;border:0;margin-top:30px;border-spacing:0 !important;border-collapse:collapse !important;table-layout:fixed !important;margin:0 auto !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;">
                            <tbody>
                              <tr>
                                <td class="mobile inline-mobile customer-info-block">
                                    <span class="customer-info-label">{{{ trans('email-coupon-not-available.table_customer_info.header.trx_id', [], '', $lang) }}}</span>
                                    <span class="customer-info-value">{{{ $transaction['id'] }}}</span>
                                </td>
                                <td class="mobile inline-mobile customer-info-block">
                                    <span class="customer-info-label">{{{ trans('email-coupon-not-available.table_customer_info.header.customer', [], '', $lang) }}}</span>
                                    <span class="customer-info-value">{{{ $customerName }}}</span>
                                </td>
                                <td class="mobile inline-mobile customer-info-block">
                                    <span class="customer-info-label">{{{ trans('email-coupon-not-available.table_customer_info.header.email', [], '', $lang) }}}</span>
                                    <span class="customer-info-value">{{{ $customerEmail }}}</span>
                                </td>
                                <td class="mobile inline-mobile customer-info-block">
                                    <span class="customer-info-label">{{{ trans('email-coupon-not-available.table_customer_info.header.phone', [], '', $lang) }}}</span>
                                    <span class="customer-info-value">{{{ $customerPhone }}}</span>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                          <br>
                          <table class="no-border transaction" width="100%">
                            <thead class="bordered">
                                <tr>
                                    <th class="transaction-item-name">{{{ trans('email-coupon-not-available.table_transaction.header.item', [], '', $lang) }}}</th>
                                    <th class="transaction-qty">{{{ trans('email-coupon-not-available.table_transaction.header.quantity', [], '', $lang) }}}</th>
                                    <th class="transaction-amount">{{{ trans('email-coupon-not-available.table_transaction.header.price', [], '', $lang) }}}</th>
                                    <th class="transaction-subtotal">{{{ trans('email-coupon-not-available.table_transaction.header.subtotal', [], '', $lang) }}}</th>
                                </tr>
                            </thead>
                            <tbody class="transaction-items">
                                @foreach($transaction['items'] as $item)
                                <tr class="transaction-item">
                                    <td class="transaction-item">{{ $item['name'] }}</td>
                                    <td class="transaction-item" style="text-align: center;">{{{ $item['quantity'] }}}</td>
                                    <td class="transaction-item">{{{ $item['price'] }}}</td>
                                    <td class="transaction-item">{{{ $item['total'] }}}</td>
                                </tr>
                                @endforeach
                                @foreach($transaction['discounts'] as $item)
                                <tr class="transaction-item">
                                    <td class="transaction-item">{{{ trans('label.discount', [], '', $lang) }}} {{{ $item['name'] }}}</td>
                                    <td class="transaction-item" style="text-align: center;">{{{ $item['quantity'] }}}</td>
                                    <td class="transaction-item">{{{ $item['price'] }}}</td>
                                    <td class="transaction-item">{{{ $item['total'] }}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="transaction-footer">
                                <tr>
                                    <td colspan="2" class="transaction-item transaction-total"></td>
                                    <td class="transaction-item transaction-total"><strong>{{{ trans('email-coupon-not-available.table_transaction.footer.total', [], '', $lang) }}}</strong></td>
                                    <td class="transaction-item transaction-total">{{{ $transaction['total'] }}}</td>
                                </tr>
                            </tfoot>
                          </table>
                          <br>
                          <br>
                          <p class="help-text">
                              {{ trans('email-coupon-not-available.body.help', $cs, '', $lang) }}
                              <br>
                              <br>
                              {{{ trans('email-coupon-not-available.body.thank_you', [], '', $lang) }}}
                          </p>
                          <br>
                          &nbsp;
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
