<?php

// Notification emails (app/Mail/*, resources/views/emails/*). Rendered in
// the language of the user whose action triggered the email.
return [
    'common' => [
        'greeting' => ':name님, 안녕하십니까.',
        'trial_code' => '트라이얼 코드',
        'product' => '제품',
        'thanks' => '감사합니다.',
    ],

    'review_requested' => [
        'subject' => '트라이얼 :code 검토 요청',
        'heading' => '트라이얼 검토 요청',
        'intro' => ':department 부서의 검토 대상으로 트라이얼이 제출되었으며, 귀하께서 검토자로 지정되었습니다.',
        'button' => '트라이얼 열기 및 검토',
        'outro' => '위 버튼을 클릭하여 트라이얼 상세 내용을 확인하신 후 검토 의견을 제출하여 주십시오.',
    ],

    'approval_requested' => [
        'subject' => '트라이얼 :code 승인 요청',
        'heading' => '트라이얼 승인 요청',
        'intro' => '아래 트라이얼에 대한 모든 부서의 검토가 완료되어 현재 귀하의 승인을 기다리고 있습니다.',
        'button' => '트라이얼 열기 및 승인',
        'outro' => '위 버튼을 클릭하여 트라이얼 상세 내용을 확인하신 후 승인 결정을 제출하여 주십시오.',
    ],

    'line_config_signoff' => [
        'subject' => '라인 구성 보고서 — :label 결재 요청 (:code)',
        'heading' => '라인 구성 보고서: :label 결재',
        'intro' => '아래 트라이얼의 :report에 귀하께서 :label(으)로 지정되었습니다. 이는 트라이얼에 첨부된 생산 라인 구성 양식에 대한 것으로, 트라이얼 자체의 검토/승인 결정이 아닙니다. 트라이얼 전체에 대한 조치는 필요하지 않으며, 해당 보고서에 대한 결재만 요청드립니다.',
        'button' => '라인 구성 보고서 열기',
        'outro' => '위 버튼을 클릭하신 후 :report 항목으로 이동하여 :label 버튼으로 확인하여 주십시오. 일자는 자동으로 기록됩니다.',
    ],

    'line_config_returned' => [
        'subject' => '라인 구성 보고서 수정 요청 반송 (:code)',
        'heading' => '라인 구성 보고서 수정 요청 반송',
        'intro' => '아래 트라이얼에 대해 귀하께서 작성하신 :report이(가) 수정을 위해 반송되었습니다. 이는 해당 보고서에 한한 것으로, 트라이얼 자체의 검토/승인 결정과는 무관합니다.',
        'intro_by' => '아래 트라이얼에 대해 귀하께서 작성하신 :report이(가) :stage에 의해 수정을 위해 반송되었습니다. 이는 해당 보고서에 한한 것으로, 트라이얼 자체의 검토/승인 결정과는 무관합니다.',
        'reason' => '반송 사유:',
        'button' => '라인 구성 보고서 열기',
        'outro' => '보고서를 수정하신 후 다시 결재를 요청하여 주십시오.',
    ],
];
