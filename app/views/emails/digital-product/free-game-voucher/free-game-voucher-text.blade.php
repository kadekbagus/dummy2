@foreach($supportedLangs as $lang)

{{ trans('email-purchase-rewards.free_game_voucher.greeting', ['customerName' => $customerName], '', $lang) }},

{{ trans('email-purchase-rewards.free_game_voucher.congrats', [], '', $lang) }}

{{ trans('email-purchase-rewards.free_game_voucher.line_1', ['providerProductName' => $productName], '', $lang) }}

{{ trans('email-purchase-rewards.free_game_voucher.line_2', [], '', $lang)}}


----------------------------

{{ trans('email-purchase-rewards.free_game_voucher.labels.transaction_id', [], '', $lang) }} : {{ $transactionId }}

{{ trans('email-purchase-rewards.free_game_voucher.labels.transaction_datetime', [], '', $lang) }} : {{ $transactionDateTime }}

{{ trans('email-purchase-rewards.free_game_voucher.labels.pin', [], '', $lang) }} : {{ $voucher['pin'] }}

{{ trans('email-purchase-rewards.free_game_voucher.labels.serial_number', [], '', $lang) }} : {{ $voucher['serialNumber'] }}

----------------------------


{{ trans('email-purchase-rewards.free_game_voucher.line_3', [], '', $lang) }}

{{ trans('email-purchase-rewards.free_game_voucher.line_4',
    ['startDate' => $voucher['startDate'], 'endDate' => $voucher['endDate']],
    '',
    $lang)
}}

{{ trans('email-purchase-rewards.free_game_voucher.thank_you', [], '', $lang) }}


============================


@endforeach
