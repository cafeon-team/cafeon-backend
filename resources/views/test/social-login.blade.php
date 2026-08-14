<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CafeOn 소셜 로그인 테스트</title>
    <style>
        body{margin:0;background:#f7f3ed;font-family:Arial,sans-serif;color:#29231f;display:grid;place-items:center;min-height:100vh}
        .card{width:min(420px,calc(100% - 40px));background:#fff;padding:32px;border-radius:20px;box-shadow:0 14px 38px #39291b20}
        .logo{color:#8a4f2d;font-size:26px;font-weight:800}.desc{color:#716861;line-height:1.6;margin:10px 0 26px}
        .btn{display:block;text-align:center;text-decoration:none;color:#191919;font-weight:700;padding:15px;border-radius:10px;margin-top:12px}
        .kakao{background:#fee500}.naver{background:#03c75a;color:#fff}.google{background:#fff;border:1px solid #d8d8d8}
        .flow{margin-top:28px;padding-top:20px;border-top:1px solid #eee;font-size:13px;line-height:1.7;color:#746b65}
    </style>
</head>
<body>
<main class="card">
    <div class="logo">CafeOn</div>
    <p class="desc">카카오, 네이버 또는 구글 계정으로 로그인하는 백엔드 연동 테스트 화면입니다.</p>
    <a class="btn kakao" href="{{ route('social.redirect', 'kakao') }}">카카오로 로그인</a>
    <a class="btn naver" href="{{ route('social.redirect', 'naver') }}">네이버로 로그인</a>
    <a class="btn google" href="{{ route('social.redirect', 'google') }}">Google로 로그인</a>
    <div class="flow">OAuth 인증 → 백엔드 콜백 → 사용자 계정 연결 → 일회용 코드 → Sanctum 토큰 발급</div>
</main>
</body>
</html>
