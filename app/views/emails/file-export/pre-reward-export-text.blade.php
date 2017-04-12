Hello,
User {{ $userEmail}} started reward CSV Export process at {{ $exportDate }} UTC. The details as follow:
Export ID: {{ $syncId }}
Coupon(s) to Export: {{ $totalExport }}
@forelse ($coupons as $c)
    {{ $c }}
@endforeach

Once the export process is completed you will get notified by email.

Regards,
Mr. Robot
