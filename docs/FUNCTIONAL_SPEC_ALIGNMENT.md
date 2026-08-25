# CafeOn 기능명세서 백엔드 정합성 보고서

- 기준 문서: `CafeOn_기능명세서_백엔드전달용.docx`
- 점검일: 2026-08-19
- 대상: Laravel API, 로컬 MySQL `cafeon`, Swagger

## 반영 완료

| 항목 | 결과 |
|---|---|
| 손님/사장님 로그인 분리 | `/api/auth/customer/login`, `/api/auth/owner/login` 제공 |
| 소셜 로그인 역할 구분 | 시작 URL의 `role`을 처리하며, 토큰 교환의 선택적 `role`도 검증 |
| 매장 프로필 수정 | `PATCH /api/owner/stores/{store}` 추가 |
| 영업시간 저장 | 매장 수정 API의 `business_hours` 배열을 기존 `store_business_hours` 테이블에 요일별 저장 |
| 사업자 정보 저장 | 매장 수정 API의 `business_info` 객체와 `stores.business_info` JSON 컬럼 추가 |
| 매장 태그 관리 | 매장 수정 API의 `tags` 배열로 매장 태그 전체 동기화 |
| 사장님 프로필 CRUD | 가입 시 생성, 전용 GET/PATCH/DELETE 제공; `store_members.user_id` 소유권 필수 |
| 손님 생년월일 | 사용자 수정 API의 `birth_date` 필드와 `users.birth_date` 컬럼 추가 |
| 영업 상태 | DB에 `stores.is_open` 추가, `PATCH /api/owner/stores/{store}/business-status` 추가 |
| 기존 프론트 영업 토글 호환 | `PATCH /api/owner/stores/{store}/availability`의 `{is_active}` 요청을 `is_open`으로 처리 |
| 좌석 관리 | 목록 GET, 생성 POST, 단건 PATCH, 삭제 DELETE 제공 |
| 대시보드 매출 의미 | 09시부터 현재 시각까지 시간별 누적 매출로 통일하고 `sales_meta` 제공 |
| Swagger | 신규 경로를 포함해 재생성 |
| 카카오 주변 카페 | 카카오 Local API `CE7` 프록시 `/api/map/kakao-cafes` 추가; 키 보호·5분 캐시·분당 60회 제한 |

## 명세 정정 및 확인 결과

### 좌석 일괄 변경

`PATCH /api/owner/stores/{store}/availability`의 원래 계약은 다음과 같은 좌석 일괄 변경이다.

```json
{
  "seats": [
    {"id": 1, "status": "AVAILABLE"}
  ]
}
```

프론트 호환을 위해 `{ "is_active": true }`도 받지만, 신규 프론트 코드는 의미가 분명한 `/business-status`와 `{ "is_open": true }`를 사용해야 한다.

### 이미지 업로드

`POST /api/uploads/images`는 이미 다음 형태를 반환한다.

```json
{
  "path": "blog/example.jpg",
  "url": "http://.../storage/blog/example.jpg"
}
```

프론트는 `url`을 우선 사용하면 된다.

### 포인트 및 쿠폰 사용

별도 임의 차감 API 대신 주문 생성 시 함께 처리한다.

```json
{
  "store_id": 1,
  "point_used": 500,
  "user_coupon_id": 1,
  "items": [{"menu_id": 1, "quantity": 1}]
}
```

`POST /api/orders` 내부 트랜잭션에서 잔액 검증, 쿠폰 사용, 주문 생성이 원자적으로 처리되며 주문 취소·환불 시 복구된다.

## 프론트 연동 권장 경로

| 기능 | Method | Endpoint |
|---|---|---|
| 매장 프로필 수정 | PATCH | `/api/owner/stores/{store}` |
| 매장 프로필 조회 | GET | `/api/owner/stores/{store}` |
| 매장 프로필 삭제 | DELETE | `/api/owner/stores/{store}` |
| 영업 중/마감 | PATCH | `/api/owner/stores/{store}/business-status` |
| 좌석 목록 | GET | `/api/owner/stores/{store}/seats` |
| 좌석 생성 | POST | `/api/owner/stores/{store}/seats` |
| 좌석 상태 수정 | PATCH | `/api/owner/stores/{store}/seats/{seat}` |
| 좌석 삭제 | DELETE | `/api/owner/stores/{store}/seats/{seat}` |

### 매장 영업시간 및 사업자 정보

`PATCH /api/owner/stores/{store}` 요청에 다음 필드를 함께 보낼 수 있다.

```json
{
  "business_info": {
    "business_registration_number": "123-45-67890",
    "representative_name": "홍길동",
    "company_name": "카페온 주식회사",
    "business_type": "음식점업",
    "business_item": "커피 전문점",
    "business_address": "서울특별시 중구"
  },
  "business_hours": [
    {
      "day_of_week": 1,
      "opening_time": "09:00",
      "closing_time": "22:00",
      "is_closed": false
    },
    {
      "day_of_week": 2,
      "opening_time": null,
      "closing_time": null,
      "is_closed": true
    }
  ],
  "tags": [
    {"name": "와이파이", "slug": "wifi"},
    {"name": "주차", "slug": "parking"}
  ]
}
```

`day_of_week`은 0(일요일)부터 6(토요일)까지다. 휴무가 아니면 시작·종료 시간이 모두 필요하다.

매장 프로필 접근 권한은 사용자 전역 역할명이 아니라 `store_members`의 활성 `OWNER` 연결로 확인한다. 따라서 다른 사장님 계정이나 일반 `ADMIN` 역할만 가진 계정은 해당 매장을 조회·수정·삭제할 수 없다.

### 손님 생년월일

`PUT /api/users/me`에 `birth_date`를 `YYYY-MM-DD` 형식으로 저장한다. 이후 로그인 응답과 `GET /api/users/me`의 `user.birth_date`에서 동일한 값을 받을 수 있다.

### 사장님 개인 프로필 영구 저장 및 재로그인 복원

- `GET /api/owner/profile`: 로그인한 사장님의 개인 프로필과 활성 소유 매장 목록을 함께 조회한다.
- `PATCH|PUT /api/owner/profile`: 이름, 이메일, 전화번호, 프로필 이미지, 생년월일을 `users` 테이블에 저장한다.
- `POST /api/auth/owner/login`과 사장님 계정의 `GET /api/users/me` 응답에는 `store`, `membership`, `stores`, `memberships`가 포함된다.
- 모든 사장님 인증 응답은 최상위 `store_id`도 제공하며 `GET /api/owner/stores`로 ID 없이 내 매장을 다시 조회할 수 있다.
- `GET|PATCH /api/owner/store`는 활성 OWNER 연결의 첫 번째 매장을 ID 없이 조회·저장하는 복구 경로다.
- 모바일 프로필의 단순 문자열 영업시간·사업자정보와 문자열 태그 배열도 서버에서 정규화해 저장한다.

### 사장님 운영정보 영구 저장

- 매장 운영상태는 `stores.is_open`, 예약 승인대기는 `reservations.status`, 메뉴는 `menus`, 좌석은 `store_seats`에 저장한다.
- `GET /api/owner/dashboard`, `/api/owner/reservations`, `/api/owner/menus`, `/api/owner/seats`에서 매장 ID 없이 로그인 계정의 대표 OWNER 매장 데이터를 복원한다.
- 상태 변경과 생성 API도 `/api/owner/...` ID 없는 경로를 제공한다.
- 단순 `users.role=ADMIN`은 다른 매장을 관리할 수 없으며 활성 `store_members` OWNER/MANAGER 연결이 있어야 한다.
- 소셜 사장님 계정도 `users.role=ADMIN`으로 통일하고, 최초 가입 시 기본 매장과 활성 `store_members.OWNER` 연결을 생성한다.
- 알림 및 선호 태그 설정은 `PUT /api/users/me/preferences`로 저장하며, 사장님 로그인 응답의 `preferences`로 복원한다.
- 로그아웃은 현재 인증 토큰만 폐기하며 사용자·매장 프로필 DB 데이터는 삭제하지 않는다.
- 재로그인 시 프론트는 로그인 응답 또는 프로필 조회 API로 개인 정보와 매장 ID를 복원한다.

## 점검 당시 로컬 데이터

- 사용자: 13명 (`ADMIN` 2, `CUSTOMER` 11)
- 매장: 3개
- 좌석: 1개
- 주문: 1개
- 예약: 1개
- 멤버십 계정: 0개
- 발급 쿠폰: 0개

혜택 화면은 API가 있어도 현재 로컬 DB에 멤버십 계정과 발급 쿠폰이 없으므로 빈 상태가 정상이다.

## 검증 결과

- 전체 자동화 테스트: 152개 통과
- Assertions: 743개 통과
- 최신 마이그레이션: `2026_08_19_020000_create_admin_audit_logs_table` 적용 완료
- Swagger: 115개 API path 생성
