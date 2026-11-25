<?php

namespace App\Http\Controllers;

use App\Services\AzureSpeech;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use RuntimeException;

#[OA\Info(version: '1.0.0', title: 'TTS API', description: '本地环境无需登录即可调试；其他环境使用 Passport 令牌认证。')]
class TtsController extends Controller
{
    #[OA\Get(
        path: '/api/tts/voices',
        summary: '获取音色和语速',
        tags: ['TTS'],
        responses: [
            new OA\Response(response: 200, description: '音色和语速列表'),
            new OA\Response(response: 401, description: '未认证'),
            new OA\Response(response: 429, description: '请求过于频繁'),
        ],
    )]
    public function voices(): JsonResponse
    {
        return response()->json(['voices' => AzureSpeech::VOICES, 'speeds' => array_keys(AzureSpeech::SPEEDS)]);
    }

    #[OA\Post(
        path: '/api/tts/preview',
        summary: '生成试听音频',
        description: '接口不需要 CSRF token；本地环境无需登录，其他环境需要 Passport 令牌。Azure Speech 未配置时返回 502。',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['text', 'voice', 'speed'],
            properties: [
                new OA\Property(property: 'text', type: 'string', maxLength: 200, example: 'apple'),
                new OA\Property(property: 'voice', type: 'string', enum: ['us_female', 'us_male', 'uk_female'], example: 'us_female'),
                new OA\Property(property: 'speed', type: 'string', enum: ['slow', 'normal', 'fast'], example: 'normal'),
            ],
        )),
        tags: ['TTS'],
        responses: [
            new OA\Response(response: 200, description: 'MP3 音频', content: new OA\MediaType(mediaType: 'audio/mpeg', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: '未认证'),
            new OA\Response(response: 419, description: 'CSRF token 无效'),
            new OA\Response(response: 422, description: '请求参数无效'),
            new OA\Response(response: 429, description: '请求过于频繁'),
            new OA\Response(response: 502, description: '语音服务不可用'),
        ],
    )]
    public function preview(Request $request, AzureSpeech $speech): Response|JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:200'],
            'voice' => ['required', Rule::in(array_keys(AzureSpeech::VOICES))],
            'speed' => ['required', Rule::in(array_keys(AzureSpeech::SPEEDS))],
        ]);

        try {
            $audio = $speech->synthesize($data['text'], $data['voice'], $data['speed']);
        } catch (RuntimeException) {
            return response()->json(['message' => '语音合成暂时不可用。'], 502);
        }

        return response($audio, 200, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'no-store']);
    }
}
