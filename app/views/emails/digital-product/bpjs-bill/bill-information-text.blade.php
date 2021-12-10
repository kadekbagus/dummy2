@if (! empty($bill))
{{ strtoupper(trans('email-bill.labels.billing_details', [], '', $lang)) }}
{{ trans('email-bill.labels.billing_id', [], '', $lang) }}: {{ $bill->billing_id }}]
{{ trans('email-bill.labels.billing_name', [], '', $lang) }}: {{ $bill->customer_name }}
{{ trans('email-bill.labels.water_bill.periode', [], '', $lang) }}: {{ $bill->period }}
{{ trans('email-bill.labels.billing_amount', [], '', $lang) }}: {{ $bill->formatted_amount }}
@endif
