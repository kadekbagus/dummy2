<html>
    <title>Confirm Coupon Transfer</title>
    <body>
        Hi, {{ $couponOwnerName }} just sent you a coupon via Gotomalls.com. Click button "Accept" to confirm the transfer or "Decline" to reject it.
        <br>
        <br>
        <a href="{{ $acceptUrl }}">Accept</a> <a href="{{ $declineUrl }}">Decline</a>
    </body>
</html>
