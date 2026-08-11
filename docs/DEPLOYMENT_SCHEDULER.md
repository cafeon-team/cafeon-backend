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
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan swagger:generate-routes
```

`.env`는 `APP_ENV=production`, `APP_DEBUG=false`로 설정하고 실제 MySQL 접속 정보와 `APP_URL`을 입력한다.
