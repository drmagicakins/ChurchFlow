Hello {{ $name }},

Someone asked to reset the password for your ChurchFlow account. If that was you, open this link to choose a new
one:

{{ $url }}

The link can be used once and expires in {{ $hours }} {{ \Illuminate\Support\Str::plural('hour', $hours) }}.

If you did not ask for this, you can ignore this email - your password will not change. Nobody can sign in to your
account with this message alone.

ChurchFlow
