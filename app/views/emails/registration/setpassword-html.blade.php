<!doctype html>
<html>
<head>
<title>Orbit - A DominoPOS Product</title>
</head>
<body style="padding:0;margin:0;background-color:#fff;width:100%;font-family:Arial,Verdana,Tahoma,Serif;line-height:22px;font-size:16px;">
<div id="container;" style="padding:0; margin:0 auto;width:451px;border:1px solid #ccc;display: table;">
    <div id="header" style="">
        <img style="width:451px;height:63" src="{{ asset('emails/orbit-almost-in-orbit.png ') }}" alt="You are almost in Orbit!">
    </div>

    <div id="main" style="width:95%;margin: 0 auto;">
        <p style="line-height:1.2em;padding-top:1em;padding-bottom:1em;">Hi <strong style="font-size:18px">{{{ $first_name }}},</strong></p>
        <p  style="line-height:1.2em;text-align:justify;padding-bottom:1em;">To complete your <strong style="font-size:18px">registration</strong> and obtain <strong style="font-size:18px">great promotions</strong>
        and <strong style="font-size:18px;">money saving deals</strong>, follow this link below:</p>

        <p>
            <a href="{{ $token_url }}" style="display: block; text-align: center; padding: 4px; background: #3DBEEC; width: 200px; font-weight: bold; text-decoration: none; color: #fff; margin: 1em auto 0 auto;">Setup My Password</a></p>
        </p>

        <div id="regards" style="padding-top:1em;">
        <p style="line-height:1em;">Regards,
        <img style="display:block;padding-top:0.7em" alt="Orbit Team" src="{{ asset('emails/orbit-team.png') }}">
        </p>
    </div>

    <div id="contact" style="padding:0.5em 0 0.5em 0;margin-top:3em;border-top:1px dotted #ccc;line-height:18px;width:100%;">
        <p style="width:100%;margin:0 auto;text-align:center;">
            <strong>Orbit Customer Service</strong>
            <span style="display:block;font-size:12px;">Email: {{ $cs_email }}</span>
        </p>
    <div>
</div>
</body>
</html>
