<!DOCTYPE html>
<html>
<head>
    <title></title>
    <style>
        .ok {
            background: green;
            color: #fff;
        }

        .danger {
            background: red;
            color: #fff;
        }
    </style>
</head>
<body>
    <p>Hi!</p>
    <p>Here are report for the availability of Sepulsa Vouchers against our Active Campaigns <br>
        <strong style="color: red;font-size: 1.5rem;">per {{ Carbon\Carbon::now()->format('d F Y, H:i') }}</strong>
    </p>
    <table border="1" style="border-collapse: collapse;" cellpadding="8" cellspacing="8">
        <thead>
            <tr>
                <th width="50">No</th>
                <th>Campaign/Coupon Name</th>
                <th>Token</th>
                <th>In DB?</th>
                <th>In Sepulsa?</th>
            </tr>
        </thead>
        <tbody>

            <?php $number = 0; ?>
            @foreach($coupons as $coupon)
            <tr>
                <td align="center">{{ ++$number }}</td>
                <td>{{ $coupon->promotion_name }}</td>
                <td>{{ $coupon->token }}</td>
                <td align="center" class="{{ ! $coupon->in_db ? 'danger' : 'ok' }}">{{ $coupon->in_db ? 'Yes' : 'No' }}</td>
                <td align="center" class="{{ ! $coupon->in_sepulsa ? 'danger' : 'ok' }}">{{ $coupon->in_sepulsa ? 'Yes' : 'No' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <br>
    <br>
    <br>

    <p>
        BR
        <br>
        Mr. Robot
    </p>
</body>
</html>
