<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerDocumentationTest extends TestCase
{
    public function test_documentation_is_available_only_in_local_environment(): void
    {
        $this->get('/api/documentation')->assertNotFound();
        $this->get('/docs')->assertNotFound();

        app()['env'] = 'local';

        $this->get('/api/documentation')->assertOk()->assertSee('TTS API')
            ->assertSee('tts-audio-player')->assertSee('tts-audio-download');
    }

    public function test_generated_specification_describes_existing_tts_endpoints(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();
        app()['env'] = 'local';

        $this->getJson('/docs')->assertOk()
            ->assertJsonPath('paths./api/tts/voices.get.summary', '获取音色和语速')
            ->assertJsonPath('paths./api/tts/preview.post.summary', '生成试听音频');
    }
}
