@if (! empty($bill))
{{ strtoupper(trans('email-bill.labels.billing_details', [], '', $lang)) }}
{{ trans('email-bill.labels.billing_id', [], '', $lang) }}: {{ $bill->billing_id }}]
{{ trans('email-bill.labels.billing_name', [], '', $lang) }}: {{ $bill->customer_name }}
{{ trans('email-bill.labels.water_bill.periode', [], '', $lang) }}: {{ $bill->period }}
{{ trans('email-bill.labels.water_bill.meter_start', [], '', $lang) }}: {{ $bill->meter_start }}
{{ trans('email-bill.labels.water_bill.meter_end', [], '', $lang) }}: {{ $bill->meter_end }}
{{ trans('email-bill.labels.water_bill.usage', [], '', $lang) }}: {{ $bill->usage }}
{{ trans('email-bill.labels.billing_amount', [], '', $lang) }}: {{ $bill->amount }}
{{-- {{ trans('email-bill.labels.total_amount', [], '', $lang) }}: {{ $transaction['total'] }} --}}
@endif
