<?php

// Rule-based trial bot: answer templates (App\Services\Ai\Bot\BotAnswerComposer).
// Placeholders use :name. Every key must exist in id/en/ko.
return [
    'title' => '트라이얼 어시스턴트',

    'answers' => [
        'my_queue' => [
            'intro' => '현재 처리하실 업무는 다음과 같습니다:',
            'draft' => '이어서 작성하실 초안(Draft) 트라이얼 :count건.',
            'need_revision' => '수정이 필요한(Need Revision) 트라이얼 :count건.',
            'in_progress' => '진행 중인 본인 트라이얼 :count건 (In Review / Ready for Approval), 조치가 필요하지 않습니다.',
            'pending_reviews' => '검토 대기 :count건.',
            'pending_approvals' => '승인 대기 트라이얼 :count건.',
            'empty' => '현재 대기 중인 업무가 없습니다.',
        ],

        'trials_by_status' => [
            'total' => '트라이얼이 총 :total건 있습니다:filter.',
            'none' => '조건에 맞는 트라이얼이 없습니다:filter.',
            'latest' => '최신순:',
            'filter_status' => ' (상태: :status)',
            'filter_type' => ' (제품 유형: :type)',
            'more' => '(상위 :shown건 표시)',
        ],

        'trial_status' => [
            'found' => '트라이얼 :code (:product): 상태 :status, 단계 :step.',
            'pending_with' => '현재 :who 대기 중입니다.',
            'revision' => '수정 차수 :no.',
            'not_found' => '트라이얼 :code 을(를) 찾을 수 없거나 접근 권한이 없습니다.',
        ],

        'stuck_trials' => [
            'total' => ':days일 넘게 진행되지 않은 트라이얼이 :total건 있습니다.',
            'none' => ':days일 넘게 정체된 트라이얼이 없습니다.',
            'item' => ':code (:product) - :status, :who 대기, 최종 수정 :date',
            'nobody' => '담당자 미지정',
        ],

        'not_ok_hotspots' => [
            'intro' => 'NOT OK가 가장 자주 발생하는 파라미터:',
            'item' => ':type / :parameter: 트라이얼 :total건 중 :count건 (:rate%)',
            'none' => '보고할 만큼 뚜렷한 NOT OK 패턴이 아직 없습니다 (그룹당 최소 5건 필요).',
            'scope' => '과거 트라이얼 데이터에서 자동 학습한 결과입니다.',
        ],

        'weighing_norm' => [
            'found' => ':type :section 의 칭량 샘플 간 정상 편차(변동계수)는 약 :median% 입니다. :high% 를 넘으면 이상으로 봅니다. 트라이얼 :n건 기준, 신뢰도 :confidence.',
            'none' => ':type :section 에 대해 신뢰할 만한 칭량 기준이 아직 없습니다 (실제 칭량한 트라이얼 최소 5건 필요).',
            'need_type' => '제품 유형과 구간을 함께 말씀해 주세요. 예: "Tube Packaging 칭량 편차".',
        ],

        'summary' => [
            'intro' => '확인 가능한 트라이얼 요약: 총 :total건 (충전 :filling건, 믹싱 :mixing건).',
            'none' => '확인 가능한 트라이얼이 아직 없습니다.',
        ],

        'my_trials' => [
            'total' => '내가 만든 트라이얼이 :total건 있습니다:filter.',
            'none' => '내가 만든 트라이얼이 없습니다:filter.',
        ],

        'latest_trials' => [
            'intro' => '최신 트라이얼 :count건:filter:',
            'none' => '조건에 맞는 트라이얼이 없습니다:filter.',
        ],

        'search_trials' => [
            'total' => '":keyword" 와(과) 일치하는 트라이얼 :total건:',
            'none' => '":keyword" 와(과) 일치하는 트라이얼이 없거나 접근 권한이 없습니다.',
        ],

        'trial_not_ok' => [
            'intro' => '트라이얼 :code 은(는) 점검한 :total개 항목 중 :count개가 NOT OK입니다:',
            'item' => ':parameter - 결과: :result; 비고: :remark',
            'none' => '트라이얼 :code 에는 NOT OK 항목이 없습니다.',
            'not_validated' => '트라이얼 :code 에는 아직 검증 결과가 없습니다.',
        ],

        'trial_reviews' => [
            'intro' => '트라이얼 :code 검토 현황 (:round차):',
            'done' => ':dept: :who 님이 :date 에 검토 완료',
            'pending' => ':dept: 아직 검토되지 않음, :who 님 대기 중',
            'none' => '트라이얼 :code 은(는) 아직 검토에 제출되지 않았습니다.',
        ],

        'step_duration' => [
            'intro' => '단계별 소요 시간 (과거 트라이얼에서 학습, 일반적 / 지연 기준):',
            'item' => ':stage:scope: 일반적으로 :typical, :slow 초과 시 지연으로 간주 (:n건)',
            'stages' => [
                'prepare' => '트라이얼 준비 (생성부터 제출까지)',
                'review' => '부서 검토',
                'approval' => '최종 승인',
            ],
            'hours' => ':n시간',
            'days' => ':n일',
            'none' => '신뢰할 수 있는 소요 시간 데이터가 아직 충분하지 않습니다 (단계당 최소 5건 필요).',
            'scope' => '과거 트라이얼 데이터에서 자동 학습한 결과입니다.',
        ],

        'how_to' => [
            'choose' => '어떤 방법이 궁금하신가요? 아래 예시를 사용해 보세요:',
            'topics' => [
                'create_trial' => "새 트라이얼 만드는 방법:\n1. Trials > New Trial 메뉴를 엽니다.\n2. 헤더 정보(제품, 범위, 설비, 배치 등)를 입력하고 저장 & 계속을 누릅니다.\n3. Validation, Weighing Packaging, Weighing Filling, Attachments, Review & Submit 순서로 진행합니다.",
                'validation' => "Validation 작성 방법:\n1. 각 항목의 판정(OK, NOT OK, N/A)을 선택합니다.\n2. NOT OK인 경우 결과와 비고는 필수입니다.\n3. 모든 항목을 입력한 후 저장합니다.",
                'weighing' => "Weighing 작성 방법 (Packaging 후 Filling):\n1. 각 샘플의 무게를 입력합니다 (0 이상의 숫자). 더 필요하면 Add Sample을 사용합니다.\n2. 해당 구간을 측정하지 않으면 Skip을 체크합니다.\n3. 저장하면 다음 구간으로 넘어갑니다.",
                'attachments' => "사진 첨부 방법:\n1. Attachments 단계에서 카테고리를 선택합니다.\n2. 사진(JPG/PNG/WEBP/GIF, 파일당 최대 10MB)을 선택하고 업로드합니다.\n3. 트라이얼을 수정할 수 있는 동안에는 사진을 삭제할 수 있습니다.",
                'submit_review' => "검토 제출 방법:\n1. Validation이 완료되었는지 확인하고 Review & Submit 단계를 엽니다.\n2. 검토 부서를 체크하고 담당자를 선택한 뒤 승인자를 선택합니다.\n3. Submit for Review를 클릭합니다.",
                'do_review' => "검토하는 방법:\n1. Need Review 메뉴에서 대기 중인 트라이얼을 클릭합니다.\n2. Report Summary 페이지의 데이터를 확인합니다.\n3. 검토 의견을 작성하고 제출합니다.",
                'approve' => "승인하는 방법:\n1. Need Approval 메뉴에서 본인에게 지정된 트라이얼을 클릭합니다.\n2. Report Summary를 확인한 뒤 Approve, Need Revision, Reject 중 하나를 선택합니다.\n3. 의견과 본인 계정 비밀번호(전자서명)를 입력합니다.",
                'revision' => "Need Revision 처리 방법:\n1. Need Revision 메뉴에서 본인의 트라이얼을 클릭합니다.\n2. 승인자의 의견에 따라 데이터를 수정합니다.\n3. Review & Submit으로 다시 제출합니다.",
            ],
        ],

        'help' => [
            'text' => '저는 트라이얼 어시스턴트입니다. 다음과 같은 질문에 답할 수 있습니다. 아래 예시를 사용해 보세요.',
        ],

        'unknown' => [
            'text' => '죄송합니다, 아직 그 질문을 이해하지 못했습니다. 관리자가 저에게 가르칠 수 있도록 질문이 기록되었습니다. 그동안 아래 예시를 사용해 보세요.',
        ],
    ],

    'widget' => [
        'placeholder' => '트라이얼에 대해 질문하세요…',
        'send' => '보내기',
        'close' => '닫기',
        'open' => '어시스턴트 열기',
        'helpful' => '도움이 됨',
        'not_helpful' => '도움이 안 됨',
        'thanks' => '의견 감사합니다.',
        'error' => '오류가 발생했습니다. 다시 시도해 주세요.',
        'try_asking' => '이렇게 물어보세요:',
        'greeting' => '안녕하세요! 업무, 트라이얼 상태, 지연된 트라이얼을 물어보세요.',
        'open_trial' => '트라이얼 열기',
    ],

    'confidence' => [
        'rendah' => '낮음',
        'sedang' => '보통',
        'tinggi' => '높음',
    ],

    'examples' => [
        'my_queue' => '오늘 제 업무가 무엇인가요?',
        'trials_by_status' => 'In Review 상태의 트라이얼은 몇 건인가요?',
        'trial_status' => 'TRIAL-20260914-124439-QF1D 상태',
        'stuck_trials' => '14일 넘게 정체된 트라이얼은 무엇인가요?',
        'not_ok_hotspots' => '가장 자주 NOT OK가 나오는 파라미터는?',
        'weighing_norm' => 'Tube Packaging 정상 칭량 편차는 얼마인가요?',
        'summary' => '전체 트라이얼 요약 보여줘',
        'my_trials' => 'Draft 상태의 내 트라이얼 보여줘',
        'latest_trials' => '최신 트라이얼 5건 보여줘',
        'search_trials' => 'Tube 트라이얼 찾아줘',
        'trial_not_ok' => 'TRIAL-20260914-124439-QF1D 은(는) 왜 NOT OK야?',
        'trial_reviews' => 'TRIAL-20260914-124439-QF1D 검토자 누구야?',
        'step_duration' => '검토는 보통 얼마나 걸려?',
        'how_to' => '새 트라이얼은 어떻게 만들어?',
    ],

    'how_to_examples' => [
        'create_trial' => '새 트라이얼은 어떻게 만들어?',
        'validation' => '검증은 어떻게 작성해?',
        'weighing' => '칭량은 어떻게 입력해?',
        'attachments' => '첨부 사진은 어떻게 올려?',
        'submit_review' => '검토 제출 방법은?',
        'do_review' => '검토하는 방법은?',
        'approve' => '승인하는 방법은?',
        'revision' => '수정 요청은 어떻게 처리해?',
    ],
];
