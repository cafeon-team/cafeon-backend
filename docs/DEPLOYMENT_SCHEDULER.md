# CafeON 예약 발행 Scheduler 설정

CafeON은 `routes/console.php`에서 `posts:publish-scheduled` 명령을 매분 실행하도록 등록한다.

## 로컬 개발

```powershell
php artisan schedule:work
```

## Linux 운영 서버 또는 공유 호스팅

호스팅 관리 화면의 Cron 메뉴에 다음 작업을 1분마다 등록한다. PHP 및 프로젝트 경로는 실제 서버 경로로 변경한다.

```cron
* * * * * /usr/bin/php /home/ACCOUNT/cafeon-backend/artisan schedule:run >> /dev/null 2>&1
```

호스팅이 1분 간격을 지원하지 않으면 제공되는 가장 짧은 간격을 사용한다. 배포 후 아래 명령으로 등록 상태를 확인한다.

```bash
php artisan schedule:list
php artisan posts:publish-scheduled
```

## 배포 시 실행 순서

```bash
composer install --no-dev --optimize-autoloader
composer run deploy
```

`composer run deploy`는 설정 캐시를 비우고 이미지 업로드 디렉터리의 생성·쓰기
검사를 수행한 뒤, 마이그레이션·Swagger 생성·운영 캐시 생성을 순서대로 실행한다.

운영 `.env`에는 다음 값을 설정한다.

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wa26b01.yjjob.kr
IMAGE_UPLOAD_DISK=public
```

일반적인 배포 구조에서는 이미지가 `public/uploads`에 저장되므로
`php artisan storage:link`가 없어도 된다. 웹 루트가 Laravel의 `public` 폴더와
다른 공유 호스팅에서는 실제 공개 디렉터리를 절대 경로로 지정한다.

```env
PUBLIC_UPLOAD_ROOT=/home/ACCOUNT/public_html/uploads
PUBLIC_UPLOAD_URL=https://wa26b01.yjjob.kr/uploads
```

배포 중 `uploads:prepare`가 실패하면 해당 경로의 소유자와 쓰기 권한을 먼저
수정해야 한다. 배포가 성공한 뒤 다음 명령으로 언제든 다시 확인할 수 있다.

```bash
php artisan uploads:prepare
```

`.env`는 `APP_ENV=production`, `APP_DEBUG=false`로 설정하고 실제 MySQL 접속 정보와 `APP_URL`을 입력한다.
