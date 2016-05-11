<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns:v="urn:schemas-microsoft-com:vml" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?php echo $pageTitle; ?></title>
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
        table thead {
            display:table-header-group;
        }
        table tbody {
            display:table-row-group;
        }
        table tr td, table tr th {
            border-bottom: 1px solid #ccc;
            padding: 1px;
        }
        table.noborder tr td, table.noborder tr th {
            border: 1;
            padding: 1px;
        }
        #loadingbar {
            position: relative;
            left: 1em;
        }

    </style>
    <style type="text/css" media="print">
        #payment-date, #printernote { display:none; }
        table thead {
            display:table-header-group;
        }
        table tbody {
            display:table-row-group;
        }
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
        <!-- <button id="printbtn" style="padding:0 6px;" onclick="window.exportToCSV()">Export to CSV</button> -->
    </div>
    <div id="loadingbar">Loading all the data, please wait...</div>
</div>

<div id="main">
    <h2 style="margin-bottom:0.5em;"><?php echo $pageTitle; ?></h2>
    <table style="width:100%; margin-bottom:1em;" class="noborder">
        <tr>
            <td style="width:150px"></td>
            <td style="width:10px;"></td>
            <td><strong></strong></td>
        </tr>
        <tr>
            <td>Total Tenants</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalRec); ?></strong></td>
        </tr>

        <?php if ($filterName != '') { ?>
            <tr>
                <td>Filter by Tenant Name</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($filterName); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($filterCategory != '') { ?>
            <tr>
                <td>Filter by Category</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($filterCategory); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($filterFloor != '') { ?>
            <tr>
                <td>Filter by Floor</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($filterFloor); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($filterUnit != '') { ?>
            <tr>
                <td>Filter by Unit</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($filterUnit); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($filterStatus != '') { ?>
            <tr>
                <td>Filter by Status</td>
                <td>:</td>
                <td><strong><?php echo htmlentities(implode(' , ',$filterStatus)); ?></strong></td>
            </tr>
        <?php } ?>

    </table>

    <table style="width:100%">
        <thead>
           <!--  <th style="text-align:left;">No.</th> -->
            <th style="text-align:left;">Tenant Name</th>
            <th style="text-align:left;">Categories</th>
            <th style="text-align:left;">Location</th>
            <th style="text-align:left;">Status</th>
            <th style="text-align:left;">Last Update</th>
        </thead>
        <tbody>
        <?php $count = 1; while ($row = $statement->fetch(PDO::FETCH_OBJ)) : ?>
            <tr class="{{ $rowCounter % 2 === 0 ? 'zebra' : '' }}">
                 <td><?php echo $me->printUtf8($row->name); ?></td>
                 <td><?php echo $me->printUtf8($row->tenant_categories); ?></td>
                 <td><?php echo $me->printLocation($row); ?></td>
                 <td><?php echo $row->status; ?></td>
                 <td><?php echo $me->printDateTime($row->updated_at, $timezone, 'd F Y  H:i:s'); ?></td>
            </tr>
        <?php  $count++; endwhile; ?>
        </tbody>
    </table>
</div>

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
        document.getElementById('loadingbar').style.display = 'none';
    }

    function exportToCSV() {
        // Replace the redundant query string argument 'export'
        var url = window.location.href.replace('&export=print', '').replace('&export=csv', '');

        window.location.href = url + '&export=csv';
    }
</script>

</body>
</html>
