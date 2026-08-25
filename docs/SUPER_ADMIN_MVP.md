# CafeOn SUPER_ADMIN 운영 콘솔 MVP

## 접속

- 주소: `http://127.0.0.1:8000/admin/login`
- 권한: `users.role = SUPER_ADMIN`이며 `is_active = true`인 계정만 허용
- 일반 `ADMIN`, 점주, 고객 계정은 접근 불가

## 제공 기능

1. 운영 개요: 사용자·매장·주문·예약·당일 매출·미답변 문의 지표
2. 사용자 관리: 이름/이메일 검색, 역할/활성 상태 필터, CUSTOMER·ADMIN 역할 변경, 계정 활성·정지
3. 매장 관리: 매장/주소 검색, 활성 상태 필터, 운영 활성·정지
4. 주문·예약: 주문 검색/상태 필터, 예약 상태 필터, 결제 결과 요약
5. 리뷰·문의: 리뷰 공개 상태 변경, 고객 문의 답변 및 종료
6. 시스템: DB·캐시·저장소·지도/소셜 설정 상태와 관리자 감사 로그 확인

## 보안 기준

- Laravel 세션 인증 및 CSRF 보호
- 로그인 분당 5회 제한
- 비활성 SUPER_ADMIN 로그인 차단
- 사용자 정지 시 발급된 Sanctum 토큰 폐기
- 자기 자신과 다른 SUPER_ADMIN 계정의 화면 내 정지 방지
- SUPER_ADMIN 역할은 서버 명령으로만 관리하고 화면에서는 CUSTOMER·ADMIN만 변경
- 역할 변경 시 기존 로그인 토큰 폐기, ADMIN을 CUSTOMER로 변경하면 활성 OWNER 연결 해제
- 관리자 로그인·로그아웃·상태 변경·답변 처리 감사 로그 기록

## 운영 명령

```bash
php artisan migrate --force
php artisan admin:create admin@example.com --name="CafeOn 시스템 관리자"
npm install
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

다른 컴퓨터에서 접속할 때는 `127.0.0.1` 대신 서버 컴퓨터의 내부 IP를 사용하고 Windows 방화벽에서 8000번 포트를 허용합니다. 인터넷 공개 운영 환경에서는 HTTPS와 웹 서버(Nginx/Apache)를 사용해야 합니다.
