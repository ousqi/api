<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#09090b">
        <title>{{ $categoryDetails['label'] ?? 'Home' }} · 小甜甜影院</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                body { font-family: ui-sans-serif, system-ui, sans-serif; }
            </style>
        @endif
    </head>
    <body class="min-h-screen overflow-x-hidden bg-[#101114] text-zinc-100 selection:bg-sky-300 selection:text-zinc-950">
        <header class="sticky top-0 z-10 border-b border-white/8 bg-[#101114]/95 backdrop-blur">
            <div class="mx-auto flex max-w-[110rem] items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('movies.index') }}" class="flex shrink-0 items-center gap-2 text-sm font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-300">
                    <span class="grid size-7 place-items-center rounded-md bg-sky-400 text-xs font-black text-slate-950" aria-hidden="true">小</span>
                    <span class="hidden sm:inline">小甜甜影院</span>
                </a>
                <nav class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto whitespace-nowrap text-sm [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="主要媒体导航">
                    <a href="{{ route('movies.index') }}" @class(['rounded-md px-3 py-2 font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300', 'bg-sky-400/12 text-sky-200' => request()->routeIs('movies.index'), 'text-zinc-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('movies.index')]) @if (request()->routeIs('movies.index')) aria-current="page" @endif>首页</a>
                    @foreach ($mediaCategories as $key => $definition)
                        @php
                            $isParentActive = $categoryDetails !== null && ($categoryDetails['key'] === $key || $categoryDetails['parent'] === $key);
                            $parentRoute = route($definition['route']);
                            $requiresAdultAuthorization = $key === 'adult' && ! $adultAuthorized;
                        @endphp
                        <a href="{{ $parentRoute }}" @class(['rounded-md px-3 py-2 font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300', 'bg-sky-400/12 text-sky-200' => $isParentActive, 'bg-[#101114] text-[#101114] hover:bg-[#101114] hover:text-[#101114]' => ! $isParentActive && $key === 'adult', 'text-zinc-400 hover:bg-white/5 hover:text-white' => ! $isParentActive && $key !== 'adult']) @if ($isParentActive) aria-current="page" @endif @if ($requiresAdultAuthorization) data-adult-gate data-adult-intended="{{ parse_url($parentRoute, PHP_URL_PATH) }}" @endif>{{ $definition['label'] }}</a>
                    @endforeach
                </nav>
                <div class="hidden shrink-0 items-center gap-3 text-right text-xs sm:flex">
                    @if ($searchTerm === null)
                        <span class="text-zinc-400">已收录 <strong class="font-semibold text-white">{{ count($movies) }}</strong> 部</span>
                        <span class="hidden h-4 w-px bg-white/10 sm:block"></span>
                        <span class="hidden text-zinc-500 sm:inline">最新快取刷新：<time class="text-zinc-300">{{ $refreshedAt ?: '尚未刷新' }}</time></span>
                    @else
                        <span class="text-zinc-400">找到 <strong class="font-semibold text-white">{{ count($movies) }}</strong> 条结果</span>
                    @endif
                </div>
            </div>
            @php
                $contextualParentKey = $categoryDetails['parent'] ?? $categoryDetails['key'] ?? null;
                $contextualParent = is_string($contextualParentKey) ? ($mediaCategories[$contextualParentKey] ?? null) : null;
            @endphp
            @if (is_array($contextualParent) && is_array($contextualParent['children'] ?? null) && $contextualParent['children'] !== [])
                <div class="border-t border-white/6 px-4 py-2 sm:px-6 lg:px-8">
                    <div class="mx-auto flex max-w-[110rem] flex-col gap-2 sm:flex-row sm:items-center">
                        <nav class="max-w-full overflow-x-auto text-xs [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="{{ $contextualParent['label'] }}分类">
                            <div class="flex w-max gap-1.5">
                                <a href="{{ route($contextualParent['route']) }}" @class(['shrink-0 rounded px-2 py-1 font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300', 'bg-sky-400/12 text-sky-200' => $categoryDetails['key'] === $contextualParentKey, 'text-zinc-200 hover:bg-white/8 hover:text-white' => $categoryDetails['key'] !== $contextualParentKey]) @if ($categoryDetails['key'] === $contextualParentKey) aria-current="page" @endif @if ($contextualParentKey === 'adult' && ! $adultAuthorized) data-adult-gate data-adult-intended="{{ parse_url(route($contextualParent['route']), PHP_URL_PATH) }}" @endif>全部{{ $contextualParent['label'] }}</a>
                                @foreach ($contextualParent['children'] as $childKey => $child)
                                    <a href="{{ route($contextualParent['child_route'], ['category' => $childKey]) }}" @class(['shrink-0 rounded px-2 py-1 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300', 'bg-sky-400/12 text-sky-200' => $categoryDetails['key'] === $childKey, 'text-zinc-400 hover:bg-white/8 hover:text-white' => $categoryDetails['key'] !== $childKey]) @if ($categoryDetails['key'] === $childKey) aria-current="page" @endif @if ($contextualParentKey === 'adult' && ! $adultAuthorized) data-adult-gate data-adult-intended="{{ parse_url(route($contextualParent['child_route'], ['category' => $childKey]), PHP_URL_PATH) }}" @endif>{{ $child['label'] }}</a>
                                @endforeach
                            </div>
                        </nav>
                        <form action="{{ url()->current() }}" method="GET" class="flex w-full gap-1 sm:w-72 sm:shrink-0 sm:gap-2">
                            <label for="movie-search" class="sr-only">搜索{{ $categoryDetails['label'] }}</label>
                            <input id="movie-search" name="q" type="search" value="{{ $searchTerm }}" maxlength="100" placeholder="搜索{{ $categoryDetails['label'] }}" class="min-w-0 flex-1 border border-white/10 bg-white/5 px-2 py-1 text-base text-white outline-none placeholder:text-zinc-500 focus:border-sky-300 focus:ring-2 focus:ring-sky-300/30 sm:px-3 sm:py-1.5 sm:text-sm">
                            <button type="submit" class="shrink-0 bg-sky-400 px-2 py-1 text-sm font-bold text-slate-950 transition hover:bg-sky-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300 sm:px-3 sm:py-1.5">搜索</button>
                        </form>
                    </div>
                    <p class="mx-auto mt-2 max-w-[110rem] text-[11px] text-zinc-500 sm:hidden">
                        @if ($searchTerm === null)
                            已收录 {{ count($movies) }} 部 · 最新快取刷新：<time class="text-zinc-300">{{ $refreshedAt ?: '尚未刷新' }}</time>
                        @else
                            找到 {{ count($movies) }} 条结果
                        @endif
                    </p>
                </div>
            @elseif ($categoryDetails !== null)
                <div class="border-t border-white/6 px-4 py-2 sm:px-6 lg:px-8">
                    <form action="{{ url()->current() }}" method="GET" class="mx-auto flex max-w-[110rem] gap-2">
                        <label for="movie-search" class="sr-only">搜索{{ $categoryDetails['label'] }}</label>
                        <input id="movie-search" name="q" type="search" value="{{ $searchTerm }}" maxlength="100" placeholder="搜索{{ $categoryDetails['label'] }}" class="min-w-0 flex-1 border border-white/10 bg-white/5 px-3 py-1.5 text-base text-white outline-none placeholder:text-zinc-500 focus:border-sky-300 focus:ring-2 focus:ring-sky-300/30 sm:max-w-md sm:text-sm">
                        <button type="submit" class="shrink-0 bg-sky-400 px-3 py-1.5 text-sm font-bold text-slate-950 transition hover:bg-sky-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300">搜索</button>
                    </form>
                </div>
            @endif
        </header>

        <main class="mx-auto max-w-[110rem] px-4 py-5 sm:px-6 sm:py-8 lg:px-8">
            @if ($searchTerm === null)
            <section class="relative min-h-[19rem] overflow-hidden border-0 bg-[#1a1b20] shadow-2xl shadow-black/30 sm:min-h-96 sm:border sm:border-white/8" aria-label="精选媒体">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_75%_25%,rgba(56,189,248,0.24),transparent_32%),linear-gradient(120deg,#20232b,#15161a_58%,#102834)]" data-hero-fallback @if ($heroMovies !== []) hidden @endif></div>
                <div class="relative flex min-h-64 max-w-2xl flex-col justify-end p-4 sm:min-h-96 sm:p-10" data-hero-fallback-content @if ($heroMovies !== []) hidden @endif>
                    <p class="text-xs font-bold tracking-[0.2em] text-sky-200">{{ $categoryDetails['type'] ?? '精选推荐' }}</p>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-5xl">{{ $categoryDetails['label'] ?? '小甜甜影院' }}</h1>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-zinc-200 sm:text-base">浏览由 MTeam 刷新指令写入快取的最新媒体种子。点击海报即可查看详情。</p>
                </div>
                @if ($heroMovies === [])
                @else
                    <div class="swiper min-h-[19rem] sm:min-h-96" data-hero-carousel aria-roledescription="carousel" aria-label="精选媒体">
                        <div class="swiper-wrapper">
                            @foreach ($heroMovies as $movie)
                                @php($detailUrl = filled($movie['id'] ?? null) && filled($movie['categoryId'] ?? null) ? route('movies.detail', ['categoryId' => $movie['categoryId'], 'torrent' => $movie['id']]) : null)
                            <article class="swiper-slide relative aspect-[2/3] min-h-[19rem] overflow-hidden border-0 shadow-xl shadow-black/40 sm:min-h-96 sm:aspect-auto sm:shadow-none" data-hero-slide>
                                    <img src="{{ $movie['poster'] }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-100 sm:scale-105 sm:opacity-70 sm:blur-xl" aria-hidden="true">
                                    <div class="absolute inset-0 bg-black/25 sm:bg-[linear-gradient(90deg,rgba(16,17,20,0.97),rgba(16,17,20,0.88)_38%,rgba(16,17,20,0.35)_62%,rgba(16,17,20,0.20)),radial-gradient(circle_at_75%_25%,rgba(56,189,248,0.16),transparent_42%)]"></div>
                                    <div class="relative grid min-h-[19rem] gap-3 p-4 sm:min-h-96 sm:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] sm:items-center sm:gap-10 sm:p-10">
                                        <div class="flex justify-center sm:justify-start">
                                            <button
                                                type="button"
                                                @if ($detailUrl) data-torrent-detail-trigger data-torrent-detail-url="{{ $detailUrl }}" aria-haspopup="dialog" aria-controls="torrent-detail-dialog" aria-label="查看《{{ $movie['title'] }}》详情" @else disabled @endif
                                                class="absolute inset-0 sm:relative sm:block"
                                            >
                                                <img src="{{ $movie['poster'] }}" alt="{{ $movie['title'] }} 海报" class="hidden h-auto w-auto max-w-full border border-white/10 object-contain shadow-2xl shadow-black/40 sm:block sm:max-h-80" data-hero-poster onerror="this.dataset.heroPosterFailed='true';">
                                            </button>
                                        </div>
                                        <div class="hidden flex-col justify-center sm:flex">
                                            <p class="text-xs font-bold tracking-[0.2em] text-sky-200">{{ $categoryDetails['type'] ?? '精选推荐' }}</p>
                                            <h2 class="mt-3 line-clamp-2 text-3xl font-bold tracking-tight text-white sm:h-[7.5rem] sm:text-5xl">{{ $movie['title'] }}</h2>
                                            <p class="mt-4 max-w-xl text-sm leading-6 text-zinc-100 sm:text-base">{{ $movie['description'] ?: '浏览由 MTeam 刷新指令写入快取的最新媒体种子。选择海报可展开查看简介与发布时间。' }}</p>
                                            @if ($movie['time'])
                                                <p class="mt-5 text-xs font-medium text-zinc-200">发布时间：{{ $movie['time'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @if (count($heroMovies) > 1)
                            <div class="absolute inset-x-0 bottom-4 z-10 flex items-center justify-between px-4 sm:bottom-6 sm:px-6" data-hero-controls>
                                <button type="button" class="grid size-9 place-items-center rounded-full border border-white/20 bg-black/30 text-white backdrop-blur transition hover:bg-black/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-hero-previous aria-label="显示上一部精选媒体">‹</button>
                                <div class="!w-auto text-white" data-hero-pagination></div>
                                <button type="button" class="grid size-9 place-items-center rounded-full border border-white/20 bg-black/30 text-white backdrop-blur transition hover:bg-black/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-hero-next aria-label="显示下一部精选媒体">›</button>
                            </div>
                        @endif
                    </div>
                @endif
            </section>
            @endif

                <section @class(['mt-8' => $searchTerm === null]) aria-labelledby="latest-movies-title">
                    <div class="mb-5 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold tracking-[0.2em] text-sky-300">{{ $searchTerm === null ? 'RECENTLY ADDED' : 'SEARCH RESULTS' }}</p>
                            <h2 id="latest-movies-title" class="mt-1 text-2xl font-bold tracking-tight text-white">{{ $searchTerm === null ? ($categoryDetails['label'] ?? '全部媒体') : '“'.$searchTerm.'” 的搜索结果' }}</h2>
                        </div>
                        @if ($searchTerm === null)
                            <p class="hidden text-xs text-zinc-500 sm:block">点击海报查看详情</p>
                        @endif
                    </div>

                    @if ($searchError)
                        <p class="mb-5 border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">{{ $searchError }}</p>
                    @endif

                    @if ($movies === [])
                        <section class="rounded-2xl border border-dashed border-zinc-700 bg-[#1a1b20] px-6 py-20 text-center">
                            <img src="{{ asset('images/movie-poster-placeholder.svg') }}" alt="" class="mx-auto h-24 w-16 opacity-70">
                            <h3 class="mt-6 text-lg font-semibold text-white">{{ $searchTerm === null ? '暂时没有可展示的'.($categoryDetails['label'] ?? '媒体') : '未找到相关媒体' }}</h3>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-400">{{ $searchTerm === null ? '快取将在下次排程执行或运行 mteam:refresh-category 分类 ID 后显示。' : '请调整关键词后重试。' }}</p>
                        </section>
                    @else
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7">
                            @foreach ($movies as $movie)
                                @php($detailUrl = filled($movie['id'] ?? null) && filled($movie['categoryId'] ?? null) ? route('movies.detail', ['categoryId' => $movie['categoryId'], 'torrent' => $movie['id']]) : null)
                                <div class="group relative min-w-0">
                                    <button
                                        type="button"
                                        @if ($detailUrl) data-torrent-detail-trigger data-torrent-detail-url="{{ $detailUrl }}" aria-haspopup="dialog" aria-controls="torrent-detail-dialog" aria-label="查看《{{ $movie['title'] }}》详情" @else disabled @endif
                                        class="group/card relative block cursor-pointer overflow-hidden border border-white/8 bg-[#1a1b20] shadow-lg shadow-black/20 outline-none transition duration-300 hover:-translate-y-1 hover:border-sky-300/70 hover:shadow-sky-950/30 focus-visible:-translate-y-1 focus-visible:border-sky-300 focus-visible:ring-2 focus-visible:ring-sky-300/70"
                                    >
                                        <div class="aspect-[2/3] overflow-hidden bg-zinc-800">
                                            <img
                                                src="{{ $movie['cardPoster'] }}"
                                                alt="{{ $movie['title'] }} 海报"
                                                class="h-full w-full object-cover transition duration-500 group-hover/card:scale-105 group-focus-visible/card:scale-105"
                                                loading="lazy"
                                                decoding="async"
                                                data-fallback-poster="{{ $movie['fallbackPoster'] }}"
                                                onerror="this.onerror=null;this.src=this.dataset.fallbackPoster;"
                                            >
                                        </div>
                                        @if ($movie['isFree'])
                                            <span class="absolute left-2 top-2 bg-emerald-400 px-2 py-1 text-[10px] font-black tracking-wide text-emerald-950 shadow-lg shadow-black/30">FREE</span>
                                        @endif
                                        <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-[#101114] via-[#101114]/85 to-transparent px-3 pb-3 pt-14">
                                            <h3 class="line-clamp-2 text-sm font-bold leading-5 text-white">{{ $movie['title'] }}</h3>
                                            <p class="mt-1 flex min-w-0 gap-1 text-[11px] text-zinc-400">
                                                @if ($movie['sizeLabel'])
                                                    <span class="shrink-0 font-medium text-zinc-200">{{ $movie['sizeLabel'] }}</span>
                                                @endif
                                                <span class="truncate">{{ $movie['time'] ?: '上映时间未知' }}</span>
                                            </p>
                                        </div>
                                        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 max-h-[72%] translate-y-full overflow-y-auto bg-[#101114]/95 px-3 py-3 text-xs leading-5 text-zinc-200 opacity-0 shadow-[0_-12px_24px_rgba(0,0,0,0.35)] transition duration-300 group-hover/card:translate-y-0 group-hover/card:opacity-100 group-focus-visible/card:translate-y-0 group-focus-visible/card:opacity-100">
                                            {{ $movie['description'] ?: '暂无影片简介。' }}
                                        </div>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
        </main>

        <dialog id="torrent-detail-dialog" class="m-auto max-h-[calc(100%-2rem)] w-[calc(100%-2rem)] max-w-3xl overflow-y-auto border border-white/10 bg-[#1a1b20] p-0 text-zinc-100 shadow-2xl shadow-black/70 backdrop:bg-black/75" data-torrent-detail-dialog aria-labelledby="torrent-detail-title">
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold tracking-[0.2em] text-sky-300">TORRENT DETAILS</p>
                        <h2 id="torrent-detail-title" class="mt-1 text-xl font-bold text-white" data-torrent-detail-title>加载详情中…</h2>
                        <p class="mt-1 break-all text-xs text-zinc-400" data-torrent-detail-name></p>
                    </div>
                    <button type="button" class="grid size-8 shrink-0 place-items-center text-zinc-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-torrent-detail-close aria-label="关闭详情">×</button>
                </div>

                <div class="py-14 text-center text-sm text-zinc-300" data-torrent-detail-loading role="status">正在获取影片详情…</div>
                <p class="hidden border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100" data-torrent-detail-error role="alert"></p>

                <div class="hidden mt-6 space-y-6" data-torrent-detail-content>
                    <section>
                        <h3 class="text-sm font-bold text-white">简介</h3>
                        <div class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-zinc-300" data-torrent-detail-description></div>
                    </section>
                    <section class="border-t border-white/10 pt-5" data-torrent-downloads hidden>
                        <h3 class="text-sm font-bold text-white">提交下载</h3>
                        <p class="mt-1 text-xs text-zinc-400">选择要提交此种子下载的 qBittorrent。</p>
                        <div class="mt-3 flex flex-wrap gap-2" data-torrent-download-buttons></div>
                        <p class="mt-3 hidden text-sm text-amber-200" data-torrent-download-error role="alert"></p>
                    </section>
                    <a
                        class="hidden w-fit bg-white/10 px-3 py-2 text-sm font-bold text-zinc-100 transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300"
                        data-torrent-official-link
                        href="#"
                        target="_blank"
                        rel="noopener noreferrer"
                    >官方</a>
                </div>
            </div>
        </dialog>
        <button type="button" class="group fixed bottom-5 right-5 z-20 grid size-12 place-items-center rounded-full border border-sky-200/50 bg-sky-400 text-slate-950 shadow-lg shadow-sky-950/50 transition hover:-translate-y-0.5 hover:bg-sky-300 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-qbittorrent-status-trigger aria-haspopup="dialog" aria-controls="qbittorrent-status-dialog" aria-label="查看下载状态">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" class="size-5 transition group-hover:translate-y-0.5" aria-hidden="true">
                <path d="M12 3v11"></path>
                <path d="m8 10 4 4 4-4"></path>
                <path d="M5 17v3h14v-3"></path>
            </svg>
        </button>
        <dialog id="qbittorrent-status-dialog" class="m-auto max-h-[calc(100%-2rem)] w-[calc(100%-2rem)] max-w-3xl overflow-y-auto border border-white/10 bg-[#1a1b20] p-0 text-zinc-100 shadow-2xl shadow-black/70 backdrop:bg-black/75" data-qbittorrent-status-dialog aria-labelledby="qbittorrent-status-title">
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-sky-300">DOWNLOAD STATUS</p>
                        <h2 id="qbittorrent-status-title" class="mt-1 text-xl font-bold text-white">下载状态</h2>
                    </div>
                    <button type="button" class="grid size-8 shrink-0 place-items-center text-zinc-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-qbittorrent-status-close aria-label="关闭">×</button>
                </div>
                <div class="py-12 text-center text-sm text-zinc-300" data-qbittorrent-status-loading hidden>正在获取下载状态…</div>
                <p class="hidden border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100" data-qbittorrent-status-error role="alert"></p>
                <div class="mt-6 space-y-6" data-qbittorrent-status-content></div>
            </div>
        </dialog>

        <dialog class="m-auto w-[calc(100%-2rem)] max-w-md border border-white/10 bg-[#1a1b20] p-0 text-zinc-100 shadow-2xl shadow-black/70 backdrop:bg-black/75" data-adult-dialog @if ($adultAuthorizationRequired || $errors->has('password')) data-adult-gate-open @endif aria-labelledby="adult-access-title">
            <form action="{{ route('adult-access.store') }}" method="POST" class="p-5 sm:p-6">
                @csrf
                <input type="hidden" name="intended" value="{{ old('intended', session('adult.intended_url')) }}" data-adult-intended>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-sky-300">RESTRICTED ACCESS</p>
                        <h2 id="adult-access-title" class="mt-1 text-xl font-bold text-white">访问验证</h2>
                    </div>
                    <button type="button" class="grid size-8 shrink-0 place-items-center text-zinc-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-adult-dialog-close aria-label="关闭">×</button>
                </div>
                <label for="adult-password" class="mt-5 block text-sm font-medium text-zinc-200">密码</label>
                <input id="adult-password" name="password" type="password" required autofocus autocomplete="current-password" class="mt-2 block w-full border border-white/10 bg-[#101114] px-3 py-2.5 text-base text-white outline-none placeholder:text-zinc-500 focus:border-sky-300 focus:ring-2 focus:ring-sky-300/30" @if ($errors->has('password')) aria-invalid="true" aria-describedby="adult-password-error" @endif>
                @error('password')
                    <p id="adult-password-error" class="mt-2 text-sm text-amber-200">{{ $message }}</p>
                @enderror
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-semibold text-zinc-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-adult-dialog-close>取消</button>
                    <button type="submit" class="bg-sky-400 px-4 py-2 text-sm font-bold text-slate-950 transition hover:bg-sky-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300">验证并访问</button>
                </div>
            </form>
        </dialog>
        <dialog class="m-auto w-[calc(100%-2rem)] max-w-sm border border-white/10 bg-[#1a1b20] p-0 text-zinc-100 shadow-2xl shadow-black/70 backdrop:bg-black/75" data-qbittorrent-auth-dialog aria-labelledby="qbittorrent-auth-title">
            <form class="p-5 sm:p-6" data-qbittorrent-auth-form>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-sky-300">DOWNLOAD AUTHORIZATION</p>
                        <h2 id="qbittorrent-auth-title" class="mt-1 text-xl font-bold text-white">下载授权</h2>
                    </div>
                    <button type="button" class="grid size-8 shrink-0 place-items-center text-zinc-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-qbittorrent-auth-close aria-label="关闭">×</button>
                </div>
                <p class="mt-4 text-sm leading-6 text-zinc-300">输入密码后可在 30 分钟内提交下载。</p>
                <label for="qbittorrent-download-password" class="mt-5 block text-sm font-medium text-zinc-200">密码</label>
                <input id="qbittorrent-download-password" name="password" type="password" required autocomplete="current-password" class="mt-2 block w-full border border-white/10 bg-[#101114] px-3 py-2.5 text-base text-white outline-none focus:border-sky-300 focus:ring-2 focus:ring-sky-300/30" data-qbittorrent-auth-password>
                <p class="mt-3 hidden text-sm text-amber-200" data-qbittorrent-auth-error role="alert"></p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-semibold text-zinc-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-qbittorrent-auth-close>取消</button>
                    <button type="submit" class="bg-sky-400 px-4 py-2 text-sm font-bold text-slate-950 transition hover:bg-sky-300 disabled:cursor-wait disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" data-qbittorrent-auth-submit>授权下载</button>
                </div>
            </form>
        </dialog>
    </body>
</html>
