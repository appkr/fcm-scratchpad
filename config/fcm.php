<?php

return [
    'server_key' => env('FCM_SERVER_KEY', 'Your FCM server key'),
    'sender_id' => env('FCM_SENDER_ID', 'Your sender id'),
    'server_send_url' => 'https://fcm.googleapis.com',
    'timeout' => 30.0, // in second
];
