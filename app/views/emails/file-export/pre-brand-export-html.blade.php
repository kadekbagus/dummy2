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
    <p>Once the export process is completed you will get notified by email.</p>
    <p>
       Regards, <br/>
       Mr. Robot
   </p>
</body>
</html>