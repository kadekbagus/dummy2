<html>
<head>
<title></title>
</head>
<body>
    <p>Hello,</p>
    <p>User {{ $userEmail}} started brand CSV Export process at {{ $exportDate }} UTC. The details as follow:</p>
    <p>
        Export ID: {{ $exportId }} <br/>
        Merchant(s) to Export: {{ $totalExport }}
    </p>
    <p>
      List Merchant(s):<br>
      <ul>
        @foreach($merchants as $merchant)
          <li>{{ $merchant }}</li>
        @endforeach
      </ul>
    </p>
    @if(! empty($skippedMerchants))
      <p>
        List of Merchant that not included because it is already in export process:<br>
        <ul>
          @foreach($skippedMerchants as $skip)
            <li>{{ $skip }}</li>
          @endforeach
        </ul>
      </p>
    @endif
    <p>
       Regards, <br/>
       Mr. Robot
   </p>
</body>
</html>