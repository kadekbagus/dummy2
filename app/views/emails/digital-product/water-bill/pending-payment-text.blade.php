@foreach($supportedLangs as $lang)
  {{{ $transactionDateTime }}}

  {{ trans('email-pending-payment.body.greeting_digital_product.customer_name', ['customerName' => $customerName], '', $lang) }}

  {{ trans('email-pending-payment.body.greeting_digital_product.body', ['productName' => $transaction['items'][0]['name'], 'paymentMethod' => $paymentMethod,], '', $lang) }}


  {{{ trans('email-pending-payment.body.transaction_labels.transaction_id', [], '', $lang) }}} {{ $transaction['id'] }}
  {{{ trans('email-pending-payment.body.transaction_labels.transaction_date', [], '', $lang) }}} {{ $transactionDateTime }}
  {{{ trans('email-pending-payment.body.transaction_labels.customer_name', [], '', $lang) }}} {{ $customerName }}
  {{{ trans('email-pending-payment.body.transaction_labels.email', [], '', $lang) }}} {{ $customerEmail }}

  {{{ trans('email-pending-payment.body.transaction_labels.product', [], '', $lang) }}} {{ $transaction['items'][0]['name'] }}
  {{{ trans('email-pending-payment.body.transaction_labels.pulsa_price', [], '', $lang) }}} {{ $transaction['items'][0]['price'] }} X {{ $transaction['items'][0]['quantity'] }}
  @if (count($transaction['discounts']) > 0)
    @foreach($transaction['discounts'] as $discount)

      {{{ $discount['name'] }}}: {{ $discount['price'] }}
    @endforeach
  @endif

  {{{ trans('email-pending-payment.body.transaction_labels.total_amount', [], '', $lang) }}} {{ $transaction['total'] }}


  {{{ trans('email-pending-payment.body.btn_my_wallet', [], '', $lang) }}}
  {{{ $myWalletUrl }}}


  {{{ trans('email-pending-payment.body.btn_cancel_purchase', [], '', $lang) }}}
  {{{ $cancelUrl }}}


  {{ trans('email-pending-payment.body.payment-info-line-3', [], '', $lang) }}


@endforeach
