@foreach($supportedLangs as $lang)
{{ trans('email-receipt.body.greeting_products.customer_name', ['customerName' => $customerName], '', $lang) }}

{{ trans('email-receipt.body.greeting_products.body_1', [
      'itemName' => $transaction['items'][0]['name'] ?: '',
    ], '', $lang
   )
}}

@include('emails.digital-product.bill-customer-text')

@include('emails.digital-product.bpjs-bill.bill-information-text')

{{ trans('email-receipt.body.view_my_purchases', [], '', $lang) }}


{{{ trans('email-receipt.buttons.my_purchases', [], '', $lang) }}}
{{{ $myWalletUrl }}}


{{ trans('email-receipt.body.help_text', ['csPhone' => $cs['phone'], 'csEmail' => $cs['email']], '', $lang) }}

{{{ trans('email-receipt.body.thank_you', [], '', $lang) }}}


---------------------------------


@endforeach
