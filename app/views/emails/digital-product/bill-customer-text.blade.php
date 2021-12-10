{{ strtoupper(trans('email-bill.labels.transaction_details', [], '', $lang)) }}
{{ trans('email-bill.labels.transaction_id', [], '', $lang) }}: {{ $transaction['id'] }}
{{ trans('email-bill.labels.transaction_date', [], '', $lang) }}: {{ $transactionDateTime }}
{{ trans('email-bill.labels.billing_amount', [], '', $lang) }}: {{ $bill->formatted_amount }}
{{ trans('email-bill.labels.convenience_fee', [], '', $lang) }}: {{ $transaction['formatted_convenience_fee'] }}
{{ trans('email-bill.labels.total_amount', [], '', $lang) }}: {{ $transaction['total'] }}
