<?php
  $originalProductType = isset($productType) ? $productType : 'default';
?>

@foreach($supportedLangs as $lang)
  <?php $productType = trans("email-payment.product_type.{$originalProductType}", [], '', $lang); ?>

  {{{ $transactionDateTime }}}

  {{ trans('email-coupon-not-available.body.greeting_digital_product.customer_name', ['customerName' => $customerName], '', $lang) }}
  {{ trans('email-coupon-not-available.body.greeting_digital_product.body', ['productType' => $productType], '', $lang) }}

  {{{ trans('email-coupon-not-available.table_customer_info.header.trx_id', [], '', $lang) }}} {{{ $transaction['id'] }}}
  {{{ trans('email-coupon-not-available.table_customer_info.header.customer', [], '', $lang) }}} {{{ $customerName }}}
  {{{ trans('email-coupon-not-available.table_customer_info.header.email', [], '', $lang) }}} {{{ $customerEmail }}}
  {{{ trans('email-coupon-not-available.table_customer_info.header.phone', [], '', $lang) }}} {{{ $customerPhone }}}
  @foreach($transaction['items'] as $item)


      {{{ trans('email-coupon-not-available.table_transaction.header.item', [], '', $lang) }}}: {{ $item['name'] }}
      {{{ trans('email-coupon-not-available.table_transaction.header.quantity', [], '', $lang) }}}: {{{ $item['quantity'] }}}
      {{{ trans('email-coupon-not-available.table_transaction.header.price', [], '', $lang) }}}: {{{ $item['price'] }}}
      {{{ trans('email-coupon-not-available.table_transaction.header.subtotal', [], '', $lang) }}}: {{{ $item['total'] }}}
  @endforeach
  @foreach($transaction['discounts'] as $item)


      {{{ trans('label.discount', [], '', $lang) }}} {{{ $item['name'] }}}
      {{{ trans('email-coupon-not-available.table_transaction.header.quantity', [], '', $lang) }}}: {{{ $item['quantity'] }}}
      {{{ trans('email-coupon-not-available.table_transaction.header.price', [], '', $lang) }}}: {{{ $item['price'] }}}
      {{{ trans('email-coupon-not-available.table_transaction.header.subtotal', [], '', $lang) }}}: {{{ $item['total'] }}}
  @endforeach

  {{{ trans('email-coupon-not-available.table_transaction.footer.total', [], '', $lang) }}} {{{ $transaction['total'] }}}

  {{ trans('email-coupon-not-available.body.help', $cs, '', $lang) }}

  {{{ trans('email-coupon-not-available.body.thank_you', [], '', $lang) }}}

@endforeach
