<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', '관리자') · CafeOn</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f6f2] text-[#17221c] antialiased">
<div class="min-h-screen lg:grid lg:grid-cols-[250px_1fr]">
    <aside class="bg-[#102b20] text-white lg:min-h-screen">
        <div class="flex items-center justify-between px-6 py-6 lg:block">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-[#d8f06a] font-black text-[#102b20]">C</span>
                <span><strong class="block text-lg">CafeOn</strong><small class="text-white/55">System Console</small></span>
            </a>
            <span class="rounded-full bg-white/10 px-3 py-1 text-xs lg:mt-6 lg:inline-block">SUPER ADMIN</span>
        </div>
        <nav class="flex gap-1 overflow-x-auto px-3 pb-4 lg:block lg:space-y-1" aria-label="관리자 메뉴">
            @php
                $nav = [
                    ['admin.dashboard', '운영 개요', '01'],
                    ['admin.users', '사용자', '02'],
                    ['admin.stores', '매장', '03'],
                    ['admin.commerce', '주문 · 예약', '04'],
                    ['admin.moderation', '리뷰 · 문의', '05'],
                    ['admin.system', '시스템', '06'],
                ];
            @endphp
            @foreach($nav as [$routeName, $label, $number])
                <a href="{{ route($routeName) }}" class="flex shrink-0 items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs($routeName) ? 'bg-[#d8f06a] text-[#102b20]' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                    <span class="text-[10px] opacity-60">{{ $number }}</span>{{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="hidden px-6 py-6 lg:block">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-white/60">
                <p class="font-semibold text-white">{{ auth()->user()->name }}</p>
                <p class="mt-1 truncate">{{ auth()->user()->email }}</p>
                <form method="POST" action="{{ route('admin.logout') }}" class="mt-4">@csrf
                    <button class="text-[#d8f06a] hover:underline">안전하게 로그아웃</button>
                </form>
            </div>
        </div>
    </aside>
    <main class="min-w-0">
        <header class="flex items-center justify-between border-b border-black/5 bg-white/70 px-5 py-4 backdrop-blur md:px-8">
            <div><p class="text-xs font-bold uppercase tracking-[.18em] text-[#617168]">CafeOn Operations</p><h1 class="mt-1 text-xl font-black">@yield('heading', '관리자')</h1></div>
            <form method="POST" action="{{ route('admin.logout') }}" class="lg:hidden">@csrf<button class="rounded-lg border px-3 py-2 text-sm">로그아웃</button></form>
        </header>
        <div class="p-5 md:p-8">
            @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
