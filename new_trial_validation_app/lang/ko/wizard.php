<?php

// Trial wizard pages (Header form, Validation, Weighing, Attachments, Review
// & Submit). Field labels shared with the Report page come from
// report.info / report.header / report.validation / report.weighing.
return [
    'save_next' => '저장 후 계속',
    'progress_note' => '이 트라이얼은 이미 :step 단계까지 진행되었습니다.',

    'form' => [
        'title_new' => '신규 트라이얼',
        'title_edit' => '트라이얼 수정 — :code',
        'description' => '트라이얼 기본 정보를 입력해 주십시오. 검증, 계량, 검토 및 승인은 이후 별도 화면에서 진행됩니다.',
        'sections' => [
            'product' => '제품 정보',
            'scope' => '범위 및 설비',
            'batch' => '배치 및 담당 팀',
            'reason' => '사유 및 BOM',
        ],
        'product' => '제품',
        'placeholders' => [
            'product' => '제품 선택',
            'product_search' => '제품 검색...',
            'product_type' => '제품 유형 선택',
            'validation_category' => '분류 선택',
            'risk_level' => '위험 수준 선택',
            'validation_scope' => '범위 선택',
            'validation_scope_search' => '범위 검색...',
            'machine_used' => '설비 선택',
            'machine_used_search' => '설비 검색...',
        ],
        'save_changes' => '변경 사항 저장',
    ],

    'validation' => [
        'page_title' => '검증 — :code',
        'title' => '트라이얼 파라미터 검증 — :type',
        'description' => '각 검증 파라미터의 판정, 결과 및 비고를 입력해 주십시오.',
        'no_parameters' => '이 제품 유형에 대한 검증 파라미터가 아직 설정되지 않았습니다.',
    ],

    'weighing' => [
        'page_title' => ':label 계량 — :code',
        'title' => ':label 계량',
        'description' => '트라이얼 :code의 샘플링 결과를 입력해 주십시오.',
        'labels' => [
            'packaging' => '빈 포장재 (g)',
            'filling' => '충전 중량 (g)',
        ],
        'skip' => ':label 건너뛰기 (N/A)',
        'add_sample' => '샘플 추가',
        'remove_sample' => '샘플 :number 삭제',
    ],

    'attachments' => [
        'page_title' => '첨부 파일 — :code',
        'title' => '첨부 파일',
        'description' => '트라이얼 :code의 증빙 사진을 업로드하고 관리합니다.',
        'upload_title' => '사진 업로드',
        'category' => '분류',
        'category_placeholder' => '분류 선택...',
        'category_search' => '분류 검색...',
        'caption' => '설명 (선택 사항)',
        'caption_placeholder' => '이 사진에 대한 간단한 설명을 입력해 주십시오...',
        'photos' => '사진',
        'upload' => '업로드',
        'remove' => '제외',
        'delete' => '삭제',
        'delete_confirm' => '이 사진을 삭제하시겠습니까?',
        'readonly' => '첨부 파일은 읽기 전용입니다. 사진은 작성 중 또는 수정 필요 상태에서만 삭제할 수 있습니다.',
        'photo_count' => '사진 :count장',
        'empty' => '첨부 파일이 없습니다.',
        'continue_review' => '검토 단계로 이동',
    ],

    'review' => [
        'page_title' => '검토 — :code',
        'title' => '검토 및 제출',
        'description' => '트라이얼 :code을(를) 관련 부서의 검토 대상으로 제출합니다.',
        'status_title' => '부서별 검토 현황',
        'assigned_to' => '담당자',
        'not_ready' => '아직 검토 요청을 제출할 수 없습니다',
        'select_title' => '검토 부서 / 팀 선택',
        'no_reviewer' => ':department 검토 팀에 배정된 사용자가 없습니다. 접근 권한 화면에서 설정해 주십시오.',
        'reviewer_placeholder' => '검토자 선택...',
        'reviewer_search' => '검토자 검색...',
        'approver' => '승인자',
        'approver_placeholder' => '승인자 선택...',
        'approver_search' => '승인자 검색...',
        'view_detail' => '트라이얼 상세 보기',
        'submit' => '검토 요청 제출',
    ],
];
