<?php
  $originalProductType = isset($productType) ? $productType : 'default';
?>

@foreach($supportedLangs as $lang)
  <?php $productType = trans("email-payment.product_type.{$originalProductType}", [], '', $lang); ?>

  {{ trans('email-aborted-payment.body.greeting_digital_product.customer_name', ['customerName' => $customerName], '', $lang) }}
  {{ trans('email-aborted-payment.body.greeting_digital_product.body', [], '', $lang) }}

  {{{ trans('email-aborted-payment.body.transaction_labels.product_name', [], '', $lang) }}} {{ $transaction['items'][0]['name'] }}
  {{{ trans('email-aborted-payment.body.transaction_labels.transaction_id', [], '', $lang) }}} {{ $transaction['id'] }}

  {{ trans('email-aborted-payment.body.payment-info-line-1', ['transactionDateTime' => $transactionDateTime], '', $lang) }}

  {{ trans('email-aborted-payment.body.payment-info-line-2', $cs, '', $lang) }}
  @if ($lang === 'en')

      {{ trans('email-aborted-payment.body.payment-info-line-3', [], '', $lang) }}

      {{ trans('email-aborted-payment.body.payment-info-line-4-digital-product', ['productType' => $productType], '', $lang) }}
  @endif


  {{{ trans('email-aborted-payment.body.buttons.buy_digital_product', ['productType' => $productType], '', $lang) }}}
  {{{ $buyUrl }}}


  {{ trans('email-aborted-payment.body.regards', [], '', $lang) }}

@endforeach

