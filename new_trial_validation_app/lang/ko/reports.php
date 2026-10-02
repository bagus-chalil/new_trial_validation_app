<?php

// Reports hub (reports/index) and its 5 report list pages.
return [
    'index' => [
        'title' => '보고서',
        'description' => '트라이얼 검증 보고서 유형을 선택하십시오.',
    ],

    'approved' => [
        'title' => '승인 완료 보고서',
        'card_description' => '승인 완료된 트라이얼 목록입니다.',
        'description' => '승인 완료 상태의 트라이얼입니다.',
        'empty' => '승인 완료 보고서가 없습니다.',
    ],

    'rejected' => [
        'title' => '반려 보고서',
        'card_description' => '반려 또는 수정 필요 트라이얼 목록입니다.',
        'description' => 'QAC 매니저가 최종 반려한 트라이얼입니다.',
        'reason' => '사유 / 최종 의견',
        'empty' => '반려 보고서가 없습니다.',
    ],

    'trial_summary' => [
        'title' => '트라이얼 요약 보고서',
        'card_description' => '필터를 적용한 전체 트라이얼 요약입니다.',
        'description' => '전체 트라이얼 검증 요약입니다.',
        'current_step' => '현재 단계',
        'all_product_types' => '전체 제품 유형',
        'all_scopes' => '전체 범위',
        'all_machines' => '전체 설비',
        'product_name_placeholder' => '제품명',
        'empty' => '트라이얼 데이터가 없습니다.',
        'steps' => [
            'header' => '기본 정보',
            'validation' => '검증',
            'weighing_packaging' => '계량 (포장재)',
            'weighing_filling' => '계량 (충전)',
            'attachment' => '첨부 파일',
            'review' => '검토',
            'approval' => '승인',
        ],
    ],

    'department_review' => [
        'title' => '부서별 검토 보고서',
        'card_description' => '부서별 검토 진행 현황입니다.',
        'description' => '부서별 검토 진행 현황입니다.',
        'review_status' => '검토 상태',
        'pending_department' => '검토 대기 부서',
        'empty' => '부서 검토 데이터가 없습니다.',
    ],

    'audit_print_log' => [
        'title' => '출력 감사 로그',
        'card_description' => '보고서 출력 기록입니다(해당 시).',
        'description' => '보고서 출력 활동 기록입니다(해당 시).',
        'printed_by' => '출력자',
        'printed_at' => '출력 일시',
        'report_type' => '보고서 유형',
        'empty' => '출력 감사 로그가 없습니다.',
        'item_label' => '건',
    ],

    'actions' => [
        'view_report' => '보고서 보기',
        'view_summary' => '요약 보기',
        'view_review' => '검토 보기',
    ],
];
