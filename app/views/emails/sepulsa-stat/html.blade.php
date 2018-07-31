<!DOCTYPE html>
<html>
<head>
    <title></title>
</head>
<body>
    <p>Hi!</p>
    <p>Here are report for Sepulsa vs GTM Redemption stats:</p>
    <table border="1">
        <thead>
            <tr>
                <td>Voucher Name</td>
                <td>Token</td>
                <td>Sepulsa qty</td>
                <td>GTM qty</td>
                <td>Sepulsa issued</td>
                <td>GTM issued</td>
                <td>Sepulsa redeemed</td>
                <td>GTM redeemed</td>
            </tr>
        </thead>
        <tbody>

            @foreach($data as $item)
            <tr>
                <td>{{$item['promotion_name']}}</td>
                <td>{{$item['token']}}</td>
                <td>{{$item['gtm_available_count']}}</td>
                <td>{{$item['gtm_issued_count']}}</td>
                <td>{{$item['gtm_redeemed_count']}}</td>
                <td>{{$item['sepulsa_available_count']}}</td>
                <td>{{$item['sepulsa_issued_count']}}</td>
                <td>{{$item['sepulsa_redeemed_count']}}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p>
        BR
        <br>
        Mr. Robot
    </p>
</body>
</html>