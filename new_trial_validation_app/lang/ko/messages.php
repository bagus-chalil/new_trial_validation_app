<?php

// Server-side messages: toast notifications after saving, and the custom
// validation / completeness messages built in FormRequests and Actions.
return [
    'toast' => [
        'trial_created' => '트라이얼이 생성되었습니다.',
        'trial_updated' => '트라이얼이 수정되었습니다.',
        'trial_deleted' => '트라이얼 :code 이(가) 휴지통으로 이동되었습니다.',
        'validation_saved' => '검증 내용이 저장되었습니다.',
        'weighing_saved' => '계량 결과가 저장되었습니다.',
        'photos_uploaded' => '사진 :count장이 업로드되었습니다.',
        'upload_partial' => '일부 파일 업로드에 실패했습니다: :errors',
        'attachment_deleted' => '첨부 파일이 삭제되었습니다.',
        'submitted_for_review' => '트라이얼 검토 요청이 제출되었습니다.',
        'review_saved' => '검토 내용이 저장되었습니다.',
        'approval_saved' => '승인 결정이 저장되었습니다.',
        'additional_uploaded' => '추가 첨부 파일 :count개가 업로드되었습니다.',
        'additional_none_uploaded' => '업로드된 파일이 없습니다.',
        'additional_deleted' => '추가 첨부 파일이 삭제되었습니다.',
        'line_config_saved' => '라인 구성 보고서가 저장되었습니다.',
        'line_config_draft_saved' => '라인 구성 보고서 임시 저장이 완료되었습니다.',
        'line_config_submitted' => '라인 구성 보고서가 승인 요청되었습니다.',
        'line_config_confirmed' => ':label 확인이 완료되었습니다.',
        'line_config_returned' => '라인 구성 보고서가 수정 요청으로 반송되었습니다.',
    ],

    'upload' => [
        'failed' => ':number번째 파일 업로드에 실패했습니다.',
        'too_large' => ':name 파일이 10MB를 초과합니다.',
        'not_image' => ':name 파일은 허용되지 않는 이미지 형식입니다.',
        'store_failed' => ':name 파일을 저장하지 못했습니다.',
    ],

    'completeness' => [
        'required' => ':field 항목은 필수입니다.',
        'no_parameters' => '제품 유형 :type에 대한 검증 파라미터가 아직 설정되지 않았습니다.',
        'no_decision' => '파라미터 :name의 판정이 아직 입력되지 않았습니다.',
        'not_ok_incomplete' => '파라미터 :name이(가) NOT OK이므로 결과와 비고를 반드시 입력해야 합니다.',
    ],

    'validation' => [
        'parameter_missing' => '파라미터 :name이(가) 입력되지 않았습니다.',
        'parameter_not_ok' => '파라미터 :name이(가) NOT OK이므로 결과와 비고는 필수입니다.',
        'weighing_invalid' => '계량 샘플은 숫자여야 하며 음수일 수 없습니다.',
        'weighing_empty' => '계량 샘플을 1개 이상 입력하거나 건너뛰기를 선택해 주십시오.',
        'submit_incomplete' => '아직 검토 요청을 제출할 수 없습니다: :errors',
        'reviewer_required' => ':department 부서의 검토자를 선택해 주십시오.',
        'reviewer_invalid' => '선택한 검토자는 :department 부서에 유효하지 않습니다.',
        'not_ready_for_approval' => '이 트라이얼은 아직 승인 단계가 아닙니다.',
        'signature_wrong' => '전자 서명 비밀번호가 올바르지 않습니다.',
        'return_reason_min_words' => '반송 사유는 최소 5단어 이상 입력해야 합니다.',
        'additional_required' => '파일을 하나 이상 선택해 주십시오.',
        'additional_max_per_upload' => '한 번에 최대 :max개 파일까지 업로드할 수 있습니다.',
        'additional_too_large' => '파일 크기는 최대 10MB입니다.',
        'additional_file_type' => 'PDF 또는 이미지 파일(JPG, PNG, WEBP, GIF)만 허용됩니다.',
        'additional_remaining' => '트라이얼당 최대 :max개 첨부 가능 — 남은 수량 :remaining개.',
        'additional_limit_reached' => '이 트라이얼의 추가 첨부 파일 한도(:max개)에 도달했습니다.',
    ],
];
