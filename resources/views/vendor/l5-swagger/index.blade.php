<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentationTitle }}</title>
    <link rel="stylesheet" type="text/css" href="{{ l5_swagger_asset($documentation, 'swagger-ui.css') }}">
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-32x32.png') }}" sizes="32x32"/>
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-16x16.png') }}" sizes="16x16"/>
    <style>
    html
    {
        box-sizing: border-box;
        overflow: -moz-scrollbars-vertical;
        overflow-y: scroll;
    }
    *,
    *:before,
    *:after
    {
        box-sizing: inherit;
    }

    body {
      margin:0;
      background: #fafafa;
    }
    #tts-audio-result:not([hidden]) {
      position: fixed;
      right: 1rem;
      bottom: 1rem;
      z-index: 10000;
      max-width: calc(100vw - 2rem);
      padding: 1rem;
      border: 1px solid #999;
      border-radius: 0.5rem;
      background: #fff;
      color: #222;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .25);
    }
    #tts-audio-result audio {
      display: block;
      max-width: 100%;
    }
    </style>
    @if(config('l5-swagger.defaults.ui.display.dark_mode'))
        <style>
            body#dark-mode,
            #dark-mode .scheme-container {
                background: #1b1b1b;
            }
            #dark-mode .scheme-container,
            #dark-mode .opblock .opblock-section-header{
                box-shadow: 0 1px 2px 0 rgba(255, 255, 255, 0.15);
            }
            #dark-mode .operation-filter-input,
            #dark-mode .dialog-ux .modal-ux,
            #dark-mode input[type=email],
            #dark-mode input[type=file],
            #dark-mode input[type=password],
            #dark-mode input[type=search],
            #dark-mode input[type=text],
            #dark-mode textarea{
                background: #343434;
                color: #e7e7e7;
            }
            #dark-mode .title,
            #dark-mode li,
            #dark-mode p,
            #dark-mode table,
            #dark-mode label,
            #dark-mode .opblock-tag,
            #dark-mode .opblock .opblock-summary-operation-id,
            #dark-mode .opblock .opblock-summary-path,
            #dark-mode .opblock .opblock-summary-path__deprecated,
            #dark-mode h1,
            #dark-mode h2,
            #dark-mode h3,
            #dark-mode h4,
            #dark-mode h5,
            #dark-mode .btn,
            #dark-mode .tab li,
            #dark-mode .parameter__name,
            #dark-mode .parameter__type,
            #dark-mode .prop-format,
            #dark-mode .loading-container .loading:after{
                color: #e7e7e7;
            }
            #dark-mode .opblock-description-wrapper p,
            #dark-mode .opblock-external-docs-wrapper p,
            #dark-mode .opblock-title_normal p,
            #dark-mode .response-col_status,
            #dark-mode table thead tr td,
            #dark-mode table thead tr th,
            #dark-mode .response-col_links,
            #dark-mode .swagger-ui{
                color: wheat;
            }
            #dark-mode .parameter__extension,
            #dark-mode .parameter__in,
            #dark-mode .model-title{
                color: #949494;
            }
            #dark-mode table thead tr td,
            #dark-mode table thead tr th{
                border-color: rgba(120,120,120,.2);
            }
            #dark-mode .opblock .opblock-section-header{
                background: transparent;
            }
            #dark-mode .opblock.opblock-post{
                background: rgba(73,204,144,.25);
            }
            #dark-mode .opblock.opblock-get{
                background: rgba(97,175,254,.25);
            }
            #dark-mode .opblock.opblock-put{
                background: rgba(252,161,48,.25);
            }
            #dark-mode .opblock.opblock-delete{
                background: rgba(249,62,62,.25);
            }
            #dark-mode .loading-container .loading:before{
                border-color: rgba(255,255,255,10%);
                border-top-color: rgba(255,255,255,.6);
            }
            #dark-mode svg:not(:root){
                fill: #e7e7e7;
            }
            #dark-mode .opblock-summary-description {
                color: #fafafa;
            }
        </style>
    @endif
</head>

<body @if(config('l5-swagger.defaults.ui.display.dark_mode')) id="dark-mode" @endif>
<section id="tts-audio-result" hidden aria-live="polite">
    <h2>语音试听结果</h2>
    <audio id="tts-audio-player" controls preload="metadata"></audio>
    <a id="tts-audio-download" download="tts-preview.mp3">下载 MP3</a>
</section>
<div id="swagger-ui"></div>

<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-bundle.js') }}"></script>
<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-standalone-preset.js') }}"></script>
<script>
    window.onload = function() {
        const urls = [];
        const audioResult = document.getElementById('tts-audio-result');
        const audioPlayer = document.getElementById('tts-audio-player');
        const audioDownload = document.getElementById('tts-audio-download');
        let audioUrl = null;

        function clearAudio() {
            audioPlayer.pause();
            audioPlayer.removeAttribute('src');
            audioPlayer.load();
            audioDownload.removeAttribute('href');
            audioResult.hidden = true;
            if (audioUrl !== null) {
                URL.revokeObjectURL(audioUrl);
                audioUrl = null;
            }
        }

        window.addEventListener('pagehide', clearAudio);

        @foreach($urlsToDocs as $title => $url)
            urls.push({name: @json($title), url: @json($url)});
        @endforeach

        // Build a system
        const ui = SwaggerUIBundle({
            dom_id: '#swagger-ui',
            urls: urls,
            "urls.primaryName": @json($documentationTitle),
            operationsSorter: @json($operationsSorter ?? null),
            configUrl: @json($configUrl ?? null),
            validatorUrl: @json($validatorUrl ?? null),
            oauth2RedirectUrl: "{{ route('l5-swagger.'.$documentation.'.oauth2_callback', [], $useAbsolutePath) }}",

            requestInterceptor: function(request) {
                if ((request.method || 'GET').toUpperCase() === 'POST' && new URL(request.url, window.location.href).pathname.endsWith('/api/tts/preview')) {
                    clearAudio();
                }
                request.headers['X-CSRF-TOKEN'] = '{{ csrf_token() }}';
                return request;
            },

            responseInterceptor: function(response) {
                const contentType = response.headers['content-type'] || response.headers['Content-Type'] || '';
                const audioData = response.data || response.text;
                const audioBlob = audioData instanceof Blob ? audioData : (audioData instanceof ArrayBuffer ? new Blob([audioData], {type: 'audio/mpeg'}) : null);
                if (response.status === 200 && new URL(response.url, window.location.href).pathname.endsWith('/api/tts/preview') && contentType.toLowerCase().startsWith('audio/mpeg') && audioBlob && audioBlob.size > 0) {
                    if (audioUrl !== null) {
                        URL.revokeObjectURL(audioUrl);
                    }
                    audioUrl = URL.createObjectURL(audioBlob);
                    audioPlayer.src = audioUrl;
                    audioDownload.href = audioUrl;
                    audioResult.hidden = false;
                }
                return response;
            },

            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],

            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],

            layout: "StandaloneLayout",
            docExpansion : @json(config('l5-swagger.defaults.ui.display.doc_expansion', 'none')),
            deepLinking: true,
            filter: {!! config('l5-swagger.defaults.ui.display.filter') ? 'true' : 'false' !!},
            persistAuthorization: @json((bool) config('l5-swagger.defaults.ui.authorization.persist_authorization')),

        })

        window.ui = ui

        @if(in_array('oauth2', array_column(config('l5-swagger.defaults.securityDefinitions.securitySchemes'), 'type')))
        ui.initOAuth({
            usePkceWithAuthorizationCodeGrant: @json((bool) config('l5-swagger.defaults.ui.authorization.oauth2.use_pkce_with_authorization_code_grant'))
        })
        @endif
    }
</script>
</body>
</html>
