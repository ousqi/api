<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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
                        @endphp
                        <a href="{{ $parentRoute }}" @class(['rounded-md px-3 py-2 font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300', 'bg-sky-400/12 text-sky-200' => $isParentActive, 'bg-[#101114] text-[#101114] hover:bg-[#101114] hover:text-[#101114]' => ! $isParentActive && $key === 'adult', 'text-zinc-400 hover:bg-white/5 hover:text-white' => ! $isParentActive && $key !== 'adult']) @if ($isParentActive) aria-current="page" @endif>{{ $definition['label'] }}</a>
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
                                <a href="{{ route($contextualParent['route']) }}" @class(['shrink-0 rounded px-2 py-1 font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300', 'bg-sky-400/12 text-sky-200' => $categoryDetails['key'] === $contextualParentKey, 'text-zinc-200 hover:bg-white/8 hover:text-white' => $categoryDetails['key'] !== $contextualParentKey]) @if ($categoryDetails['key'] === $contextualParentKey) aria-current="page" @endif>全部{{ $contextualParent['label'] }}</a>
                                @foreach ($contextualParent['children'] as $childKey => $child)
                                    <a href="{{ route($contextualParent['child_route'], ['category' => $childKey]) }}" @class(['shrink-0 rounded px-2 py-1 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300', 'bg-sky-400/12 text-sky-200' => $categoryDetails['key'] === $childKey, 'text-zinc-400 hover:bg-white/8 hover:text-white' => $categoryDetails['key'] !== $childKey]) @if ($categoryDetails['key'] === $childKey) aria-current="page" @endif>{{ $child['label'] }}</a>
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
                    <p class="mt-4 max-w-xl text-sm leading-6 text-zinc-200 sm:text-base">浏览由 MTeam 刷新指令写入快取的最新媒体种子。点击海报可在新页面查看详情。</p>
                </div>
                @if ($heroMovies === [])
                @else
                    <div class="swiper min-h-[19rem] sm:min-h-96" data-hero-carousel aria-roledescription="carousel" aria-label="精选媒体">
                        <div class="swiper-wrapper">
                            @foreach ($heroMovies as $movie)
                                @php($detailUrl = filled($movie['id'] ?? null) ? 'https://kp.m-team.cc/detail/'.rawurlencode((string) $movie['id']) : null)
                            <article class="swiper-slide relative aspect-[2/3] min-h-[19rem] overflow-hidden border-0 shadow-xl shadow-black/40 sm:min-h-96 sm:aspect-auto sm:shadow-none" data-hero-slide>
                                    <img src="{{ $movie['poster'] }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-100 sm:scale-105 sm:opacity-70 sm:blur-xl" aria-hidden="true">
                                    <div class="absolute inset-0 bg-black/25 sm:bg-[linear-gradient(90deg,rgba(16,17,20,0.97),rgba(16,17,20,0.88)_38%,rgba(16,17,20,0.35)_62%,rgba(16,17,20,0.20)),radial-gradient(circle_at_75%_25%,rgba(56,189,248,0.16),transparent_42%)]"></div>
                                    <div class="relative grid min-h-[19rem] gap-3 p-4 sm:min-h-96 sm:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] sm:items-center sm:gap-10 sm:p-10">
                                        <div class="flex justify-center sm:justify-start">
                                            <a
                                                @if ($detailUrl) href="{{ $detailUrl }}" target="_blank" rel="noopener noreferrer" aria-label="在新页面查看《{{ $movie['title'] }}》详情" @endif
                                                class="absolute inset-0 sm:relative sm:block"
                                            >
                                                <img src="{{ $movie['poster'] }}" alt="{{ $movie['title'] }} 海报" class="hidden h-auto w-auto max-w-full border border-white/10 object-contain shadow-2xl shadow-black/40 sm:block sm:max-h-80" data-hero-poster onerror="this.dataset.heroPosterFailed='true';">
                                            </a>
                                        </div>
                                        <div class="hidden flex-col justify-center sm:flex">
                                            <p class="text-xs font-bold tracking-[0.2em] text-sky-200">{{ $categoryDetails['type'] ?? '精选推荐' }}</p>
                                            <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-5xl">{{ $movie['title'] }}</h2>
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
                            <p class="hidden text-xs text-zinc-500 sm:block">点击海报可在新页面查看详情</p>
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
                                @php($detailUrl = filled($movie['id'] ?? null) ? 'https://kp.m-team.cc/detail/'.rawurlencode((string) $movie['id']) : null)
                                <div class="group relative min-w-0">
                                    <a
                                        @if ($detailUrl) href="{{ $detailUrl }}" target="_blank" rel="noopener noreferrer" aria-label="在新页面查看《{{ $movie['title'] }}》详情" @endif
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
                                        <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-[#101114] via-[#101114]/85 to-transparent px-3 pb-3 pt-14">
                                            <h3 class="line-clamp-2 text-sm font-bold leading-5 text-white">{{ $movie['title'] }}</h3>
                                            <p class="mt-1 truncate text-[11px] text-zinc-400">{{ $movie['time'] ?: '上映时间未知' }}</p>
                                        </div>
                                        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 max-h-[72%] translate-y-full overflow-y-auto bg-[#101114]/95 px-3 py-3 text-xs leading-5 text-zinc-200 opacity-0 shadow-[0_-12px_24px_rgba(0,0,0,0.35)] transition duration-300 group-hover/card:translate-y-0 group-hover/card:opacity-100 group-focus-visible/card:translate-y-0 group-focus-visible/card:opacity-100">
                                            {{ $movie['description'] ?: '暂无影片简介。' }}
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
        </main>
    </body>
</html>
