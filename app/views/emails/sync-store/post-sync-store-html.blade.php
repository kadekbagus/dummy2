<html>
<head>
<title></title>
</head>
<body>
    <p>Hello,</p>
    <p>Sync ID {{ $syncId }} which was started by user {{ $userEmail }} has been completed. The details as follow:</p>
    <p>
        Start Time: {{ $syncStartDate}} UTC <br/>
        End Time: {{ $syncEndDate }} UTC <br/>
        Synced Store(s): {{ $finishSync }} of {{ $totalSync }}
    </p>
    <p>
       Regards, <br/>
       Mr. Robot
   </p>
</body>
</html>