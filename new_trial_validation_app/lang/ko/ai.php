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
    ],
];
