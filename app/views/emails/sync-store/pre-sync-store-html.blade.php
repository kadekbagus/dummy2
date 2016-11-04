<html>
<head>
<title></title>
</head>
<body>
    <p>Hello,</p>
    <p>User {{ $userEmail }} started a store sync process at {{ $syncDate }} UTC. The details as follow:</p>
    <p>
        Sync ID: {{ $syncId }} <br/>
        Store(s) to Sync: {{ $totalSync }}
    </p>
    <p>Once the sync is completed you will get notified by email.</p>
    <p>
        Regards,<br/>
        Mr. Robot
    </p>
</body>
</html>