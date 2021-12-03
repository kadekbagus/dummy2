@foreach($supportedLangs as $lang)
  {{ trans('email-receipt.body.greeting_products.customer_name', ['customerName' => $customerName], '', $lang) }}

  @if (count($transaction['otherProduct']) >= 1)
    {{ trans('email-receipt.body.greeting_products.body_2', [
          'itemName' => $transaction['itemName'] ?: '',
          'otherProduct' => $transaction['otherProduct'] ?: '',
        ],
        '',
        $lang
       )
    }}
  @elseif (count($transaction['otherProduct']) <= 0)
    {{ trans('email-receipt.body.greeting_products.body_1', [
          'itemName' => $transaction['itemName'] ?: '',
        ], '', $lang
       )
    }}
  @endif

  [Bill information goes here...]

  {{ trans('email-receipt.body.view_my_purchases', [], '', $lang) }}


  {{{ trans('email-receipt.buttons.my_purchases', [], '', $lang) }}}
  {{{ $myWalletUrl }}}


  {{ trans('email-receipt.body.help', ['csPhone' => $cs['phone'], 'csEmail' => $cs['email']], '', $lang) }}

  {{{ trans('email-receipt.body.thank_you', [], '', $lang) }}}

@endforeach
