## FCM Scratchpad

FCM(Firebase Cloud Message)은 Android, iOS, Web 등의 클라이언트에 푸쉬 메시지를 보낼 수 있도록 하는 Google의 서비스다. 과거 GCM(Google Cloud Message)이 진화한 것이다. 

### 1. 프로젝트 설치

프로젝트를 복제한다.

```bash
~ $ git clone git@github.com:appkr/fcm-scratchpad.git

# 깃허브에 SSH 키를 등록하지 않았다면...
~ $ git clone https://github.com/appkr/fcm-scratchpad.git
```

이 프로젝트가 의존하는 라이브러리를 설치하고, 프로젝트 설정 파일을 생성한다.

```bash
# composer는 PHP의 표준 의존성 관리자다.
# 없다면 getcomposer.org를 참고한다.

$ cd fcm-scratchpad
~/fcm-scratchpad $ composer install
~/fcm-scratchpad $ cp .env.example .env
~/fcm-scratchpad $ php artisan key:generate
```

이 프로젝트는 SQLite를 사용한다. database/database.sqlite를 만들고 테이블을 생성한다.

```bash
~/fcm-scratchpad $ touch database/database.sqlite
~/fcm-scratchpad $ php artisan migrate --seed
```

[Firebase Console](https://console.firebase.google.com/)을 방문해서 새 프로젝트를 만든후 서버 키와 발신자 ID를 얻을 수 있다. 얻은 키는 `.env` 파일에 기록한다.
 
```bash
# .env

FCM_SERVER_KEY=여기에 서버 키를 넣는다
FCM_SENDER_ID=여기에 발신자 ID를 넣는다
```
 
### 2. FCM 작동 원리
 
다음은 FCM 프로젝트 등록 및 단말기 등록 과정이다.
 
- Firebase 콘솔에서 프로젝트를 등록한다. 이때 모바일 애플리케이션의 패키지 이름을 등록해야 한다.
- [단말] 모바일 애플리케이션을 처음 시작할 때 FCM SDK가 FCM 서버와 통신해서 해당 단말을 식별할 수 있는 고유한 토큰(`registration_id`)을 얻는다.
- [단말] 방금 받은 토큰을 앱 서버에 전달한다. 이 저장소의 프로젝트가 앱 서버다.
- [서버] 토큰 저장을 요청한 단말(사용자)를 식별하고, 데이터베이스에 저장한다.

이제 FCM을 보낼 준비가 되었으므로, FCM을 전송하는 과정을 살펴보자.
 
- [서버] 푸쉬 메시지를 보낼 단말의 토큰(`registration_id`)를 식별한다.
- [서버] FCM 서버에 토큰 목록과 단말에 보낼 메시지를 전달한다.
- [FCM 서버] 요청 받은 메시지를 단말기에게 보낸다. FCM의 장점은 단말이 꺼져있거나, 잠김 상태일 때도 메시지를 안전하게 보낼 수 있다는 점이다.
- [서버] FCM 서버에게 전송 요청하고 받은 응답에 따라 적절한 처리를 한다(e.g. `registration_id` 업데이트 등)

### 3. FCM 보내기

1번 사용자, 김고객에게 메시지를 보낼 것이다.

```bash
~/fcm-scratchpad $ tinker
>>> $user = App\User::find(1);
=> App\User {#696
     id: "1",
     name: "김고객",
     email: "user@example.com",
     created_at: "2016-12-18 07:06:51",
     updated_at: "2016-12-18 07:06:51",
   }
```

로컬 서버를 구동한다. 미리 만들어 둔 [포스트맨 콜렉션](https://www.getpostman.com/collections/c1d8a2c441fa0ef43330)으로 단말기의 토큰을 등록한다. 이 토큰은 모바일 애플리케이션을 통해서 받은 정상 토큰이 아니므로 FCM을 보낼 수는 없다. 그러나 우리 서버와 FCM 서버가 정상적으로 통신한다는 것은 알 수 있다.

```bash
~/fcm-scratchpad $ php artisan serve
# Laravel development server started on http://localhost:8000/
```

FCM 전송 로그는 `storage/logs/laravel-fcm.log` 에서 확인할 수 있다.

```bash
# storage/logs/laravel-fcm.log

[2016-12-18 08:37:12] Laravel-FCM.INFO: notification send to 1 devices success: 0 failures: 1 number of modified token : 0  [] []
```
