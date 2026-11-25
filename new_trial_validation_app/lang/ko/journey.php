<?php

// Trial Validation v2.0: Product Journey (Case) pages and related fields.
return [
    'title' => '제품 여정',
    'description' => '제품별 하나의 이력: 트라이얼 횟수, 각 시도의 결과, 연결된 문서.',
    'search_placeholder' => '케이스 번호, 제품 또는 FG 코드',
    'all_types' => '전체 유형',
    'empty' => '해당하는 케이스가 아직 없습니다.',

    'type' => [
        'NPD' => 'NPD',
        'IMP' => '개선(Improvement)',
        'MIX' => '믹싱(Mixing)',
    ],

    'result' => [
        'pass' => 'Pass',
        'pass_client' => '고객 승인 Pass',
        'retrial' => 'Re-trial',
        'fail' => 'Fail',
        'pending' => '결과 대기',
    ],

    'columns' => [
        'case_no' => '케이스 번호',
        'product' => '제품',
        'fg' => 'FG 코드',
        'type' => '유형',
        'attempts' => '트라이얼 횟수',
        'last_result' => '최근 결과',
    ],

    'summary' => [
        'attempts' => '트라이얼 :count회',
        'none' => '제출된 트라이얼이 아직 없습니다',
        'last_result' => '최근 결과',
        'lead_time' => '평균 리드타임',
        'lead_time_value' => ':days일 (트라이얼 :samples건)',
        'lead_time_none' => 'PM/Bulk 준비일이 아직 입력되지 않았습니다',
        'first_trial' => '첫 트라이얼',
        'breakdown' => '결과 내역',
        'fg_master_note' => 'FG 코드는 제품 마스터에서 읽습니다.',
    ],

    'attempt' => [
        'title' => ':no번째 시도',
        'timeline' => '타임라인',
        'trial_date' => '트라이얼 날짜',
        'decided_at' => '결정일',
        'pending_with' => '대기 중',
        'revision' => '수정 :no차',
        'pm_ready' => 'PM 준비',
        'bulk_ready' => 'Bulk 준비',
        'dates_defaulted' => '기본 날짜(D-1), 리드타임에서 제외',
        'lead_time' => '리드타임 :days일',
        'stale_fg' => '트라이얼의 FG 코드가 오래되었습니다. 마스터를 사용합니다',
        'has_not_ok' => 'NOT OK 항목 있음',
        'line_config' => '라인 구성: :status',
        'open_trial' => '트라이얼 보고서 열기',
        'private' => '이 트라이얼에 접근할 수 없습니다',
    ],

    'line_config_status' => [
        'Draft' => '초안',
        'Submitted' => '제출됨',
        'Approved' => '승인됨',
    ],

    'client' => [
        'title' => '고객 승인',
        'approved' => '고객 승인함',
        'note' => '메모 / 증빙 (문서 번호, 이름, 날짜)',
        'evidence_hint' => '트라이얼 추가 첨부로 문서를 올린 뒤 여기에 참조를 적어 주세요.',
        'save' => '저장',
        'clear' => '승인 삭제',
        'edit' => '고객 승인 기록',
    ],

    'projects' => [
        'title' => '연결된 프로젝트',
        'empty' => '이 케이스에 연결된 프로젝트가 아직 없습니다.',
    ],

    'form' => [
        'pm_ready_date' => 'PM 준비일',
        'bulk_ready_date' => 'Bulk 준비일',
    ],

    'kpi' => [
        'title' => '실제 트라이얼 리드타임 (NPD)',
        'value' => ':days일',
        'none' => '데이터 없음',
        'note' => '트라이얼 날짜에서 마지막 PM/Bulk 준비일을 뺀 값 (실제 날짜가 있는 NPD 트라이얼 :samples건).',
        'excluded' => '기본 날짜를 쓰는 :count건은 제외됩니다.',
    ],

    'toast' => [
        'client_approval_saved' => '고객 승인이 저장되었습니다.',
    ],
];
