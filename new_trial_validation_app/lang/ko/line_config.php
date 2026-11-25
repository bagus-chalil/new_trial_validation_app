<?php

return [
    'title' => '라인 구성 보고서',
    'dialog_title' => '라인 구성 보고서 (생산)',
    'subtitle' => '생산 — 라인 구성, 생산 기준 및 :first → :second 결재.',
    'empty' => '등록된 라인 구성 보고서가 없습니다.',
    'no_data' => '데이터가 없습니다.',

    'badge' => [
        'draft' => '임시 저장',
        'signed_off' => '결재 완료',
        'in_approval' => '승인 진행 중',
    ],

    'actions' => [
        'edit' => '보고서 수정',
        'create' => '라인 구성 보고서 작성',
        'add_row' => '행 추가',
        'remove_row' => '행 삭제',
        'insert_row_below' => '아래에 행 삽입',
        'save' => '라인 구성 보고서 저장',
        'save_draft' => '임시 저장',
        'submit' => '저장 및 승인 요청',
        'approve' => '승인',
        'mark_checked' => '확인 완료 처리',
        'return' => '반송',
        'download_pdf' => 'PDF',
        'download_current_pdf' => 'PDF 다운로드',
    ],

    'return_note' => [
        'title' => '수정 요청으로 반송됨',
        'approver' => '승인자',
    ],

    'versions' => [
        'title' => '버전 이력',
        'hint' => '반송된 버전은 이곳에 잠금 보관됩니다. 다운로드만 가능하며 더 이상 수정할 수 없습니다.',
        'locked_at' => '잠금 일시 :date',
    ],

    'sign_off' => [
        'title' => '결재',
        'select_user' => '사용자 선택...',
        'search_user' => '사용자 검색...',
        'no_eligible_users' => ':lane 팀 요건을 충족하는 사용자가 없습니다. 접근 권한 / 레인 구성에서 설정하십시오.',
        'hint' => '결재 요청 후 저장하면 이 양식이 잠깁니다. 담당자를 지정할 필요가 없습니다. 권한이 있는 사용자가 먼저 :first 처리를 하고, 이후 권한이 있는 사용자가 :second 처리를 합니다. 일시는 서명 시점에 자동으로 기록되며, 반송될 때까지 양식은 잠긴 상태로 유지됩니다.',
        'not_confirmed' => '미확인 상태입니다.',
        'confirmed_by' => ':name 확인',
        'confirmed_by_at' => ':name 확인 (:date)',
    ],

    'fields' => [
        'date' => '일자',
        'client' => '고객사',
        'validation' => '검증',
        'pic' => '담당자',
        'operator' => '작업자',
        'total' => '총 수량',
        'setting' => '세팅',
        'pass' => '합격',
        'ng' => '불량',
        'ng_hint' => '총 수량 − 세팅 − 합격으로 자동 계산됩니다(필요 시 직접 수정할 수 있습니다).',
        'opinion' => '의견',
    ],

    'sections' => [
        'production_standard' => '생산 기준',
        'line_configuration' => '라인 구성',
    ],

    'columns' => [
        'line' => '라인',
        'workers' => '인원',
        'capacity' => '생산능력/속도',
        'remark' => '비고',
        'no' => 'No.',
        'equipment' => '설비',
        'process' => '공정',
        'worker' => '인원',
        'trial' => '트라이얼',
    ],

    'validation' => [
        'rows_required' => ':section 행을 최소 1개 이상 입력해야 합니다.',
        'row_field_required' => ':position행 — :attribute 항목은 필수입니다.',
        'summary' => '임시 저장은 미완성이어도 됩니다. 승인 요청 시에는 두 명의 승인자를 포함한 모든 항목이 필수입니다.',
        'already_submitted' => '이 보고서는 이미 승인 요청되어 임시 저장 상태로 되돌릴 수 없습니다.',
        'draft_hint' => '승인자는 승인 요청 시에만 저장됩니다.',
    ],

    'total_workers' => '총 인원',
    'total_workers_inline' => '총 인원:',

    'trial_status' => [
        'pass' => '합격',
        'no_trial' => '미실시',
    ],

    'stage' => [
        'prepared' => '작성 (PIE)',
        'not_filled' => '미입력',
        'not_assigned' => '미지정',
        'not_submitted' => '아직 제출되지 않음',
        'waiting_any' => '권한 있는 사용자 처리 대기',
        'confirmed' => '확인 완료',
        'waiting_for' => ':name 처리 대기',
        'awaiting_you' => '귀하의 처리 대기 중',
        'assigned_to' => '담당자: :name',
    ],

    'action_panel' => [
        'title' => '처리 필요: :lane',
        'description' => '이 보고서의 :lane을(를) 확인하거나 수정을 위해 반송하십시오.',
        'comment' => '의견',
        'comment_placeholder' => '의견 (확인 시 선택 사항, 반송 시 필수)...',
        'min_words' => '반송 시 최소 :count단어가 필요합니다 —',
    ],
];
