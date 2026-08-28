<!DOCTYPE html>
<html lang="ko">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>CafeOn 관리자 로그인</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen bg-[#102b20] text-[#17221c] antialiased">
<main class="grid min-h-screen place-items-center p-5">
    <div class="w-full max-w-md rounded-[2rem] bg-[#f7f8f4] p-8 shadow-2xl md:p-10">
        <div class="mb-8"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-[#d8f06a] text-xl font-black">C</span><p class="mt-6 text-xs font-bold uppercase tracking-[.2em] text-[#6b766f]">Restricted access</p><h1 class="mt-2 text-3xl font-black">운영 관리자 로그인</h1><p class="mt-3 text-sm leading-6 text-[#66726a]">CafeOn 시스템 운영 권한이 있는 계정만 접근할 수 있습니다.</p></div>
        @if($errors->any())<div class="mb-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('admin.authenticate') }}" class="space-y-5">@csrf
            <label class="block"><span class="mb-2 block text-sm font-bold">관리자 이메일</span><input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 outline-none ring-[#9eb934] focus:ring-2"></label>
            <label class="block"><span class="mb-2 block text-sm font-bold">비밀번호</span><input name="password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 outline-none ring-[#9eb934] focus:ring-2"></label>
            <label class="flex items-center gap-2 text-sm text-[#66726a]"><input name="remember" type="checkbox" value="1" class="rounded"> 로그인 유지</label>
            <button class="w-full rounded-xl bg-[#173f2e] px-4 py-3.5 font-bold text-white transition hover:bg-[#0e2e20]">관리 콘솔 열기</button>
        </form>
        <p class="mt-6 text-center text-xs text-[#7b867f]">접속과 변경 작업은 감사 로그에 기록됩니다.</p>
    </div>
</main>
</body></html>
