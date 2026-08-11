<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CafeON MVC 검증 - {{ $title }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f1eb;color:#29251f;font-family:Arial,"Malgun Gothic",sans-serif}.wrap{max-width:1280px;margin:auto;padding:28px}.head{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:18px}.badge{background:#392f28;color:#fff;border-radius:999px;padding:8px 13px;font-size:13px}.nav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px}.nav a{color:#59483d;text-decoration:none;background:#fff;border:1px solid #ded6cc;padding:9px 13px;border-radius:9px}.nav a.on{background:#9a613c;color:#fff;border-color:#9a613c}.panel{background:#fff;border:1px solid #ded6cc;border-radius:14px;overflow:hidden;box-shadow:0 5px 18px #392f2810}.title{padding:20px 22px;border-bottom:1px solid #eee7df}.title h1{font-size:23px;margin:0 0 6px}.title p{margin:0;color:#756b63;font-size:14px}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{text-align:left;padding:13px 15px;border-bottom:1px solid #eee7df;white-space:nowrap}th{background:#faf8f5;color:#675a50;font-size:13px}td{font-size:14px}.empty{text-align:center;padding:70px 20px;color:#81776f}.foot{margin-top:16px;color:#796e65;font-size:13px}.ok{color:#26734d;font-weight:700}
    </style>
</head>
<body>
<div class="wrap">
    <div class="head"><div><strong>CafeON</strong> · Laravel MVC 검증</div><span class="badge">LOCAL ONLY</span></div>
    @php($links = ['index'=>'홈','stores'=>'매장','menus'=>'메뉴','users'=>'회원·인증','reservations'=>'예약','orders'=>'주문','benefits'=>'쿠폰·포인트','reviews'=>'리뷰','dashboard'=>'사장 대시보드','blogApi'=>'블로그 API 테스트'])
    <nav class="nav">
        @foreach($links as $key => $label)<a class="{{ $active === $key ? 'on' : '' }}" href="{{ route('test.mvc.'.$key) }}">{{ $label }}</a>@endforeach
    </nav>
    <section class="panel">
        <div class="title"><h1>{{ $title }}</h1><p><span class="ok">Controller → Model → MySQL → Blade</span>{{ $note ? ' · '.$note : '' }}</p></div>
        @if(count($rows))
            <div class="scroll"><table><thead><tr>@foreach(array_keys($rows[0]) as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
            <tbody>@foreach($rows as $row)<tr>@foreach($row as $value)<td>{{ $value ?? '-' }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        @else
            <div class="empty">조회된 데이터가 없습니다. MVC 연결은 정상이며 DB에 테스트 데이터를 추가하면 표시됩니다.</div>
        @endif
    </section>
    <div class="foot">이 화면은 APP_ENV=local일 때만 등록되는 백엔드 검증용 View입니다. Next.js 서비스 화면과는 분리되어 있습니다.</div>
</div>
</body>
</html>
