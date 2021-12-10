<?php
  $originalProductType = isset($productType) ? $productType : 'default';
?>

@foreach($supportedLangs as $lang)
  <?php $productType = trans("email-payment.product_type.{$originalProductType}", [], '', $lang); ?>

  {{ trans('email-customer-refund.body.greeting_digital_product.customer_name', ['customerName' => $customerName], '', $lang) }}
  {{ trans('email-customer-refund.body.greeting_digital_product.body', [], '', $lang) }}

  {{{ trans('email-customer-refund.body.transaction_labels.transaction_id', [], '', $lang) }}} {{ $transaction['id'] }}
  {{{ trans('email-customer-refund.body.transaction_labels.transaction_date', [], '', $lang) }}} {{{ $transactionDateTime }}}
  {{{ trans('email-customer-refund.body.transaction_labels.amount', [], '', $lang) }}} {{ $transaction['total'] }}
  @if (! empty($reason))

    {{{ trans('email-customer-refund.body.transaction_labels.reason', [], '', $lang) }}}
    {{ $reason }}
  @endif


  {{ trans('email-customer-refund.body.content_digital_product.line_1', [], '', $lang) }}

  {{ trans('email-customer-refund.body.content_digital_product.line_2', [], '', $lang) }}

  {{{ trans('email-customer-refund.body.thank_you', [], '', $lang) }}}

  {{ trans('email-customer-refund.body.cs_name') }}

@endforeach
