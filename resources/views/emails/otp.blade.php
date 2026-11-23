<x-mail::message>
# Verify your email

Hi {{ $user->name }},

Use this code to verify your DeveloperJet account:

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

The code expires in {{ $minutes }} minutes. If you did not create an account, you can ignore this email.

Never share this code with anyone. DeveloperJet staff will never ask for it.

Thanks,<br>
DeveloperJet
</x-mail::message>
