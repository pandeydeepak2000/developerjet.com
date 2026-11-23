<x-mail::message>
# Staff invitation

Hi {{ $name }},

You have been invited to join the DeveloperJet staff portal as **{{ $roleName }}**.

<x-mail::button :url="$url">
Accept the invitation
</x-mail::button>

The link expires in {{ $hours }} hours and works once. You will choose your own password; nobody else will know it.

If you were not expecting this, ignore this email.

Thanks,<br>
DeveloperJet
</x-mail::message>
