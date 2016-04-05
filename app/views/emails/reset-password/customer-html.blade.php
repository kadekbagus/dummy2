<!doctype html>
<html>
<head>
<title>Gotomalls</title>
</head>
<body style="padding:0;margin:0;background-color:#fff;width:100%;font-family:Arial,Verdana,Tahoma,Serif;line-height:22px;font-size:16px;">
<div id="container;" style="padding:0; margin:0 auto;width:451px;border:1px solid #ccc">
    <div id="header" style="">
        <img style="width:451px;height:63px" src="{{ asset('emails/orbit-gotomalls-reset-password-header.png') }}" alt="Reset Password Notifier">
    </div>

    <div id="main" style="width:95%;margin: 0 auto;">
        <p>Hi <strong style="font-size:18px">{{ $email }}</strong>,</p>
        <p style="text-align:justify">We received a request to reset your password for your gotomalls account:</p>
        <p style="text-align:justify"><strong style="font-size:18px">{{ $email }}</strong> We're here to help you.</p>

        <p style="text-align:justify">Click this button to reset your password :</p>

        <p><a href="{{ $token_url }}" style="text-align: left; padding: 4px; background: #1088DE; width: 200px; font-weight: bold; text-decoration: none; color: #fff; margin: 1em auto 0 auto;border-radius: 5px">Reset My Password</a></p>

        <p style="text-align:justify"><i>if you didn't ask to change your password, don't worry! Your password is still safe and just ignore this email</i></p>

        <div id="regards" style="padding-top:1em;">
        <span style="float:left;width:10px;">Cheers,
            <img style="display:block;padding-top:0.7em" alt="Orbit Team" src="{{ asset('emails/orbit-team.png') }}">
        </span>
        <span style="float:right;width:100px;">
            <img style="padding-top:0.7em;max-width:100%;max-height:100%" alt="Orbit Team" src="{{ asset('emails/orbit-gotomalls-reset-password-footer.png') }}">
        </span>
        </div>
    </div>
    <div id="contact" style="padding:0.5em 0 0.5em 0;margin-top:7em;line-height:18px;width:100%;">

    </div>
</div>
</body>
</html>
