<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-aa-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-aa-title">{{ $alt ?? 'One DeveloperJet API key connects your tools to models from many AI providers.' }}</title>
    <defs>
        <marker id="aa-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0 10 5 0 10Z" fill="#7C3AED"/></marker>
        <linearGradient id="aa-key" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4F46E5"/><stop offset="1" stop-color="#9333EA"/></linearGradient>
    </defs>
    <rect width="600" height="460" rx="20" fill="#F6F9FC"/>

    {{-- Your tools --}}
    <text x="28" y="44" font-size="12" font-weight="700" fill="#5B6B82">YOUR TOOLS</text>
    <g font-size="13" font-weight="600" fill="#17243B">
        @foreach (['Claude Code', 'Cursor', 'n8n', 'OpenAI SDK', 'Hermes Agent'] as $i => $tool)
            <rect x="24" y="{{ 58 + $i * 52 }}" width="150" height="40" rx="10" fill="#fff" stroke="#D5DEEA"/>
            <circle cx="44" cy="{{ 78 + $i * 52 }}" r="6" fill="#4F46E5" opacity=".85"/>
            <text x="58" y="{{ 83 + $i * 52 }}">{{ $tool }}</text>
            <path d="M178 {{ 78 + $i * 52 }}C210 {{ 78 + $i * 52 }} 214 210 232 210" fill="none" stroke="#C4B5FD" stroke-width="1.6"/>
        @endforeach
    </g>

    {{-- One key --}}
    <g transform="translate(236 150)">
        <rect width="132" height="120" rx="16" fill="url(#aa-key)"/>
        <circle cx="44" cy="48" r="16" fill="none" stroke="#fff" stroke-width="4"/>
        <path d="M60 48h44m-12 0v14m-12-14v10" stroke="#fff" stroke-width="4" stroke-linecap="round"/>
        <text x="66" y="92" font-size="14" font-weight="800" fill="#fff" text-anchor="middle">One API key</text>
        <text x="66" y="108" font-size="11" fill="#E9D5FF" text-anchor="middle">monthly credit</text>
    </g>
    <text x="302" y="300" font-size="11" fill="#5B6B82" text-anchor="middle">OpenAI- &amp; Anthropic-compatible</text>

    {{-- Models --}}
    <text x="412" y="44" font-size="12" font-weight="700" fill="#5B6B82">MODELS</text>
    <g font-size="13" font-weight="600" fill="#17243B">
        @foreach ([['GPT', '#10A37F'], ['Claude', '#D97757'], ['Gemini', '#4285F4'], ['DeepSeek', '#4D6BFE'], ['Qwen', '#6F42C1'], ['Grok', '#111827']] as $i => [$model, $colour])
            <path d="M372 210C392 210 390 {{ 78 + $i * 46 }} 408 {{ 78 + $i * 46 }}" fill="none" stroke="#C4B5FD" stroke-width="1.6" marker-end="url(#aa-arrow)"/>
            <rect x="412" y="{{ 60 + $i * 46 }}" width="164" height="36" rx="10" fill="#fff" stroke="#D5DEEA"/>
            <rect x="424" y="{{ 70 + $i * 46 }}" width="16" height="16" rx="4" fill="{{ $colour }}"/>
            <text x="450" y="{{ 83 + $i * 46 }}">{{ $model }}</text>
        @endforeach
    </g>
    <text x="494" y="352" font-size="11" fill="#5B6B82" text-anchor="middle">…and more</text>

    <g transform="translate(24 392)">
        <rect width="552" height="44" rx="12" fill="#fff" stroke="#D5DEEA"/>
        <text x="20" y="27" font-size="12" fill="#17243B"><tspan font-weight="700">No separate provider accounts.</tspan> Your key and base URL appear in your DeveloperJet dashboard.</text>
    </g>
</svg>
