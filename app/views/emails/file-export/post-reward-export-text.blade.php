Hello,
User {{ $userEmail}} started reward CSV Export process at {{ $exportDate }} UTC. The details as follow:
Export ID: {{ $exportId }}
Coupon(s) to Export: {{ $totalExport }}

@forelse ($coupons as $c)
    {{ $c }}
@endforelse

@if(! empty($skippedCoupons))
    List of coupon that not included because it is already in export process:
      @foreach($skippedCoupons as $skip)
        - {{ $skip }}
      @endforeach
@endif

Regards,
Mr. Robot
