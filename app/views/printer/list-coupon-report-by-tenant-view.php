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
    <h2 style="margin-bottom:0.5em;">Coupon Detail Report for <?php echo ($couponName); ?></h2>
    <table style="width:100%; margin-bottom:1em;" class="noborder">
        <tr>
            <td style="width:150px"></td>
            <td style="width:10px;"></td>
            <td><strong></strong></td>
        </tr>
        <tr>
            <td>Total Coupons</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalCoupons, 0, '.', '.'); ?></strong></td>
        </tr>
        <tr>
            <td>Total Acquiring Customers</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalAcquiringCustomers, 0, '.', '.'); ?></strong></td>
        </tr>
        <tr>
            <td>Total Active Days</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalActiveDays, 0, '.', '.'); ?></strong></td>
        </tr>
        <tr>
            <td>Total Redemption Places</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalRedemptionPlace, 0, '.', '.'); ?></strong></td>
        </tr>

        <!-- Filtering -->
        <?php if ($couponCode != '') { ?>
            <tr>
                <td>Filter by Coupon Code</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($couponCode); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($customerAge != '') { ?>
            <tr>
                <td>Filter by Customer Age</td>
                <td>:</td>
                <td><strong><?php echo $customerAge; ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($customerGender != '') { 
            $gender_string = '';
            $count = 1;
            foreach ($customerGender as $key => $valgender){
                if ($count == 1) {
                    $gender_string .= $valgender ;
                } else {
                    $gender_string .= ', ' .$valgender;
                }
                $count++;
            }
        ?>
            <tr>
                <td>Filter by Customer Gender</td>
                <td>:</td>
                <td><strong><?php echo $gender_string; ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($redemptionPlace != '') { ?>
            <tr>
                <td>Filter by Redemption Place</td>
                <td>:</td>
                <td><strong><?php echo $redemptionPlace; ?></strong></td>
            </tr>
        <?php } ?>

        <?php 
            if ($issuedDateGte != '' && $issuedDateLte != ''){
                $startDate = date('d M Y', strtotime($issuedDateGte));
                $endDate = date('d M Y', strtotime($issuedDateLte));
                $dateRange = $startDate . ' - ' . $endDate;
                if ($startDate === $endDate) {
                    $dateRange = $startDate;
                }
        ?>
                    <tr>
                        <td>Issued Date</td>
                        <td>:</td>
                        <td><strong><?php echo $dateRange; ?></strong></td>
                    </tr>
        <?php
            }
        ?>

        <?php 
            if ($redeemedDateGte != '' && $redeemedDateLte != ''){
                $startDate = date('d M Y', strtotime($redeemedDateGte));
                $endDate = date('d M Y', strtotime($redeemedDateLte));
                $dateRange = $startDate . ' - ' . $endDate;
                if ($startDate === $endDate) {
                    $dateRange = $startDate;
                }
        ?>
                    <tr>
                        <td>Redeemed Date</td>
                        <td>:</td>
                        <td><strong><?php echo $dateRange; ?></strong></td>
                    </tr>
        <?php
            }
        ?>
    </table>
    <table style="width:100%">
        <thead>
            <th style="text-align:left;">No</th>
            <th style="text-align:left;">Coupon Code</th>
            <th style="text-align:left;">Customer Age</th>
            <th style="text-align:left;">Customer Gender</th>
            <th style="text-align:left;">Issued Date</th>
            <th style="text-align:left;">Redeemed Date</th>
            <th style="text-align:left;">Redemption Place</th>
            <th style="text-align:left;">Status</th>
        </thead>
        <tbody>
        <?php while ($row = $statement->fetch(PDO::FETCH_OBJ)) : ?>
            <tr class="{{ $rowCounter % 2 === 0 ? 'zebra' : '' }}">
                <td><?php echo (++$rowCounter); ?></td>
                <td><?php echo $row->issued_coupon_code; ?></td>
                <td><?php echo $row->age; ?></td>
                <td><?php echo $row->gender; ?></td>
                <td><?php echo date('d M Y H:i', strtotime($row->issued_date)); ?></td>
                <td><?php if (! empty($row->redeemed_date)) { echo date('d M Y', strtotime($row->redeemed_date)); } else { echo '--'; } ?></td>
                <td><?php if (! empty($row->redemption_place)) { echo htmlentities($row->redemption_place); } else { echo '--'; } ?></td>
                <td><?php if ($row->status != 'active') { echo $row->status; } else { echo 'issued'; } ?></td>
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
