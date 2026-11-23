<x-mail::message>
# Reset your staff password

Hi {{ $user->name }},

A password reset was requested for your DeveloperJet staff account.

<x-mail::button :url="$url">
Choose a new password
</x-mail::button>

This link expires in {{ $minutes }} minutes and can be used once. Resetting your password signs out all of your staff sessions.

If you did not request this, ignore this email and tell a Super Admin.

Thanks,<br>
DeveloperJet
</x-mail::message>
