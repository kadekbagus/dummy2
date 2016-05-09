<!doctype html>
<html>
<head>
<title>Activate Your Account - GotoMalls</title>
</head>
<body style="padding:0;margin:0;background-color:#fff;width:100%;font-family:Arial,Verdana,Tahoma,Serif;line-height:22px;font-size:16px;">
<div id="container;" style="padding:0; margin:0 auto;width:451px;border:1px solid #ccc;display: table;">
    <div id="header" style="">
        <img style="width:451px;height:63px" src="{{ asset('emails/orbit-gotomalls-header.png') }}" alt="Activate Your GotoMalls Account">
    </div>

    <div id="main" style="width:95%;margin: 0 auto;">
        <p style="line-height:1.2em;padding-top:1em;padding-bottom:1em;">Hi <strong style="font-size:18px">{{{ $first_name or $email }}},</strong></p>
        <p style="line-height:1.2em;text-align:justify;padding-bottom:1em;">We hope you had a great experience at <b>&quot;{{ $shop_name }}&quot;</b>.
        <p  style="line-height:1.2em;text-align:justify;padding-bottom:1em;">To complete your registration please click "Activate My Account" button below:</p>

        <p>
            <a href="{{ $token_url }}" style="display: block; text-align: center; padding: 4px; background: #3DBEEC; width: 200px; font-weight: bold; text-decoration: none; color: #fff; margin: 1em auto 0 auto;">Activate My Account</a></p>
        </p>

        <div id="regards" style="padding-top:1em;">
            <div style="line-height:1em; float:left">Cheers,
                <img style="display:block;padding-top:0.7em" alt="Orbit Team" src="{{ asset('emails/orbit-team.png') }}">
            </div>
            <div id="footer" style="float:right;width:100px;">
                <img style="max-width: 100%; max-height: 100%" alt="GotoMalls" src="{{ asset('emails/orbit-gotomalls-footer.png') }}">
            </div>
            <div style="clear:both;">&nbsp;</div>
        </div>
    </div>
</div>
</body>
</html>
