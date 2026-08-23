<?php

/**
 * OpenAI-compatible chat drivers for Settings → AI providers.
 * POST {api_endpoint}/chat/completions (Helpdesk / Knowledge Hub pattern).
 */
return [
    'default_driver' => 'openai',
    'drivers' => [
        [
            'key' => 'openai',
            'label' => 'OpenAI',
            'description' => 'Official OpenAI API. Keys typically start with sk-…',
            'api_endpoint' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'env_key' => 'OPENAI_API_KEY',
        ],
        [
            'key' => 'gemini',
            'label' => 'Google Gemini',
            'description' => 'Gemini via the OpenAI-compatible Google AI Studio endpoint.',
            'api_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'model' => 'gemini-2.0-flash',
            'env_key' => 'GEMINI_API_KEY',
        ],
        [
            'key' => 'deepseek',
            'label' => 'DeepSeek',
            'description' => 'DeepSeek chat models (OpenAI-compatible).',
            'api_endpoint' => 'https://api.deepseek.com/v1',
            'model' => 'deepseek-chat',
            'env_key' => 'DEEPSEEK_API_KEY',
        ],
        [
            'key' => 'grok',
            'label' => 'xAI Grok',
            'description' => 'xAI Grok models (OpenAI-compatible).',
            'api_endpoint' => 'https://api.x.ai/v1',
            'model' => 'grok-2-latest',
            'env_key' => 'XAI_API_KEY',
        ],
        [
            'key' => 'mistral',
            'label' => 'Mistral',
            'description' => 'Mistral AI chat models (OpenAI-compatible).',
            'api_endpoint' => 'https://api.mistral.ai/v1',
            'model' => 'mistral-small-latest',
            'env_key' => 'MISTRAL_API_KEY',
        ],
        [
            'key' => 'groq',
            'label' => 'Groq',
            'description' => 'Groq inference API (OpenAI-compatible).',
            'api_endpoint' => 'https://api.groq.com/openai/v1',
            'model' => 'llama-3.3-70b-versatile',
            'env_key' => 'GROQ_API_KEY',
        ],
        [
            'key' => 'azure',
            'label' => 'Azure OpenAI',
            'description' => 'Azure OpenAI resource. Use the deployment name as the model and the resource /v1 base URL.',
            'api_endpoint' => '',
            'model' => 'gpt-4o-mini',
            'env_key' => 'AZURE_OPENAI_API_KEY',
        ],
        [
            'key' => 'custom',
            'label' => 'Custom API',
            'description' => 'Any OpenAI-compatible API root (often ends with /v1).',
            'api_endpoint' => '',
            'model' => '',
            'env_key' => '',
        ],
    ],
];
