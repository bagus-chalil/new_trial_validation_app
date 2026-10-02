<?php

// Login / password-reset screens, the guest layout, and the account Settings
// pages (profile, security, appearance).
return [
    'auth_layout' => [
        'back_to_portal' => '포털로 돌아가기',
        'tagline' => '생산 트라이얼의 신청부터 부서 간 검토, 승인 및 보고까지 하나의 업무 흐름으로 관리합니다.',
        'highlights' => [
            'form' => '트라이얼 양식, 파라미터 검증 및 중량 측정',
            'review' => '부서별 단계적 검토',
            'approval' => '승인, 보고서 및 활동 이력',
        ],
        'copyright' => '© :year Cosmax Indonesia. All rights reserved.',
    ],

    'login' => [
        'head' => '로그인',
        'title' => '계정에 로그인',
        'description' => '로그인하려면 아래에 이메일과 비밀번호를 입력해 주십시오',
        'email' => '이메일 주소',
        'password' => '비밀번호',
        'forgot' => '비밀번호를 잊으셨습니까?',
        'remember' => '로그인 상태 유지',
        'submit' => '로그인',
    ],

    'forgot_password' => [
        'head' => '비밀번호 찾기',
        'title' => '비밀번호 찾기',
        'description' => '비밀번호 재설정 링크를 받으실 이메일을 입력해 주십시오',
        'email' => '이메일 주소',
        'submit' => '비밀번호 재설정 링크 발송',
        'return_to' => '또는',
        'log_in' => '로그인 화면으로 돌아가기',
    ],

    'reset_password' => [
        'head' => '비밀번호 재설정',
        'title' => '비밀번호 재설정',
        'description' => '아래에 새 비밀번호를 입력해 주십시오',
        'email' => '이메일',
        'password' => '비밀번호',
        'confirm' => '비밀번호 확인',
        'submit' => '비밀번호 재설정',
    ],

    'password_input' => [
        'show' => '비밀번호 표시',
        'hide' => '비밀번호 숨기기',
    ],

    'appearance_tabs' => [
        'light' => '라이트',
        'dark' => '다크',
        'system' => '시스템 설정',
    ],

    'settings' => [
        'title' => '설정',
        'description' => '프로필 및 계정 설정을 관리합니다',
        'nav' => [
            'profile' => '프로필',
            'security' => '보안',
            'appearance' => '화면 표시',
        ],
        'save' => '저장',
    ],

    'profile' => [
        'head' => '프로필 설정',
        'title' => '프로필',
        'description' => '이름과 이메일 주소를 변경합니다',
        'name' => '이름',
        'name_placeholder' => '성명',
        'email' => '이메일 주소',
    ],

    'security' => [
        'head' => '보안 설정',
        'title' => '비밀번호 변경',
        'description' => '계정 보안을 위해 길고 예측하기 어려운 비밀번호를 사용해 주십시오',
        'current_password' => '현재 비밀번호',
        'new_password' => '새 비밀번호',
        'confirm_password' => '비밀번호 확인',
    ],

    'appearance' => [
        'head' => '화면 표시 설정',
        'title' => '화면 표시 설정',
        'description' => '계정의 화면 표시 설정을 변경합니다',
    ],

    'delete_user' => [
        'title' => '계정 삭제',
        'description' => '계정과 모든 관련 데이터를 삭제합니다',
        'warning' => '경고',
        'warning_text' => '이 작업은 되돌릴 수 없으니 신중하게 진행해 주십시오.',
        'button' => '계정 삭제',
        'confirm_title' => '계정을 삭제하시겠습니까?',
        'confirm_description' => '계정이 삭제되면 모든 관련 데이터도 영구적으로 삭제됩니다. 계정을 영구적으로 삭제하시려면 비밀번호를 입력하여 확인해 주십시오.',
        'password' => '비밀번호',
    ],

    'header' => [
        'navigation_menu' => '내비게이션 메뉴',
        'search' => '검색',
        'repository' => '저장소',
        'documentation' => '문서',
    ],

    'alert_error' => [
        'title' => '오류가 발생했습니다.',
    ],

    'toast' => [
        'profile_updated' => '프로필이 변경되었습니다.',
        'password_updated' => '비밀번호가 변경되었습니다.',
    ],
];
