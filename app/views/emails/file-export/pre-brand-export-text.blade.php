Hello,

User {{ $userEmail}} started brand CSV Export process at {{ $exportDate }} UTC. The details as follow:

Export ID: {{ $exportId }}
Merchant(s) to Export: {{ $totalExport }}

List Merchant(s):
@foreach($merchants as $merchant)
  - {{ $merchant }}
@endforeach

Once the export process is completed you will get notified by email.

Regards,
Mr. Robot