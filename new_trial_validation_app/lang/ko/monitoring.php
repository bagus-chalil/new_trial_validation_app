<?php

// Bottleneck monitor: read-only overview of every active trial for all users.
return [
    'title' => '전체 트라이얼 현황',
    'description' => '소유자와 관계없이 진행 중인 모든 트라이얼을 표시하여 어느 단계, 누구에게서 지연되고 있는지 확인할 수 있습니다. 이 페이지는 조회 전용입니다.',
    'stage_cards' => [
        'count' => '트라이얼 :count건',
        'avg' => '활동 없음 평균 :days일',
        'oldest' => '최장 :days일',
        'stale' => ':days일 초과 :count건',
    ],
    'holders' => [
        'title' => '트라이얼이 가장 많이 지연된 곳',
        'subtitle' => '각 트라이얼이 현재 기다리고 있는 대상입니다. 행을 클릭하면 아래 목록이 필터링됩니다.',
        'name' => '대기 대상',
        'kind' => '구분',
        'count' => '트라이얼 수',
        'avg_days' => '평균(일)',
        'oldest_days' => '최장(일)',
        'empty' => '진행 중인 트라이얼이 없습니다.',
        'kinds' => [
            'owner' => '트라이얼 소유자',
            'team' => '검토 부서',
            'approver' => '승인자',
        ],
    ],
    'filters' => [
        'search' => '검색',
        'status' => '상태',
        'holder' => '대기 대상',
    ],
    'search_placeholder' => '트라이얼 코드 또는 제품명',
    'table' => [
        'trial_code' => '트라이얼 코드',
        'product_name' => '제품명',
        'status' => '상태',
        'waiting_for' => '대기 대상',
        'owner' => '소유자',
        'idle' => '무활동 기간',
        'created_at' => '생성일',
        'no_access' => '이 트라이얼의 상세 정보에 접근할 권한이 없습니다.',
        'empty' => '진행 중인 트라이얼이 없습니다.',
        'item_label' => '건',
    ],
    'days' => ':count일',
    'legend' => '무활동 기간 색상: 트라이얼의 마지막 활동 후 :warn일부터 노란색, :critical일부터 빨간색으로 표시됩니다.',
];
