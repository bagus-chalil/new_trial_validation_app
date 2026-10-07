<?php

// Dashboard and "My Work" page strings.
return [
    'title' => '트라이얼 대시보드',
    'description' => '트라이얼 검증 시스템 현황: 프로세스 상태, 추이 및 세부 분석.',

    'period' => [
        'options' => [
            'all' => '전체 기간',
            'this_month' => '이번 달',
            'last_month' => '지난 달',
            'last_3_months' => '최근 3개월',
            'last_6_months' => '최근 6개월',
            'this_year' => '올해',
            'month' => '월 선택...',
            'custom' => '기간 지정...',
        ],
        'from' => '시작일',
        'to' => '종료일',
        'apply' => '적용',
        'showing_all' => '전체 트라이얼 표시 (전체 기간)',
        'showing_range' => ':from – :to 생성된 트라이얼',
    ],

    'kpi' => [
        'no_data' => '데이터 없음',
        'approval_rate' => '승인율',
        'approval_rate_caption' => '최종 결정된 트라이얼 기준',
        'avg_approval_time' => '평균 승인 소요 시간',
        'avg_approval_time_caption' => '트라이얼 생성부터 승인까지',
        'days' => ':count일',
        'active_trials' => '진행 중 트라이얼',
        'active_trials_caption' => '현재 진행 중인 트라이얼',
        'bottleneck' => '병목 부서',
        'bottleneck_none' => '없음',
        'bottleneck_caption' => '검토 대기 :count건',
        'bottleneck_none_caption' => '모든 검토가 완료되었습니다',
    ],

    'summary' => [
        'total' => '전체 트라이얼',
        'total_breakdown' => 'Mixing: :mixing · Filling: :filling',
    ],

    'charts' => [
        'trend_title' => '트라이얼 생성 추이',
        'trend_series' => '생성된 트라이얼',
        'status_title' => '상태별 분포',
        'product_type_title' => '제품 유형별 현황',
        'product_type_share_title' => '제품 유형별 트라이얼 비율',
        'department_pending_title' => '부서별 검토 대기 현황',
        'count_label' => '트라이얼 수',
        'other' => '기타',
        'empty_trials' => '트라이얼이 없습니다.',
        'empty_pending_reviews' => '대기 중인 검토가 없습니다.',
    ],

    'filters' => [
        'search_placeholder' => '트라이얼, 제품, FG 코드, 카테고리, 범위, 설비',
        'search' => '검색',
        'status' => '상태',
        'product_type' => '제품 유형',
        'trial_type' => '트라이얼 유형',
        'trial_type_placeholder' => '전체 트라이얼 유형',
        'date_from' => '시작일',
        'date_to' => '종료일',
        'chip' => ':label: :value',
        'empty' => '선택한 조건에 해당하는 트라이얼이 없습니다.',
    ],

    'my_work' => [
        'description' => '내 업무 요약: 이어서 작성할 Draft, 수정이 필요한 트라이얼, 진행 중인 트라이얼, 그리고 처리를 기다리는 검토 및 승인 건입니다.',
        'drafts_title' => '내 Draft (이어서 작성)',
        'drafts_empty' => '이어서 작성할 Draft가 없습니다.',
        'drafts_hint' => 'Trial ID를 클릭하여 양식 작성을 계속하십시오.',
        'revision_title' => '수정 필요',
        'revision_empty' => '수정이 필요한 트라이얼이 없습니다.',
        'revision_hint' => 'Trial ID를 클릭하여 수정한 후 다시 제출하십시오.',
        'in_progress_title' => '진행 중',
        'in_progress_empty' => '진행 중인 트라이얼이 없습니다.',
        'in_progress_hint' => '검토/승인 대기 중입니다. 현재 필요한 조치는 없습니다.',
        'reviews_title' => '내 검토 대기',
        'reviews_empty' => '현재 검토 대기 중인 건이 없습니다.',
        'reviews_view_all' => '검토 대기 전체 보기',
        'approvals_title' => '내 승인 대기',
        'approvals_empty' => '현재 승인 대기 중인 트라이얼이 없습니다.',
        'approvals_view_all' => '승인 대기 전체 보기',
        'waiting' => '대기 중: :name',
        'more' => '외 트라이얼 :count건',
        'last' => '최근: :code',
        'last_decided' => '최근: :code (:decision)',
    ],
];
