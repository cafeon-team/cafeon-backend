# CafeON 프런트엔드 연결 안내

## 접속 주소

현재 운영 백엔드 주소는 다음과 같습니다.

```text
https://wa26b01.yjjob.kr
```

프런트엔드에서는 환경변수 하나로 API 기준 주소를 관리합니다.

```env
# Vite
VITE_API_BASE_URL=https://wa26b01.yjjob.kr

# Next.js
NEXT_PUBLIC_API_BASE_URL=https://wa26b01.yjjob.kr
```

향후 프런트엔드와 백엔드를 분리하면 백엔드를 `https://api.wa26b01.yjjob.kr`, 프런트엔드를 `https://wa26b01.yjjob.kr`로 운영하고 위 값만 API 서브도메인으로 변경합니다. API 경로에는 항상 `/api`가 붙습니다.

## 기본 요청

모든 JSON 요청에 `Accept: application/json`을 보냅니다.

```js
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL;

export async function api(path, options = {}) {
  const token = localStorage.getItem('cafeon_token');
  const response = await fetch(`${API_BASE_URL}/api${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  });

  const body = await response.json().catch(() => null);
  if (!response.ok) throw { status: response.status, body };
  return body;
}
```

공개 API 예시:

```js
const stores = await api('/stores');
const posts = await api('/posts');
```

## 회원가입과 로그인

일반 회원가입:

```http
POST /api/auth/signup
Content-Type: application/json

{
  "name": "홍길동",
  "email": "user@example.com",
  "password": "password123",
  "terms_accepted": true
}
```

로그인:

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

성공 응답의 `token`을 저장하고 보호 API에 다음 헤더로 전달합니다.

```http
Authorization: Bearer <token>
```

로그아웃 API를 호출한 뒤 프런트엔드에서도 토큰을 삭제합니다. 현재 인증은 쿠키가 아닌 Sanctum Bearer 토큰 방식이므로 `credentials: 'include'`는 필요하지 않습니다.

## 소셜 로그인

브라우저를 다음 백엔드 주소로 이동시킵니다. `role`은 `customer` 또는 `owner`입니다.

```text
GET /auth/social/google/redirect?role=customer
GET /auth/social/kakao/redirect?role=customer
GET /auth/social/naver/redirect?role=customer
```

공급자 인증이 끝나면 백엔드는 역할별 `FRONTEND_SOCIAL_CALLBACK_URL_*` 주소로 일회용 코드를 전달합니다. 프런트엔드는 받은 `code`를 다음 API로 교환해 Bearer 토큰을 얻습니다.

```http
POST /api/auth/social/exchange
Content-Type: application/json

{"code":"callback에서 받은 코드"}
```

운영 서버 `.env` 예시:

```env
APP_URL=https://api.wa26b01.yjjob.kr
FRONTEND_ORIGINS=https://wa26b01.yjjob.kr
FRONTEND_SOCIAL_CALLBACK_URL_CUSTOMER=https://wa26b01.yjjob.kr/auth/callback
FRONTEND_SOCIAL_CALLBACK_URL_OWNER=https://owner.wa26b01.yjjob.kr/auth/callback
GOOGLE_REDIRECT_URI=https://api.wa26b01.yjjob.kr/auth/social/google/callback
KAKAO_REDIRECT_URI=https://api.wa26b01.yjjob.kr/auth/social/kakao/callback
NAVER_REDIRECT_URI=https://api.wa26b01.yjjob.kr/auth/social/naver/callback
```

OAuth 공급자 콘솔에도 각 `*_REDIRECT_URI`를 정확히 동일하게 등록해야 합니다.

## CORS와 오류 처리

백엔드 운영 `.env`의 `FRONTEND_ORIGINS`에는 허용할 프런트엔드 HTTPS Origin만 쉼표로 구분해 입력합니다. 경로(`/page`)나 끝 슬래시는 넣지 않습니다.

```env
FRONTEND_ORIGINS=https://wa26b01.yjjob.kr,https://owner.wa26b01.yjjob.kr
```

주요 상태 코드는 다음처럼 처리합니다.

- `401`: 토큰이 없거나 만료됨 — 토큰 삭제 후 로그인 화면으로 이동
- `403`: 로그인했지만 권한 또는 역할이 맞지 않음
- `422`: 입력값 검증 실패 — 응답의 `errors`를 폼에 표시
- `429`: 요청 제한 초과 — 잠시 후 재시도
- `500`: 서버 오류 — 사용자에게 일반 오류 메시지를 표시하고 서버 로그 확인

전체 API 목록과 스키마는 `/api/documentation`의 Swagger 문서에서 확인합니다.
