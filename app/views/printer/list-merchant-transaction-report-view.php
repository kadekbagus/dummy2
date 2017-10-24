<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns:v="urn:schemas-microsoft-com:vml" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?php echo htmlentities($pageTitle); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="generator" content="Orbit" />
    <style media="all">
        * {
            margin: 0;
            padding: 0;
        }
        tr.zebra {
            background-color: #f1f1f1;
        }
        .hide {
            display: none;
        }
        #printernote {
            margin: 0;
            padding: 4px 8px;
            background: #FFFFAF;
            color: #555;
            font-size: 14px;
            height: 22px;
            position: relative;
            width: 100%;
            clear: both;
            font-family: Verdana, Arial, Serif;
        }
        table {
            border-collapse: collapse;
        }
        table tr td, table tr th {
            border-bottom: 1px solid #ccc;
            padding: 1px;

        }
    </style>
    <style type="text/css" media="print">
        #payment-date, #printernote { display:none; }
    </style>
</head>
<body>
<div id="printernote">
    <div style="float:left">
        <div style="margin-right:2em;top:4px;position:relative;">You are on printer friendly view.</div>
    </div>

    <div style="float:left">
        <select id="fontname" name="fontname">
            <option value="Arial">Arial</option>
            <option value="Courier New">Courier New</option>
            <option value="Sans-Serif">Sans-Serif</option>
            <option value="Serif">Serif</option>
            <option value="Tahoma">Tahoma</option>
            <option value="Times New Roman">Times New Roman</option>
            <option value="Verdana">Verdana</option>
        </select>
        <select id="fontsize" name="fontsize" style="width:50px;">
            <option value="8">8</option>
            <option value="9">9</option>
            <option value="10">10</option>
            <option value="11">11</option>
            <option value="12" selected="selected">12</option>
            <option value="13">13</option>
            <option value="14">14</option>
            <option value="15">15</option>
            <option value="16">16</option>
            <option value="17">17</option>
            <option value="18">18</option>
            <option value="19">19</option>
            <option value="20">20</option>
            <option value="17">21</option>
            <option value="18">22</option>
            <option value="19">23</option>
            <option value="20">24</option>
        </select>
        <button id="printbtn" style="padding:0 6px;" onclick="window.print()">Print Page</button>
    </div>
</div>

<div id="main">
    <table style="width:100%">
        <thead>
            <th style="text-align:left;">Base Merchant ID</th>
            <th style="text-align:left;">Merchant Name</th>
            <th style="text-align:left;">Store ID</th>
            <th style="text-align:left;">Store Name</th>
            <th style="text-align:left;">Store Location (mall)</th>
            <th style="text-align:left;">Coupon Campaign Name</th>
            <th style="text-align:left;">Coupon ID</th>
            <th style="text-align:left;">Coupon Redemption Code</th>
            <th style="text-align:left;">Transaction Date and Time</th>
            <th style="text-align:left;">Gtm Transaction ID</th>
            <th style="text-align:left;">External Transaction ID</th>
            <th style="text-align:left;">Payment Method (ewallet Operator Name OR Normal Redeem)</th>
            <th style="text-align:left;">Transaction Amount (paid by user)</th>
            <th style="text-align:left;">Currency</th>
            <th style="text-align:left;">Transaction Status</th>
        </thead>
        <tbody>
        <?php while ($row = $statement->fetch(PDO::FETCH_OBJ)) : ?>
            <tr class="{{ $rowCounter % 2 === 0 ? 'zebra' : '' }}">
                <td><?php echo $row->merchant_id; ?></td>
                <td><?php echo $row->merchant_name; ?></td>
                <td><?php echo $row->store_id; ?></td>
                <td><?php echo $row->store_at_building; ?></td>
                <td><?php echo $row->building_name; ?></td>
                <td><?php echo $row->object_name; ?></td>
                <td><?php echo $row->object_id; ?></td>
                <td><?php echo $row->coupon_redemption_code; ?></td>
                <td><?php echo $row->date_tz; ?></td>
                <td><?php echo $row->payment_transaction_id; ?></td>
                <td><?php echo $row->external_payment_transaction_id; ?></td>
                <td><?php echo $row->payment_method; ?></td>
                <td><?php echo number_format($row->amount, 2, '.', ','); ?></td>
                <td><?php echo $row->currency; ?></td>
                <td><?php echo $row->status; ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!--<div style="page-break-after:always;"></div>-->

<script type="text/javascript">
    window.onload = function() {
        // window.print();
        document.getElementById('fontname').onchange = function() {
            var selectedFont = document.getElementById('fontname').value;
            document.getElementById('main').style.fontFamily = selectedFont;
        };

        document.getElementById('fontsize').onchange = function() {
            var selectedSize = document.getElementById('fontsize').value;
            document.getElementById('main').style.fontSize = selectedSize + "px";
        };

        document.getElementById('main').style.fontFamily = "Arial";
        document.getElementById('main').style.fontSize = "12px";
    }
</script>

</body>
</html>
