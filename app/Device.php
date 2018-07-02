<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Device
 *
 * @property int $id
 * @property int $user_id
 * @property string $device_id 단말기 고유 식별 ID. 단말기 공장 초기화시 바뀔 수 있음.
 * @property string $os_enum 단말기 운영 체제
 * @property string $model 단말기 모델명
 * @property string $operator 단말기 통신사
 * @property int $api_level SDK API 버전
 * @property string $push_service_enum
 * @property string $push_service_id 푸시 메시지를 위한 단말기 고유 식별 ID
 * @property-read User $user
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @mixin \Eloquent
 */
class Device extends Model
{
    protected $fillable = [
        'device_id',
        'os_enum',
        'model',
        'operator',
        'api_level',
        'push_service_enum',
        'push_service_id'
    ];

    /* RELATIONS */

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
