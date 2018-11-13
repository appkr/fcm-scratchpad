<?php

namespace App\Services;

interface FcmDeviceRepository
{
    /**
     * 구글 서버로부터 받은 변경된 push_service_id(==registration_id)를 업데이트 합니다.
     *
     * @param string $oldId
     * @param string $newId
     * @return void
     */
    public function updateFcmDevice(string $oldId, string $newId);

    /**
     * 구글 서버에서 처리할 수 없는 push_service_id(==registration_id)를 삭제합니다.
     *
     * @param string $deprecatedId
     * @return void
     */
    public function deleteFcmDevice(string $deprecatedId);
}
