<?php

// 승인자와 검토자를 위한 트라이얼 요약 (트라이얼별 보고서 요약 화면). `facts.*` 문장은 화면에 표시되고
// 동시에 Vision Service로 전송되므로, 한 문장에 한 가지 사실만 담고 사람 이름은 넣지 않는다.
return [
    'title' => '트라이얼 요약',
    'subtitle' => '기록된 트라이얼 데이터를 바탕으로 정리했습니다. 읽는 데 도움을 줄 뿐이며, 결정은 담당자께 있습니다.',
    'button' => 'AI 요약 작성',
    'button_busy' => '작성 중...',
    'wait_hint' => '최대 1~2분이 걸릴 수 있습니다 (AI 서버는 요청을 하나씩 처리합니다).',
    'ai_label' => 'AI 요약 (참고용)',
    'finding' => '발견 사항',

    'errors' => [
        'unreachable' => '이 서버에서 Vision Service에 연결할 수 없습니다.',
        'outdated' => '서버의 Vision Service가 아직 업데이트되지 않았습니다. 아래 요약 항목을 이용해 주십시오.',
        'rejected' => 'Vision Service가 AI 요약을 거부했습니다 (권고 규칙 위반). 아래 요약 항목을 이용해 주십시오.',
        'failed' => 'AI 요약에 실패했습니다 (Ollama/Vision Service가 응답하지 않았습니다).',
        'busy' => '이전 요약이 아직 진행 중입니다. 잠시 기다려 주십시오.',
        'network' => '서버에 연결할 수 없습니다.',
    ],

    'facts' => [
        'identity' => '트라이얼 :code, 제품 :product (유형 :type), 검증 구분 :category, 위험도 :risk.',
        'identity_anonymous' => '트라이얼 :code, 제품 유형 :type, 검증 구분 :category, 위험도 :risk.',
        'status' => '상태 :status, 단계 :step, 검토 :round차.',
        'revised' => '이 트라이얼은 :count회 수정되었습니다.',
        'decision_note' => '승인자 결정 메모: :note',
        'validation_none' => '아직 검증 결과가 없습니다.',
        'validation_counts' => '검증: 전체 :total개 항목 중 :ok개 OK, :not_ok개 NOT OK, :na개 N/A.',
        'validation_missing' => '아직 입력되지 않은 검증 항목이 :count개 있습니다.',
        'not_ok' => 'NOT OK: :parameter, 결과 ":result", 비고 ":remark".',
        'not_ok_more' => '표시되지 않은 NOT OK 항목이 :count개 더 있습니다.',
        'weighing_none' => 'Weighing :section 이(가) 아직 입력되지 않았습니다.',
        'weighing_skipped' => 'Weighing :section 은(는) 수행하지 않았습니다 (건너뜀).',
        'weighing_data' => 'Weighing :section: 샘플 :count개, 평균 :avg, 최소 :min, 최대 :max.',
        'photos' => '첨부 사진: :count장.',
        'photos_none' => '첨부된 사진이 아직 없습니다.',
        'review_pending' => '검토 대기 중인 부서: :departments.',
        'review_ok' => '추가 의견 없이 OK로 검토한 부서: :departments.',
        'review_note' => ':department 검토 의견: :note',
        'review_more' => '표시되지 않은 검토 의견이 :count개 더 있습니다.',
    ],
];
