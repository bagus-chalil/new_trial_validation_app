<?php

// Trial lists (per-status + tracking), the shared trials table, and the
// Need Review / Need Approval queues.
return [
    'table' => [
        'trial_code' => '트라이얼 코드',
        'product_name' => '제품명',
        'finish_good_code' => '완제품 코드',
        'product_type' => '제품 유형',
        'validation_scope' => '트라이얼 유형',
        'status' => '상태',
        'progress' => '진행 상황',
        'created_at' => '작성일',
        'pending_with' => '대기 담당자',
        'actions' => '작업',
        'edit' => '수정',
        'empty' => '선택한 조건에 해당하는 트라이얼이 없습니다.',
        'item_label' => '트라이얼',
    ],

    'list' => [
        'new_trial' => '신규 트라이얼',
        'search_placeholder' => '트라이얼 코드, 제품, 완제품 코드, 트라이얼 유형, 설비',
        'empty' => '이 페이지에 트라이얼이 없습니다.',
        'filters' => [
            'search' => '검색',
            'status' => '상태',
            'product_type' => '제품 유형',
            'validation_scope' => '트라이얼 유형',
            'all_validation_scopes' => '전체 트라이얼 유형',
            'date_from' => '시작일',
            'date_to' => '종료일',
            'chip_from' => '시작',
            'chip_to' => '종료',
        ],
        'groups' => [
            'approved' => [
                'title' => '승인 완료 트라이얼',
                'subtitle' => '승인이 완료된 트라이얼 목록입니다.',
            ],
            'tracking' => [
                'title' => '진행 현황',
                'subtitle' => '진행 중인 모든 트라이얼(검토 중 및 승인 대기)을 확인합니다. 이 페이지는 조회 전용이며, 검토 및 승인 작업은 검토 대기 및 승인 대기 메뉴에서 수행하십시오.',
            ],
            'need_revision' => [
                'title' => '수정 필요 트라이얼',
                'subtitle' => '수정을 위해 담당자(Staff)에게 반송된 트라이얼입니다.',
            ],
            'rejected' => [
                'title' => '반려 트라이얼',
                'subtitle' => '최종 반려된 트라이얼입니다.',
            ],
            'draft' => [
                'title' => '작성 중 트라이얼',
                'subtitle' => '아직 작성 중인 트라이얼입니다.',
            ],
        ],
    ],

    'queue' => [
        'search_placeholder' => '트라이얼 코드 또는 제품 검색',
        'trial' => '트라이얼',
        'product' => '제품',
        'product_type' => '제품 유형',
        'status' => '상태',
    ],

    'reviews' => [
        'title' => '검토 대기',
        'description' => '소속 부서의 검토가 필요한 트라이얼입니다. 관련된 모든 부서는 이 페이지에서 검토를 진행할 수 있습니다.',
        'round' => '차수',
        'reviewer' => '검토자',
        'comment' => '의견',
        'action' => '확인 및 검토',
        'empty' => '대기 중인 검토가 없습니다.',
        'item_label' => '검토',
        'status' => [
            'pending' => '대기',
            'reviewed' => '검토 완료',
        ],
    ],

    'approvals' => [
        'title' => '승인 대기',
        'description' => 'QAC 매니저 / 승인자의 최종 결정을 기다리는 트라이얼입니다. 지정된 모든 승인자는 이 페이지에서 결정을 내릴 수 있습니다.',
        'approver' => '승인자',
        'action' => '확인 및 결정',
        'empty' => '승인 대기 중인 트라이얼이 없습니다.',
    ],
];
