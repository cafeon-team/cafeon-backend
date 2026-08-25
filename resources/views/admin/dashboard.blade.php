@extends('admin.layout')
@section('title','운영 개요') @section('heading','오늘의 CafeOn')
@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
@foreach([
 ['사용자',$metrics['users'],'오늘 +'.number_format($metrics['newUsers'])],
 ['활성 매장',$metrics['activeStores'],number_format($metrics['stores']).'개 중'],
 ['오늘 결제 매출',number_format($metrics['todaySales']).'원',number_format($metrics['orders']).'건 누적 주문'],
 ['미답변 문의',$metrics['pendingInquiries'],number_format($metrics['reservations']).'건 누적 예약']
] as [$label,$value,$note])
<article class="rounded-2xl border border-black/5 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#728078]">{{ $label }}</p><p class="mt-3 text-3xl font-black tracking-tight">{{ $value }}</p><p class="mt-2 text-xs text-[#89948e]">{{ $note }}</p></article>
@endforeach
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
<section class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm"><div class="mb-5 flex items-center justify-between"><h2 class="text-lg font-black">최근 가입자</h2><a href="{{ route('admin.users') }}" class="text-sm font-bold text-[#527128]">전체 보기 →</a></div><div class="divide-y divide-black/5">@forelse($recentUsers as $user)<div class="flex items-center justify-between py-3"><div><p class="font-bold">{{ $user->name }}</p><p class="text-xs text-[#758079]">{{ $user->email }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $user->role }}</span></div>@empty<p class="py-8 text-center text-sm text-gray-500">사용자가 없습니다.</p>@endforelse</div></section>
<section class="rounded-2xl bg-[#173f2e] p-6 text-white shadow-sm"><h2 class="text-lg font-black">주문 상태</h2><div class="mt-5 space-y-3">@forelse($orderStatuses as $status=>$total)<div class="flex items-center justify-between border-b border-white/10 pb-3"><span class="text-sm text-white/65">{{ $status }}</span><strong>{{ number_format($total) }}</strong></div>@empty<p class="text-sm text-white/60">주문 데이터가 없습니다.</p>@endforelse</div></section>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
<section class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm"><h2 class="mb-4 text-lg font-black">최근 매장</h2><div class="space-y-3">@foreach($recentStores as $store)<div class="flex items-center justify-between rounded-xl bg-[#f7f8f4] p-3"><div><p class="font-bold">{{ $store->name }}</p><p class="text-xs text-[#758079]">{{ $store->address ?: '주소 미등록' }}</p></div><span class="h-2.5 w-2.5 rounded-full {{ $store->is_active ? 'bg-emerald-500' : 'bg-red-400' }}"></span></div>@endforeach</div></section>
<section class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm"><h2 class="mb-4 text-lg font-black">관리 작업 기록</h2><div class="space-y-3">@forelse($recentAudits as $audit)<div class="flex justify-between gap-4 text-sm"><div><p class="font-semibold">{{ $audit->action }}</p><p class="text-xs text-[#758079]">{{ $audit->admin?->name ?? '시스템' }} · {{ $audit->target_type }} #{{ $audit->target_id }}</p></div><time class="shrink-0 text-xs text-[#89948e]">{{ $audit->created_at?->format('m-d H:i') }}</time></div>@empty<p class="text-sm text-gray-500">기록된 작업이 없습니다.</p>@endforelse</div></section>
</div>
@endsection
