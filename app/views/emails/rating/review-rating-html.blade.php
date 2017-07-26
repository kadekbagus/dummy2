<html>
<head>
<title></title>
</head>
<body>
    <p>Hello,</p>
    <p>New rating and review on {{$location}}. The details as follow:</p>
    <p>
        Date: {{ $date }} UTC <br/>
        Type: {{ $type }} <br/>
        Location: {{ $location_detail }} <br/>
        Name: {{ $name }} <br/>
        Email: {{ $email }} <br/>
        Rating: {{ $rating }} <br/>
        Review: {{ $review }}
    </p>
    <p>
       Regards, <br/>
       Mr. Robot
   </p>
</body>
</html>