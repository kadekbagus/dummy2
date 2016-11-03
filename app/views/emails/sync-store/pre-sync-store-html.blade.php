<html>
<head>
<title></title>
</head>
<body>
    <p>Hello,</p>
    <p>User {{ $userEmail }} started a store sync process at {{ $syncDate }} UTC. The details as follow:</p>
    <br/>
    <p>
        Sync ID : {{ $syncId }} <br/>
        Store to sync : {{ $totalSync }}
    </p>
    <br/>
    <p>Once the sync is completed you will get notified by email</p>
    <br/>
    <p>
        Regards,<br/>
        Mr. Robot
    </p>
</body>
</html>