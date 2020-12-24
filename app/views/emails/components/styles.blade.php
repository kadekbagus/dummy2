  <style type="text/css">
    * {
      font-family:'Roboto', 'Arial', sans-serif;
      color: #333;
      line-height: 1.5em;
    }

    /* Outlines the grids, remove when sending */
    /* table td { border: 1px solid cyan; } */

    /* CLIENT-SPECIFIC STYLES */
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; }

    /* RESET STYLES */
    img { border: 0; outline: none; text-decoration: none; }
    table { border-collapse: collapse !important; }
    body { margin: 0 !important; padding: 0 !important; width: 100% !important; }

    /* iOS BLUE LINKS */
    a[x-apple-data-detectors] {
      color: inherit !important;
      text-decoration: none !important;
      font-size: inherit !important;
      font-family: inherit !important;
      font-weight: inherit !important;
      line-height: inherit !important;
    }

    /* ANDROID CENTER FIX */
    div[style*="margin: 16px 0;"] { margin: 0 !important; }

    img.logo {
      height:40px;
      margin-top:25px;
      margin-bottom:25px;
    }

    .greeting-title-container {
      background-color: red;
      background: url('https://s3-ap-southeast-1.amazonaws.com/asset1.gotomalls.com/uploads/emails/banner.png');
      background-size: 640px;
      background-repeat: no-repeat;
    }

    .greeting-title {
      font-size: 32px;
      text-transform: uppercase;
      letter-spacing: 5px;
      color: #FFF;
      margin: 0;
    }

    .greeting-username {
      margin: 25px 0 10px 0;
      color: #222;
      font-size: 16px;
    }

    .greeting-text {
      color: #333;
      line-height: 1.5em;
      font-size: 16px;
      margin-top: 10px;
    }

    .btn {
      background-color: #ef5350;
      padding: 10px 40px;
      border-radius: 5px;
      box-shadow: 0px 0px 4px #444;
      color: #ffffff !important;
      text-decoration: none;
      font-size: 14px;
      font-weight: bold;
    }

    .btn.btn-blue {
      background-color: #2196F3;
    }

    .btn.btn-light {
      background-color: #fff;
      padding: 10px 40px;
      border-radius: 5px;
      box-shadow: 0px 0px 4px #444;
      color: #ef5350 !important;
      text-decoration: none;
      font-weight: bold;
      border: 1px solid #ef5350;
    }

    .btn.btn-light.btn-blue {
      border: 1px solid #2196F3;
      color: #2196F3;
    }

    .mx-4 {
      margin-left: 4px;
      margin-right: 4px;
    }

    .text-left {
      text-align: left;
    }

    .text-right {
      text-align: right;
    }

    .text-center {
      text-align: center;
    }

    .bold {
      font-weight: bold;
    }

    .uppercase {
      text-transform: uppercase;
    }

    .text-orange {
      color: orange;
    }

    .text-green {
      color: green;
    }

    .text-red {
      color: #ef5350;
    }

    .text-gray {
      color: gray;
    }

    .w-35 {
      width: 35%;
    }

    .p-8 {
      padding: 8px;
    }

    .block {
      display: block;
    }

    .coupon-img-container {
      margin-right: 5px;
      text-decoration: none;
      font-size: 16px;
      font-weight: bold;
      text-align: right;
      display: block;
    }

    .coupon-info {
      margin-left: 5px;
      text-decoration: none;
      display: block;
      color: #222;
      font-size: 14px;
    }

    .coupon-image {
      width:80px;
      height:80px;
    }

    .coupon-name {
      font-size:16px;
    }

    .coupon-info {
      font-size:15px;
    }

    .marketing-container {
      color: #222;
    }

    .marketing-section-img {
      text-align: right;
      padding-right: 20px;
    }

    .marketing-icon {
      width: 60px;
    }

    .marketing-section-text-container {
      width: 80%;
    }

    .marketing-section-title {
      margin-top: 0;
      margin-bottom: 0;
      font-size: 17px;
    }

    .marketing-section-text {
      line-height: 1.5em;
      margin-top: 10px;
      font-size: 14px;
    }

    .bottom-info {
      color: #333;
      line-height: 1.5em;
      margin-bottom: 5px;
      font-size: 14px;
    }

    .footer-container {
      font-size: 12px;
      color: #333;
      text-align: left;
    }

    .footer-address {
      font-style: normal;
      line-height: 1.7em;
    }

    .footer-telp {
      text-decoration: underline;
      color: #ef5350;
    }

    .contact-link {
      text-decoration: none;
    }

    .contact-img {
      width:30px;;
      height: 30px;
      margin-left: 2px;
      margin-right: 2px;
    }

    .footer-follow-container {
      text-align: left;
    }

    .footer-follow {
      font-size: 14px;
      margin-top:0;
      margin-bottom:5px;
      line-height:1.7em;
    }

    .transaction-details {
      color: #333;
      text-align: left;
      line-height: 1.5em;
      font-size: 16px;
    }

    .help-text {
      font-size: 16px;
      color: #333;
      text-align: left;
      line-height: 1.4em;
    }

    .transaction-date {
      color: #333;
      margin-top: 10px;
      padding-top: 20px;
      font-size: 14px;
    }

    .customer-info-block {
      line-height: 1.4em;
      text-align: center;
      color: #333;
      font-size: 15px;
      vertical-align: top;
    }

    .customer-info-label {
        display: block;
        font-weight: bold;
    }

    .customer-info-value {
        display: block;
        padding-left: 10px;
        padding-right: 10px;
        word-break: break-all;
    }

    .transaction-item-name,
    .transaction-qty,
    .transaction-amount,
    .transaction-subtotal {
      text-align:center;
      padding:8px;
      border-top:1px solid #999;
      border-bottom:1px solid #999;
      padding-left:0;
      width: 25%;
      font-size: 15px;
    }

    .transaction-item {
      border-bottom:1px solid #999;
      vertical-align:top;
      padding:15px 8px;
      padding-left:0;
      mso-table-lspace:0pt !important;
      mso-table-rspace:0pt !important;
      word-wrap: break-word;
      text-align:center;
      font-size: 15px;
      color: #333;
    }

    .transaction-item.item-name {
      text-align: left;
      word-break: break-all;
    }

    .transaction-total {
      border-bottom: 0;
      font-size: 15px;
    }

    .voucher-data-label {
      text-align: left;
      font-size: 16px;
      margin-bottom: 8px;
      font-weight: bold;
      color: #222;
    }

    .voucher-data-container {
      text-align: left;
      font-size: 16px;
      padding: 5px 14px;
      border-radius: 5px;
      background: #f3f3f3;
      color: #222;
    }

    .voucher-data-item {
      font-size: 16px;
      margin: 10px 0;
      text-align: left;
      color: #222;
    }

    .btn.btn-visit {
      display: inline-block;
      padding-top: 10px;
      padding-bottom: 10px;
      padding-right: 15px;
      padding-left: 15px;
      border-radius: 5px;
      margin-top: 50px;
      color: #ffffff !important;
    }

    .statistic-item {
      border-top: 1px solid #ddd;
      padding-top: 10px;
      padding-bottom: 10px;
    }

    .statistic-title {
      font-size: 17px;
      margin-bottom: 10px;
      color: #222;
      text-align: center;
    }

    .statistic-value {
      font-size: 26px;
      font-weight: bold;
      color: #2196F3;
      margin-bottom: 10px;
      text-align: center;
    }

    .statistic-value.number-of-views {
      text-align: right;
    }

    .pulsa-banner-img {
      width: 100%;
      height: auto;
      max-height:140px;
      margin-top: 10px;
      margin-bottom: 10px;
    }

    .campaigns-title {
      font-size: 17px;
      color: #222;
      margin: 10px 0;
    }

    .campaigns-desc {
      color: #333;
      line-height: 1.5em;
      font-size: 16px;
      margin-top: 10px;
    }

    .campaigns-views {
      font-size: 20px;
      margin-top: 10px;
    }

    .see-all {
      font-size: 16px;
      color: #ef5350 !important;
    }

    .campaigns-item-container,
    .campaigns-item-separator {
      border-top: 1px solid #ddd;
      padding-top: 20px;
      padding-bottom: 20px;
    }

    .reservation-table-title {
      padding-top: 8px;
      padding-bottom:8px;
      background-color: #eee;
      border:1px solid #e3e3e3;
      letter-spacing:4px;
    }

    .reservation-table-item-label {
      border: 1px solid #e3e3e3;
      border-right: 0;
      border-top: 0;
    }

    .reservation-table-item-value {
      border: 1px solid #e3e3e3;
      border-left: 0;
      border-top: 0;
    }

    .reservation-actions .btn {
        box-shadow: none !important;
        display: inline-block;
        min-width: 20%;
    }

    /* MEDIA QUERIES */
    @media all and (max-width:639px) {
      .wrapper{ width:95% !important; padding: 0 !important; }
      .container{ width:90% !important;  padding: 0 !important; }
      .mobile{ width:100% !important; display:block!important; padding: 0 !important; }
      .img{ width:100% !important; height:auto !important; }
      *[class="mobileOff"] { width: 0px !important; display: none !important; }
      *[class*="mobileOn"] { display: block !important; max-height:none !important; }

      .container.mobile-full-width {width: 100% !important;}

      .container.footer-container { width: 100% !important; text-align: center !important;}

      .mobile.full-width { width: 100% !important; }

      .mobile.center { text-align: center !important; }

      img.logo {
        height: 60px;
      }

      .greeting-username {
        font-size: 24px;
      }

      .greeting-text {
        font-size: 22px;
      }

      .transaction-details {
        font-size: 22px;
      }

      .btn {
        font-size: 22px;
      }

      .btn-block {
        display: block;
        margin-bottom: 15px;
      }

      .coupon-img-container {
        text-align: center;
      }

      .coupon-image {
        width:100px;
        height:100px;
      }

      .coupon-info {
        text-align: center;
      }

      .coupon-name {
        font-size: 22px;
      }

      .coupon-location {
        font-size: 20px;
      }

      .marketing-icon {
        width: 90px;
      }

      .marketing-section-title {
        text-align: center;
        font-size: 22px;
      }

      .marketing-section-text {
        text-align: center;
        font-size: 20px;
      }

      .marketing-section-img {
        text-align: center;
        margin-bottom: 20px;
      }

      .marketing-icon {
        width: 80px;
      }

      .footer-follow-container {
        text-align: center;
      }

      .footer-follow {
        margin-top: 20px;
      }

      .footer-address {
        font-size: 18px;
      }

      .footer-follow {
        font-size: 20px;
      }

      .contact-img {
        width: 50px;
        height: 50px;
      }

      .separator {
        height: 50px;
      }

      .help-text {
        font-size: 22px;
      }

      .help-text.user-report-help-text {
        font-size: 20px;
      }

      .mobile.inline-mobile {
        width: auto !important;
        display: inline-block !important;
      }

      .mobile.inline-mobile.customer-info-block {
        width: 49% !important;
        margin-bottom: 15px;
        font-size: 22px;
        line-height: 1.2em;
        text-align: left;
      }

      .customer-info-label {
        font-size: 20px;
      }

      .customer-info-value {
        font-size: 20px;
      }

      .transaction-item-name,
      .transaction-qty,
      .transaction-amount,
      .transaction-subtotal {
        font-size: 20px;
      }

      .transaction-item {
        font-size: 20px;
      }

      .transaction-total {
        font-size: 20px;
      }

      .voucher-data-label {
        font-size: 20px;
      }

      .voucher-data-container {
        font-size: 24px;
      }

      .voucher-data-item {
        font-size: 24px;
      }

      .mobile.mobile-align-center { text-align: center !important; }

      .mobile.hide-on-mobile {display: none !important;}

      .btn.btn-visit {
        margin-top: 20px;
        margin-bottom: 20px;
      }

      .mobile.statistic-item {
        display: inline-block !important;
        padding-top: 10px !important;
        padding-bottom: 10px !important;
      }

      .mobile.statistic-item td {
        width: 49% !important;
      }

      .statistic-title {
        font-size: 24px;
        margin-bottom: 10px;
        text-align: center;
      }

      .statistic-value {
        font-size: 36px;
        text-align: center;
      }

      .mobile.campaigns-item-container {
        padding-top: 20px !important;
        padding-bottom: 20px !important;
      }

      .campaigns-title {
        font-size: 24px;
      }

      .campaigns-desc {
        font-size: 20px;
      }

      .campaigns-views {
        font-size: 30px;
      }

      .see-all {
        font-size: 20px;
      }

      .campaigns-item-container,
      .campaigns-item-separator {
        border-top: 1px solid #ddd;
        padding-top: 20px;
        padding-bottom: 20px;
      }

      .reservation-table-item-label {
        border-right: 1px solid #e3e3e3;
      }

      .reservation-table-item-value {
        border-left: 1px solid #e3e3e3;
      }

      .reservation-actions {
        display: flex !important;
        flex-direction: column-reverse;
      }

      .reservation-actions .btn {
        display: block;
        margin-bottom: 10px;
      }
    }
  </style>
