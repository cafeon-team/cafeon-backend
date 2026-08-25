# 사장님 프로필·메뉴 프론트 연동 가이드

## 확인된 백엔드 동작

- 사장님 로그인: `POST /api/auth/owner/login`
- 매장 프로필 조회: `GET /api/owner/store`
- 매장 프로필 저장: `PATCH /api/owner/store`
- 메뉴 목록 조회: `GET /api/owner/menus`
- 메뉴 저장: `POST /api/owner/menus`
- 이미지 업로드: `POST /api/uploads/images`

모든 사장님 API에는 로그인 응답의 토큰을 다음 헤더로 보내야 한다.

```http
Authorization: Bearer {owner_token}
Accept: application/json
```

`admin@cafeon.test` 계정은 사용자 권한이 `ADMIN`인 것과 별개로, DB의
`store_members` 테이블에서 관리할 매장과 `OWNER`로 연결되어 있어야 한다.
이 연결이 없으면 프로필이 비어 보이고 메뉴 저장도 실패한다.

## 프로필 조회와 저장

로그인 직후 또는 프로필 화면 진입 시 `GET /api/owner/store`를 호출하고,
응답의 `store`를 화면 상태로 설정한다. 빈 초기 상태가 서버 응답을 다시
덮어쓰지 않도록 주의한다.

저장 버튼은 `PATCH /api/owner/store`에 다음 형태로 요청한다.

```json
{
  "name": "매장명",
  "description": "매장 설명",
  "address": "주소",
  "phone": "전화번호",
  "business_hours": null,
  "tags": ["커피", "음료"]
}
```

`business_hours`는 문자열 또는 `null`을 보낼 수 있다. 저장 성공 후에는
로컬 입력값이 아니라 응답의 `store`로 화면 상태를 갱신한다.

로그아웃할 때는 토큰과 프론트의 임시 상태만 지운다. 서버 프로필을 빈 값으로
`PATCH`하거나 삭제 API를 호출하면 안 된다. 재로그인 후 다시
`GET /api/owner/store`를 호출하면 DB에 저장된 정보가 복원된다.

## 메뉴와 이미지 저장 순서

사진은 메뉴 JSON에 `File`, `blob:` URL 또는 base64 미리보기 값을 직접 넣지
않는다. 먼저 파일을 업로드한 후 반환된 공개 URL을 메뉴 저장에 사용한다.

```ts
const form = new FormData();
form.append("image", file);

const uploadResponse = await fetch(`${API_BASE}/api/uploads/images`, {
  method: "POST",
  headers: { Authorization: `Bearer ${ownerToken}` },
  body: form,
});
const upload = await uploadResponse.json();

const menuResponse = await fetch(`${API_BASE}/api/owner/menus`, {
  method: "POST",
  headers: {
    Authorization: `Bearer ${ownerToken}`,
    Accept: "application/json",
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    name: "딸기라떼",
    price: 4500,
    category: "음료",
    image_url: upload.url,
    is_available: true,
  }),
});
```

화면에는 서버가 `201`로 반환한 메뉴만 추가하고, 화면 진입 시
`GET /api/owner/menus`로 목록을 다시 조회한다.

## 현재 프론트에서 고쳐야 할 부분

현재 프론트의 `apiOwnerCreateMenu`는 모든 오류를 잡은 뒤 `null`만 반환해서
401, 403, 404, 422의 실제 원인을 숨긴다. 응답 상태와 백엔드의 `message`,
`errors`를 호출부로 전달하거나 예외를 다시 던져야 한다.

```ts
if (!response.ok) {
  const body = await response.json().catch(() => ({}));
  throw new Error(body.message ?? `메뉴 저장 실패 (${response.status})`);
}
```

또한 `.env.local`은 백엔드가 실제로 실행되는 LAN 주소를 사용해야 한다.

```env
NEXT_PUBLIC_API_BASE_URL=http://192.168.0.75:8001
```

변경 후 Next.js 개발 서버를 재시작해야 한다. 프론트 PC에서
`http://192.168.0.75:8001/api/documentation`에 접속할 수 있어야 한다.

오류별 확인 기준:

- `401`: 사장님 토큰이 없거나 만료됨
- `403`: 손님 토큰을 사장님 API에 사용했거나 계정 권한이 다름
- `404`와 연결된 매장 관련 메시지: `store_members`의 매장 연결 누락
- `422`: 필드명, 카테고리, 가격 또는 이미지 형식 검증 실패
- 브라우저 네트워크 오류: API 주소, 방화벽 또는 CORS 확인
