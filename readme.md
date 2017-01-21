## FCM Scratchpad

FCM(Firebase Cloud Message)은 Android, iOS, Web 등의 클라이언트에 푸쉬 메시지를 보낼 수 있도록 하는 Google의 서비스입니다. 과거 GCM(Google Cloud Message)이 진화한거죠. 

### 1. 프로젝트 설치

프로젝트를 복제합니다.

```bash
~ $ git clone git@github.com:appkr/fcm-scratchpad.git

# 깃허브에 SSH 키를 등록하지 않았다면...
~ $ git clone https://github.com/appkr/fcm-scratchpad.git
```

이 프로젝트가 의존하는 라이브러리를 설치하고, 프로젝트 설정 파일을 생성합니다.

```bash
# composer는 PHP의 표준 의존성 관리자에요.
# 없다면 getcomposer.org를 참고해주세요.

$ cd fcm-scratchpad
~/fcm-scratchpad $ composer install
~/fcm-scratchpad $ cp .env.example .env
~/fcm-scratchpad $ php artisan key:generate
```

이 프로젝트는 SQLite를 사용합니다. database/database.sqlite를 만들고 테이블을 생성합니다.

```bash
~/fcm-scratchpad $ touch database/database.sqlite
~/fcm-scratchpad $ php artisan migrate --seed
```

[Firebase Console](https://console.firebase.google.com/)을 방문해서 새 프로젝트를 만든후 서버 키와 발신자 ID를 얻을 수 있습니다. 얻은 키는 `.env` 파일에 기록해주세요.
 
```bash
# .env

FCM_SERVER_KEY=여기에 서버 키를 넣는다
FCM_SENDER_ID=여기에 발신자 ID를 넣는다
```
 
### 2. FCM 작동 원리
 
다음은 FCM 프로젝트 등록 및 단말기 등록 과정입니다.
 
- Firebase 콘솔에서 프로젝트를 등록합니다. 이때 모바일 애플리케이션의 패키지 이름도 등록합니다.
- [단말] 모바일 애플리케이션을 처음 시작할 때 FCM SDK가 FCM 서버와 통신해서 해당 단말을 식별할 수 있는 고유한 토큰(`registration_id`)을 얻을 수 있습니다.
- [단말] 방금 받은 토큰을 앱 서버에 제출합니다. 이 프로젝트가 앱 서버 프로젝트입니다.
- [서버] 토큰 저장을 요청한 단말(사용자)를 식별하고, 데이터베이스에 저장합니다.

이제 FCM을 보낼 준비가 되었으므로, FCM을 전송하는 과정을 살펴볼게요.
 
- [서버] 푸쉬 메시지를 보낼 단말의 토큰(`registration_id`)를 식별합니다.
- [서버] FCM 서버에 토큰 목록과 단말에 보낼 메시지를 전달합니다.
- [FCM 서버] 요청 받은 메시지를 단말기에게 보냅니다. FCM의 장점은 단말이 꺼져있거나, 잠김 상태일 때도 메시지를 안전하게 보낼 수 있다는 장점이 있습니다.
- [서버] FCM 서버에게 전송 요청하고 받은 응답에 따라 적절한 처리를 합니다(e.g. `registration_id` 업데이트 등)

> 우리 프로젝트에서는 단말기가 FCM 서버로 부터 받아 앱 서버에 등록하는 단말 식별 고유 토큰을 `registration`라 하지 않고 `push_service_id`라고 사용하고 있습니다.

### 3. FCM 보내기

#### 3.1. STEP 1

로컬 서버를 구동합니다.

```bash
~/fcm-scratchpad $ php artisan serve
# Laravel development server started on http://localhost:8000/
```

#### 3.2. STEP 2

미리 만들어 둔 [포스트맨 콜렉션](https://raw.githubusercontent.com/appkr/fcm-scratchpad/master/docs/fcm-scratchpad.postman_collection.json)으로 단말기의 토큰을 등록해주세요. 이 토큰은 모바일 애플리케이션을 통해서 받은 정상 토큰이 아니므로 FCM을 보낼 수는 없지만 우리 서버와 FCM 서버가 정상적으로 통신한다는 것은 알 수 있습니다.

#### 3.3. STEP 3

테스트 UI를 통해서 앱 서버에 토큰이 잘 등록됐나 확인합니다. 

이 프로젝트는 다음 테스트 UI를 제공합니다.

번호|URL|설명
---|---|---
1|`GET /users`|사용자 목록과 각 사용자에게 등록된 단말기 목록을 조회하고, "FCM 보내기" 버튼을 누를 수 있어요. (그림 1과 3)
2|`GET /users/{user}/fcm`|FCM 메시지 작성을 위한 폼을 출력합니다. (그림 2)
3|`POST /fcm`|FCM 메시지를 전송합니다.

그림 1 사용자 목록 조회 페이지

![](https://github.com/appkr/fcm-scratchpad/raw/master/docs/image-01.png)

그림 2 FCM 메시지 작성 폼

![](https://github.com/appkr/fcm-scratchpad/raw/master/docs/image-02.png)

그림 3 FCM 메시지 전송 결과

![](https://github.com/appkr/fcm-scratchpad/raw/master/docs/image-03.png)

#### 3.4. STEP 4

`storage/logs/laravel-fcm.log` 에서도 전송 결과를 확인할 수 있어요.

```bash
# storage/logs/laravel-fcm.log

[2016-12-18 08:37:12] Laravel-FCM.INFO: notification send to 1 devices success: 0 failures: 1 number of modified token : 0  [] []
```
