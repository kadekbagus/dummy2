<html>
<head>
<title></title>
</head>
<body>
    <p>Hello,</p>
    <p>Sync ID {{ $syncId }} which was started by user {{ $userEmail }} has been completed. The details is as follow:</p>
    <br/>
    <p>
        Start Time : {{ $syncStartDate}} UTC <br/>
        End Time : {{ $syncEndDate }} UTC <br/>
        Store to sync : {{ $finishSync }}
    </p>
    <br/>
    <p>
       Regards, <br/>
       Mr. Robot
   </p>
</body>
</html>