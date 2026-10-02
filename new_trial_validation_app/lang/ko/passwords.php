<?php

// Laravel's password-reset broker statuses (server-side only).
return [
    'reset' => '비밀번호가 재설정되었습니다.',
    'sent' => '비밀번호 재설정 링크를 이메일로 발송하였습니다.',
    'throttled' => '잠시 후 다시 시도해 주십시오.',
    'token' => '유효하지 않은 비밀번호 재설정 토큰입니다.',
    'user' => '해당 이메일 주소로 등록된 사용자를 찾을 수 없습니다.',
];
