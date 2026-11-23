@props(['name', 'size' => 20])
{{-- Original stroke icon set (24px grid). Decorative by default. --}}
<svg {{ $attributes->merge(['class' => 'dj-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
    @case('terminal')<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3M12 15h5"/>@break
    @case('browser')<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01"/>@break
    @case('workflow')<rect x="3" y="4" width="6" height="5" rx="1.2"/><rect x="15" y="4" width="6" height="5" rx="1.2"/><rect x="9" y="15" width="6" height="5" rx="1.2"/><path d="M9 6.5h6M18 9v2.5a1.5 1.5 0 0 1-1.5 1.5H12v2M6 9v2.5A1.5 1.5 0 0 0 7.5 13H12"/>@break
    @case('shield')<path d="M12 3 4.5 6v5.5c0 4.6 3.1 8.2 7.5 9.5 4.4-1.3 7.5-4.9 7.5-9.5V6Z"/><path d="m9 12 2 2 4-4"/>@break
    @case('layers')<path d="m12 3 9 5-9 5-9-5Z"/><path d="m3 13 9 5 9-5"/>@break
    @case('code')<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>@break
    @case('container')<path d="M3 8.5 12 4l9 4.5v7L12 20l-9-4.5Z"/><path d="M3 8.5 12 13l9-4.5M12 13v7"/>@break
    @case('cpu')<rect x="6" y="6" width="12" height="12" rx="1.5"/><rect x="9.5" y="9.5" width="5" height="5" rx=".6"/><path d="M9 2.5V6M15 2.5V6M9 18v3.5M15 18v3.5M2.5 9H6M2.5 15H6M18 9h3.5M18 15h3.5"/>@break
    @case('server')<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6"/>@break
    @case('scale')<path d="M12 4v16M5 20h14M6 8h12M6 8l-3 6a3 3 0 0 0 6 0ZM18 8l-3 6a3 3 0 0 0 6 0Z"/>@break
    @case('grid')<rect x="3.5" y="3.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.2"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.2"/>@break
    @case('globe')<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.5 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.5-3.8-9S9.5 5.5 12 3Z"/>@break
    @case('search')<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>@break
    @case('wrench')<path d="M14.5 6.5a4 4 0 0 0-5.3 5.3L4 17l3 3 5.2-5.2a4 4 0 0 0 5.3-5.3l-2.6 2.6-2.4-.6-.6-2.4Z"/>@break
    @case('spark')<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>@break
    @case('cart')<circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2.5 3.5h2.8l2.4 11.2a1 1 0 0 0 1 .8h9a1 1 0 0 0 1-.8L20.5 7H6.1"/>@break
    @case('user')<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>@break
    @case('check')<path d="m5 12.5 4.5 4.5L19 7.5"/>@break
    @case('x')<path d="M6 6l12 12M18 6 6 18"/>@break
    @case('arrow-right')<path d="M5 12h14M13 6l6 6-6 6"/>@break
    @case('chevron-down')<path d="m6 9 6 6 6-6"/>@break
    @case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
    @case('eye')<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/>@break
    @case('lock')<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>@break
    @case('card')<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>@break
    @case('activity')<path d="M3 12h4l2.5-6 5 12 2.5-6h4"/>@break
    @case('headset')<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3.5" y="13.5" width="4" height="6" rx="1.4"/><rect x="16.5" y="13.5" width="4" height="6" rx="1.4"/><path d="M18.5 19.5c0 1.2-1.6 2-4.5 2"/>@break
    @case('book')<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5Z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>@break
    @case('dashboard')<rect x="3" y="3.5" width="18" height="17" rx="2"/><path d="M3 9h18M9 9v11.5"/>@break
    @case('rocket')<path d="M12 15.5 8.5 12C10 7 13.5 3.8 20 4c.2 6.5-3 10-8 11.5Z"/><path d="M8.5 12 5 11.5l2.8-3.3H11M12 15.5l.5 3.5 3.3-2.8V13"/><circle cx="15.2" cy="8.8" r="1.4"/><path d="M6 18c-1 .5-1.8 1.5-2 3 1.5-.2 2.5-1 3-2"/>@break
    @case('key')<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l2.5 2.5M14.5 8.5l2 2"/>@break
    @case('settings')<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/>@break
    @case('memory')<rect x="3" y="7" width="18" height="10" rx="1.5"/><path d="M7 10.5v3M11 10.5v3M15 10.5v3M6 17v2.5M18 17v2.5M12 17v2.5"/>@break
    @case('disk')<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>@break
    @case('network')<circle cx="12" cy="5" r="2.2"/><circle cx="5" cy="19" r="2.2"/><circle cx="19" cy="19" r="2.2"/><path d="M12 7.2V12M12 12l-5.5 5.2M12 12l5.5 5.2"/>@break
    @case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
    @case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5h.01"/>@break
    @case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>@break
    @case('phone')<path d="M5 3.5h3.5l1.8 4.5-2.3 1.4a11 11 0 0 0 6.6 6.6l1.4-2.3 4.5 1.8V19a1.5 1.5 0 0 1-1.6 1.5A16.5 16.5 0 0 1 3.5 5.1 1.5 1.5 0 0 1 5 3.5Z"/>@break
    @case('chat')<path d="M4 5.5h16v10.5H9l-5 4Z"/><path d="M8 9.5h8M8 12.5h5"/>@break
    @default<circle cx="12" cy="12" r="8"/>
@endswitch
</svg>
