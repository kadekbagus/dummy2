  <style type="text/css">
    * {
      font-family:'Roboto', 'Arial', sans-serif;
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
      letter-spacing: 2px;
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
      font-size: 14px;
      margin-top: 10px;
    }

    .btn {
      background-color: #ef5350;
      padding: 10px 40px;
      border-radius: 5px;
      box-shadow: 0px 0px 4px #444;
      color: #FFF;
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
      color: #ef5350;
      text-decoration: none;
      font-size: 14px;
      font-weight: bold;
      border: 1px solid #ef5350;
    }

    .btn.btn-light.btn-blue {
      border: 1px solid #2196F3;
      color: #2196F3;
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

    /* MEDIA QUERIES */
    @media all and (max-width:639px){
      .wrapper{ width:400px!important; padding: 0 !important; }
      .container{ width:360px!important;  padding: 0 !important; }
      .mobile{ width:360px!important; display:block!important; padding: 0 !important; }
      .img{ width:100% !important; height:auto !important; }
      *[class="mobileOff"] { width: 0px !important; display: none !important; }
      *[class*="mobileOn"] { display: block !important; max-height:none !important; }

      .container.mobile-full-width {width: 100% !important;}

      .container.footer-container { width: 100% !important; text-align: center !important;}

      .mobile.full-width { width: 100% !important; }

      .mobile.center { text-align: center !important; }

      .coupon-img-container {
        text-align: center;
      }

      .coupon-info {
        text-align: center;
      }

      .marketing-section-title {
        text-align: center;
      }
      .marketing-section-text {
        text-align: center;
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
    }
  </style>
