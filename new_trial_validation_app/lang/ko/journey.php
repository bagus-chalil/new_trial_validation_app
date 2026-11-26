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
        'classification_saved' => '분류가 저장되었습니다.',
        'fg_verified' => 'FG 매핑이 확인되었습니다.',
    ],

    'classification' => [
        'title' => '분류',
        'description' => '공정 영역, 목적, 대상, 문서 유형은 서로 독립된 축입니다. Case 번호는 바뀌지 않습니다.',
        'process_area' => '공정 영역',
        'purpose' => '트라이얼 목적',
        'subject' => '트라이얼 대상',
        'document_type' => '문서 유형',
        'other_note' => '어떤 트라이얼입니까?',
        'derived_hint' => 'Case 유형에서 제안된 값입니다. 저장하여 확정하세요.',
        'edit' => '분류 수정',
        'save' => '분류 저장',
        'not_set' => '미지정',
        'process_areas' => ['Mixing' => '믹싱', 'Filling-Packing' => '충진·포장'],
        'purposes' => ['NPD' => 'NPD', 'Improvement' => '개선', 'Other' => '기타 엔지니어링 트라이얼'],
        'subjects' => ['Product' => '제품', 'Machine' => '설비', 'RM' => '원료(RM)', 'PM' => '포장재(PM)', 'Process' => '공정'],
        'document_types' => ['Validation Plan' => '밸리데이션 계획', 'Trial Report' => '트라이얼 보고서', 'Line Configuration' => '라인 구성'],
    ],

    'cohort' => [
        'all' => '전체 데이터',
        'eligible' => '대상 (:date 이후)',
        'historical' => '과거 (:date 이전)',
        'badge_eligible' => '대상',
        'badge_historical' => '과거',
        'historical_hint' => ':date 이전에 생성됨: 참고용으로만 표시되며 새 KPI에 포함되지 않습니다.',
    ],

    'fg' => [
        'title' => 'FG 식별',
        'official_name' => '공식 품목명 (iERP)',
        'verify' => '매핑 확인',
        'cannot_verify' => '이 FG 코드는 iERP 가져오기에 없거나 이미 다른 제품에 연결되어 있습니다.',
        'status' => [
            'verified' => '확인됨',
            'unverified' => '등록됨, 미확인',
            'mismatch' => '다른 제품에 매핑됨',
            'missing' => '공식 FG 없음',
        ],
        'hint' => [
            'verified' => 'FG 코드가 iERP 공식 목록과 일치하며 담당자가 확인했습니다.',
            'unverified' => '코드가 iERP에 있습니다. PIE 또는 QA가 이 제품의 코드인지 확인해야 합니다.',
            'mismatch' => '공식 목록은 이 코드를 다른 제품에 매핑합니다. 제품 마스터를 확인하세요.',
            'missing' => 'FG가 생기기 전에도 트라이얼을 시작할 수 있습니다. iERP가 코드를 발급하면 매핑하세요.',
        ],
    ],
];
