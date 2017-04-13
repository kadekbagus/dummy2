Hello,
User {{ $userEmail}} started reward CSV Export process at {{ $exportDate }} UTC. The details as follow:
Export ID: {{ $exportId }}
Coupon(s) to Export: {{ $totalExport }}

@foreach ($coupons as $c)
    - {{ $c }}
@endforeach

@if(! empty($skippedCoupons))
    List of coupon that not included because it is already in export process:
      @foreach($skippedCoupons as $skip)
        - {{ $skip }}
      @endforeach
@endif

Regards,
Mr. Robot
