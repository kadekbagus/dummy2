<!doctype html>
<html>
<head>
<title>Orbit - A DominoPOS Product</title>
</head>
<body style="padding:0;margin:0;background-color:#fff;width:100%;font-family:Arial,Verdana,Tahoma,Serif;line-height:22px;font-size:16px;">
<div id="container;" style="padding:0; margin:0 auto;width:451px;border:1px solid #ccc">
    <div id="header" style="">
        <img style="width:451px;height:63px" src="{{ asset('emails/orbit-reset-password.png') }}" alt="Reset Password Notifier">
    </div>

    <div id="main" style="width:95%;margin: 0 auto;">
        <p>Hi <strong style="font-size:18px">{{ $first_name }}</strong>,</p>
        <p style="text-align:justify">Someone (hopefully you) recently requested a password reset.</p>
        <p style="text-align:justify">To reset your password, follow the link below:

        <a href="{{ $token_url }}" style="display: block; text-align: center; padding: 4px; background: #3DBEEC; width: 200px; font-weight: bold; text-decoration: none; color: #fff; margin: 1em auto 0 auto;">Reset My Password</a></p>

        <div id="regards" style="padding-top:1em;">
        <p>Regards,
        <img style="display:block;padding-top:0.7em" alt="Orbit Team" src="{{ asset('emails/orbit-team.png') }}">
        </p>
        </div>
    </div>
    <div id="contact" style="padding:0.5em 0 0.5em 0;margin-top:3em;border-top:1px dotted #ccc;line-height:18px;width:100%;">
        <p style="width:100%;margin:0 auto;text-align:center;">
            <strong>Orbit Customer Service</strong>
            <span style="display:block;font-size:12px;">Email: {{ $cs_email }}</span>
        </p>
    </div>
</div>
</body>
</html>
