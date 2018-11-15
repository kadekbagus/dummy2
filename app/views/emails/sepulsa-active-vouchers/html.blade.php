<!DOCTYPE html>
<html>
<head>
    <title></title>
    <style>
        .ok {
            background: green;
            color: white;
        }

        .danger {
            background: red;
            color: white;
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
                <td align="center" class="{{ $coupon->in_db ? 'ok' : 'danger' }}">{{ $coupon->in_db ? 'Yes' : 'No' }}</td>
                <td align="center" class="{{ $coupon->in_sepulsa ? 'ok' : 'danger' }}">{{ $coupon->in_sepulsa ? 'Yes' : 'No' }}</td>
            </tr>
            @endforeach

            @if (count($newVouchers) > 0)
                @foreach($newVouchers as $token => $voucherName)
                <tr>
                    <td align="center">{{ ++$number }}</td>
                    <td>{{ $voucherName }}</td>
                    <td>{{ $token }}</td>
                    <td align="center" class="danger">No</td>
                    <td align="center" class="ok">Yes</td>
                </tr>
                @endforeach
            @endif

        </tbody>
    </table>
    <br>
    <br>
    <p>
        BR
        <br>
        Mr. Robot
    </p>
</body>
</html>
