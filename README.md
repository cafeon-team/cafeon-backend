# CafeON Backend

CafeON 서비스의 서버(백엔드) 프로젝트입니다. 카페 정보, 메뉴, 예약, 주문, 리뷰, 블로그, 회원 혜택 등의 데이터를 API로 제공합니다.

이 프로젝트는 **PHP 8.3**, **Laravel 13**, **MySQL**을 사용합니다.

## 처음 보는 분을 위한 용어 설명

- **백엔드**: 화면에 필요한 데이터를 저장하고 전달하는 서버 프로그램입니다.
- **API**: 프론트엔드와 백엔드가 데이터를 주고받는 통로입니다.
- **Laravel**: PHP로 백엔드를 만들 때 사용하는 웹 프레임워크입니다.
- **Composer**: PHP 라이브러리를 설치하고 관리하는 도구입니다.
- **Artisan**: Laravel 프로젝트에서 서버 실행, DB 생성, 테스트 등에 사용하는 명령어입니다.
- **Migration(마이그레이션)**: 코드에 정의된 구조대로 데이터베이스 테이블을 만드는 작업입니다.
- **Swagger**: API의 주소와 사용 방법을 웹 화면에서 확인하고 시험하는 도구입니다.

## 주요 기능

- 회원가입, 로그인, 로그아웃 및 사용자 정보 관리
- Google·Kakao 소셜 로그인
- 카페 매장, 메뉴 및 혼잡도 조회
- 예약 가능 시간 조회, 예약 생성 및 취소
- 주문 생성, 조회 및 취소
- 리뷰 작성, 수정 및 삭제
- 쿠폰, 멤버십 및 추천인 혜택
- 블로그 게시글, 댓글, 좋아요 및 분류 관리
- 점주용 예약·대시보드 기능
- SUPER_ADMIN 전용 운영 콘솔(사용자·매장·주문·예약·리뷰·문의·시스템 상태 관리)
- 이미지 업로드 및 고객 문의 관리

## 설치 전에 준비할 프로그램

다음 프로그램이 설치되어 있어야 합니다.

- PHP 8.3 이상(`pdo_mysql`, `fileinfo`, `openssl` 확장 기능 포함)
- Composer
- MySQL
- Node.js 및 npm
- Git

설치 여부는 터미널(PowerShell, 명령 프롬프트 등)에서 확인할 수 있습니다.

```bash
php -v
composer --version
mysql --version
node -v
npm -v
git --version
```

## 처음 설치하기

### 1. 프로젝트 내려받기

```bash
git clone <저장소 주소>
cd cafeon-backend
```

이미 프로젝트 폴더를 가지고 있다면 이 단계는 건너뜁니다.

### 2. MySQL 데이터베이스 만들기

MySQL에 접속한 다음 CafeON이 사용할 빈 데이터베이스를 만듭니다.

```sql
CREATE DATABASE cafeon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. 환경 설정 파일 만들기

`.env.example`을 복사해 `.env` 파일을 만듭니다.

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

macOS 또는 Linux:

```bash
cp .env.example .env
```

그다음 `.env`에서 데이터베이스 정보를 자신의 MySQL 환경에 맞게 수정합니다.

```env
APP_NAME=CafeON
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cafeon
DB_USERNAME=root
DB_PASSWORD=본인의_MySQL_비밀번호
```

> `.env`에는 DB 비밀번호나 소셜 로그인 비밀 키가 들어갈 수 있으므로 Git에 올리면 안 됩니다.

### 4. 필요한 패키지와 테이블 설치하기

```bash
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

각 명령어의 의미는 다음과 같습니다.

1. `composer install`: PHP 패키지를 설치합니다.
2. `php artisan key:generate`: 암호화에 사용하는 애플리케이션 키를 만듭니다.
3. `php artisan migrate`: MySQL에 필요한 테이블을 만듭니다.
4. `npm install`: 화면 빌드에 필요한 Node.js 패키지를 설치합니다.
5. `npm run build`: CSS와 JavaScript 파일을 빌드합니다.

위 명령을 하나씩 실행하는 대신 프로젝트가 제공하는 `composer run setup`을 사용할 수도 있습니다. 이 명령은 PHP 패키지 설치, `.env` 생성, 애플리케이션 키 생성, DB 테이블 생성, Node.js 패키지 설치 및 화면 빌드를 순서대로 실행합니다. 기존 `.env`가 있으면 덮어쓰지 않습니다. 실행 전에 MySQL 데이터베이스를 만들고 `.env`의 DB 정보를 설정해야 합니다.

## 개발 서버 실행하기

### 가장 간단한 실행 방법

```bash
php artisan serve --port=8000
```

브라우저에서 아래 주소로 접속합니다.

- 기본 화면: <http://127.0.0.1:8000>
- Swagger API 문서: <http://127.0.0.1:8000/swagger>
- MVC 기능 확인 화면: <http://127.0.0.1:8000/test/mvc>
- 블로그 API 확인 화면: <http://127.0.0.1:8000/test/mvc/blog-api>
- 소셜 로그인 확인 화면: <http://127.0.0.1:8000/test/social-login>
- SUPER_ADMIN 운영 콘솔: <http://127.0.0.1:8000/admin/login>

`/test`로 시작하는 확인용 화면은 `.env`의 `APP_ENV`가 `local` 또는 `testing`일 때만 열립니다.

### SUPER_ADMIN 계정 만들기

운영 콘솔은 일반 `ADMIN` 또는 점주 계정과 분리되어 있으며, 정확히 `SUPER_ADMIN` 역할을 가진 활성 계정만 접근할 수 있습니다.

```bash
php artisan admin:create admin@example.com --name="CafeOn 시스템 관리자"
```

명령 실행 시 안전한 임시 비밀번호가 출력됩니다. 직접 비밀번호를 지정하거나 기존 관리자 비밀번호를 교체하려면 `--password` 옵션을 사용합니다.

```bash
php artisan admin:create admin@example.com --name="CafeOn 시스템 관리자" --password="새로운-안전한-비밀번호"
```

계정 생성과 권한 변경은 서버 콘솔에서만 수행하며, 운영 콘솔 내에서는 다른 `SUPER_ADMIN` 계정을 정지할 수 없습니다. 관리자 로그인과 데이터 변경은 `admin_audit_logs`에 기록됩니다.

### 서버, 큐, 로그, Vite를 한꺼번에 실행하기

```bash
composer run dev
```

이 명령은 Laravel 서버, 작업 큐, 실시간 로그, Vite 개발 서버를 동시에 실행합니다. 이 프로젝트의 로컬 서버와 소셜 로그인 콜백 주소는 `http://127.0.0.1:8000`을 사용합니다.

서버를 종료하려면 실행 중인 터미널에서 `Ctrl + C`를 누릅니다.

## API 사용 방법

API 주소는 기본적으로 `/api`로 시작합니다. 예를 들면 다음과 같습니다.

```text
GET  /api/stores       매장 목록 조회
GET  /api/posts        블로그 게시글 목록 조회
POST /api/auth/signup  회원가입
POST /api/auth/login   로그인
```

로그인이 필요한 API는 로그인 성공 시 받은 토큰을 요청 헤더에 넣어야 합니다.

```http
Authorization: Bearer 발급받은_토큰
Accept: application/json
```

전체 API와 요청 형식은 Swagger 화면에서 확인합니다.

```bash
php artisan swagger:generate-routes
```

문서를 생성한 뒤 <http://127.0.0.1:8000/swagger>에 접속합니다.

## 테스트 실행하기

전체 자동 테스트를 실행합니다.

```bash
composer test
```

또는 Artisan 명령어를 직접 사용할 수 있습니다.

```bash
php artisan test
```

특정 테스트만 실행하려면 테스트 이름을 지정합니다.

```bash
php artisan test --filter=StoreApiTest
```

## 예약 게시글 스케줄러

예약된 블로그 게시글을 정해진 시간에 공개하려면 스케줄러가 계속 실행되어야 합니다.

로컬 개발 환경:

```bash
php artisan schedule:work
```

등록된 작업 확인:

```bash
php artisan schedule:list
```

예약 게시글 공개 작업을 즉시 시험:

```bash
php artisan posts:publish-scheduled
```

운영 서버 설정은 [스케줄러 배포 안내](docs/DEPLOYMENT_SCHEDULER.md)를 참고하세요.

## 소셜 로그인 설정

Google 또는 Kakao 로그인을 사용하려면 각 개발자 콘솔에서 발급받은 값을 `.env`에 입력합니다.

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/social/google/callback

KAKAO_CLIENT_ID=
KAKAO_CLIENT_SECRET=
KAKAO_REDIRECT_URI=http://127.0.0.1:8000/auth/social/kakao/callback
```

소셜 로그인을 사용하지 않는다면 로컬 실행 단계에서는 비워 두어도 됩니다.

## 자주 사용하는 명령어

| 명령어 | 설명 |
| --- | --- |
| `php artisan serve --port=8000` | 로컬 서버 실행 |
| `php artisan migrate` | 새 DB 변경 사항 적용 |
| `php artisan migrate:fresh` | 모든 테이블을 지우고 다시 생성하므로 주의 |
| `php artisan route:list` | 등록된 URL 목록 확인 |
| `php artisan optimize:clear` | 설정·라우트 등 Laravel 캐시 삭제 |
| `php artisan storage:link` | 공개 이미지용 저장소 링크 생성 |
| `php artisan swagger:generate-routes` | 전체 라우트 기반 Swagger 문서 생성 |
| `php artisan test` | 자동 테스트 실행 |
| `npm run dev` | 프론트엔드 파일 개발 모드 실행 |
| `npm run build` | 배포용 프론트엔드 파일 빌드 |

## 문제가 생겼을 때

### `No application encryption key` 오류

```bash
php artisan key:generate
```

### 데이터베이스 연결 오류

MySQL이 실행 중인지 확인하고 `.env`의 `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`가 실제 정보와 같은지 확인합니다.

### 변경한 `.env` 설정이 적용되지 않을 때

```bash
php artisan optimize:clear
```

### 업로드한 이미지가 보이지 않을 때

```bash
php artisan storage:link
```

### 패키지 또는 클래스 관련 오류

```bash
composer install
composer dump-autoload
```

## 주요 폴더 구조

```text
app/            핵심 PHP 코드(Controller, Model, Service 등)
config/         애플리케이션 설정
database/       DB 마이그레이션, 테스트 데이터 및 팩토리
docs/           추가 문서와 DB 설계 자료
resources/      화면, CSS, JavaScript 원본
routes/         웹 및 API 주소 정의
storage/        로그, 생성된 API 문서 및 업로드 파일
tests/          자동 테스트 코드
```

## 운영 환경 주의사항

운영 서버에서는 최소한 다음 값을 안전하게 설정해야 합니다.

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://실제-서비스-주소
```

또한 실제 MySQL 접속 정보, HTTPS, 스케줄러, 큐 작업 프로세스와 로그 관리 설정이 필요합니다. `.env`와 비밀 키는 외부에 공개하지 마세요.

## 참고 자료

- [Laravel 공식 문서](https://laravel.com/docs)
- [Laravel 학습 자료](https://laravel.com/learn)
- [프로젝트 스케줄러 배포 안내](docs/DEPLOYMENT_SCHEDULER.md)

## 라이선스

이 프로젝트가 사용하는 Laravel 프레임워크는 [MIT 라이선스](https://opensource.org/licenses/MIT)를 따릅니다.
