@extends('admin.layout')
@section('title', '사용자 관리')
@section('heading', '사용자 관리')

@section('content')
<div class="mb-5 rounded-2xl border border-[#dce6b5] bg-[#f7fadf] p-4 text-sm text-[#405221]">
    <p class="font-bold">역할 변경 기준</p>
    <p class="mt-1 leading-6"><strong>ADMIN</strong>은 사장님 로그인 권한입니다. 역할을 ADMIN으로 바꿔도 매장이 자동 배정되지는 않습니다. ADMIN을 CUSTOMER로 변경하면 보안을 위해 기존 매장 OWNER 연결과 로그인 토큰이 해제됩니다. SUPER_ADMIN은 서버 명령으로만 관리합니다.</p>
</div>

<form class="mb-5 grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-[1fr_180px_150px_auto]">
    <input name="q" value="{{ request('q') }}" placeholder="이름 또는 이메일 검색" class="rounded-xl border border-black/10 px-4 py-2.5">
    <select name="role" class="rounded-xl border border-black/10 px-3">
        <option value="">전체 역할</option>
        @foreach($roles as $role)
            <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>
        @endforeach
    </select>
    <select name="active" class="rounded-xl border border-black/10 px-3">
        <option value="">전체 상태</option>
        <option value="1" @selected(request('active') === '1')>활성</option>
        <option value="0" @selected(request('active') === '0')>정지</option>
    </select>
    <button class="rounded-xl bg-[#173f2e] px-5 py-2.5 font-bold text-white">검색</button>
</form>

<div class="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-[#eef1ea] text-xs uppercase text-[#69756e]">
                <tr>
                    <th class="px-5 py-4">사용자</th>
                    <th class="px-5 py-4">현재 역할</th>
                    <th class="px-5 py-4">OWNER 매장</th>
                    <th class="px-5 py-4">가입일</th>
                    <th class="px-5 py-4">상태</th>
                    <th class="px-5 py-4 text-right">관리</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                @forelse($users as $user)
                    <tr>
                        <td class="px-5 py-4">
                            <p class="font-bold">{{ $user->name }}</p>
                            <p class="text-xs text-[#78837d]">{{ $user->email }}</p>
                        </td>
                        <td class="px-5 py-4">
                            @if($user->role === 'SUPER_ADMIN')
                                <span class="rounded-full bg-[#173f2e] px-2.5 py-1 text-xs font-bold text-white">SUPER_ADMIN</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex min-w-[190px] items-center gap-2" data-confirm="사용자 역할을 변경하시겠습니까? 역할 변경 즉시 기존 로그인 토큰이 해제됩니다.">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" class="min-w-0 flex-1 rounded-lg border border-black/10 px-2 py-2 text-xs font-bold">
                                        <option value="CUSTOMER" @selected($user->role === 'CUSTOMER')>CUSTOMER · 손님</option>
                                        <option value="ADMIN" @selected($user->role === 'ADMIN')>ADMIN · 사장님</option>
                                    </select>
                                    <button class="rounded-lg bg-[#173f2e] px-3 py-2 text-xs font-bold text-white">변경</button>
                                </form>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span class="font-bold">{{ $user->active_owner_stores_count }}</span><span class="text-xs text-[#78837d]">개</span>
                        </td>
                        <td class="px-5 py-4 text-[#66726a]">{{ $user->created_at?->format('Y-m-d') }}</td>
                        <td class="px-5 py-4">
                            <span class="font-bold {{ $user->is_active ? 'text-emerald-700' : 'text-red-600' }}">{{ $user->is_active ? '활성' : '정지' }}</span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            @if($user->role !== 'SUPER_ADMIN')
                                <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="inline" data-confirm="계정 상태를 변경하시겠습니까?">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-lg border border-black/10 px-3 py-2 text-xs font-bold hover:bg-black/5">{{ $user->is_active ? '정지' : '활성화' }}</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">보호됨</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">검색 결과가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $users->links() }}</div>
@endsection
