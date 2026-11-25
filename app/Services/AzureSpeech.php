<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AzureSpeech
{
    public const VOICES = [
        'us_female' => ['name' => 'en-US-JennyNeural', 'language' => 'en-US', 'label' => '美式女声'],
        'us_male' => ['name' => 'en-US-GuyNeural', 'language' => 'en-US', 'label' => '美式男声'],
        'uk_female' => ['name' => 'en-GB-SoniaNeural', 'language' => 'en-GB', 'label' => '英式女声'],
    ];

    public const SPEEDS = ['slow' => '-20%', 'normal' => '0%', 'fast' => '+20%'];

    public function synthesize(string $text, string $voice, string $speed): string
    {
        if (! isset(self::VOICES[$voice], self::SPEEDS[$speed])) {
            throw new RuntimeException('语音参数无效。');
        }

        $key = config('services.azure_speech.key');
        $region = config('services.azure_speech.region');
        if (! is_string($key) || $key === '' || ! is_string($region) || ! preg_match('/\A[a-z0-9-]+\z/', $region)) {
            throw new RuntimeException('语音服务未配置。');
        }

        $voiceConfig = self::VOICES[$voice];
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="'.$voiceConfig['language'].'"><voice name="'.$voiceConfig['name'].'"><prosody rate="'.self::SPEEDS[$speed].'">'.$escaped.'</prosody></voice></speak>';

        try {
            $response = Http::connectTimeout(5)->timeout(30)->withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
                'X-Microsoft-OutputFormat' => 'audio-24khz-48kbitrate-mono-mp3',
            ])->withBody($ssml, 'application/ssml+xml')->post('https://'.$region.'.tts.speech.microsoft.com/cognitiveservices/v1');
        } catch (\Throwable) {
            throw new RuntimeException('语音服务暂时不可用。');
        }

        if (! $response->successful() || ! str_starts_with(strtolower($response->header('Content-Type') ?? ''), 'audio/') || $response->body() === '') {
            throw new RuntimeException('语音合成失败。');
        }

        return $response->body();
    }
}
