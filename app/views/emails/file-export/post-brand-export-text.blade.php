Hello,

User {{ $userEmail}} started brand CSV Export process at {{ $exportDate }} UTC. The details as follow:

Export ID: {{ $exportId }}
Merchant(s) to Export: {{ $totalExport }}

List Merchant(s):
@foreach($merchants as $merchant)
  - {{ $merchant }}
@endforeach

@if(! empty($skippedMerchants))
    List of Merchant that not included because it is already in export process:
      @foreach($skippedMerchants as $skip)
        - {{ $skip }}
      @endforeach
@endif

Regards,
Mr. Robot