<?php

/*
| AI All-Access — providers we PLAN to support through each provider's official API (bring your own key).
| Status must stay 'planned' until the integration is built, tested and released. Never list consumer
| subscriptions (ChatGPT Plus, Claude Pro, …) as included. Names are used only to identify the providers;
| no logos are shipped because no licensed brand assets have been supplied.
*/
return [
    'providers' => [
        'openai' => [
            'product' => 'ChatGPT',
            'company' => 'OpenAI',
            'monogram' => 'OA',
            'colour' => '#0F766E',
            'api' => 'OpenAI API',
            'key_from' => 'An API key created in your OpenAI developer platform account.',
            'good_for' => 'General writing, coding help and analysis with OpenAI’s GPT model family.',
            'status' => 'planned',
        ],
        'anthropic' => [
            'product' => 'Claude',
            'company' => 'Anthropic',
            'monogram' => 'An',
            'colour' => '#B45309',
            'api' => 'Anthropic API (Claude)',
            'key_from' => 'An API key created in your Anthropic Console account.',
            'good_for' => 'Long documents, careful reasoning and coding with Anthropic’s Claude models.',
            'status' => 'planned',
        ],
        'google' => [
            'product' => 'Gemini',
            'company' => 'Google',
            'monogram' => 'G',
            'colour' => '#1D4ED8',
            'api' => 'Gemini API',
            'key_from' => 'An API key for the Gemini API from your Google account (for example via Google AI Studio).',
            'good_for' => 'Multimodal work and long context with Google’s Gemini models.',
            'status' => 'planned',
        ],
        'xai' => [
            'product' => 'Grok',
            'company' => 'xAI',
            'monogram' => 'xA',
            'colour' => '#0B1736',
            'api' => 'xAI API',
            'key_from' => 'An API key created in your xAI developer console account.',
            'good_for' => 'Conversational answers with xAI’s Grok models.',
            'status' => 'planned',
        ],
    ],

    // How providers differ — shown to help customers choose a model. Kept general on purpose: limits and prices
    // change often and are set by each provider, so we link customers to the provider rather than quoting numbers.
    'differences' => [
        ['Models', 'Each provider offers its own model family. The models you can pick depend on what your API key can access.'],
        ['Pricing', 'Every provider bills usage to your own account under its own price list. DeveloperJet shows no model prices and adds no mark-up.'],
        ['Limits', 'Context length, rate limits and usage quotas are set by the provider and can differ by account tier.'],
        ['Data handling', 'Each provider has its own API terms and data-use policy. Review them before connecting a key.'],
    ],
];
