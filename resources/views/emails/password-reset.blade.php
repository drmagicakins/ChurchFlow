<!doctype html>
<html>

<body>
    <h2>Set a new password</h2>

    <p>Hello {{ $name }},</p>

    <p>Someone asked to reset the password for your ChurchFlow account. If that was you, click the link below to
        choose a new one.</p>

    <p><a href="{{ $url }}">Set a new password</a></p>

    <p style="font-size:13px;color:#555">
        If the link does not open, copy this address into your browser:<br>
        {{ $url }}
    </p>

    <p>The link can be used once and expires in {{ $hours }}
        {{ \Illuminate\Support\Str::plural('hour', $hours) }}.</p>

    <p>If you did not ask for this, you can ignore this email — your password will not change. Nobody can sign in to
        your account with this message alone.</p>

    <p>ChurchFlow</p>
</body>

</html>
