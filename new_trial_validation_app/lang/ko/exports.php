<?php

// PDF (resources/views/pdf/*) and Excel exports. On-screen terms are reused
// from report.php / line_config.php; only export-specific text lives here.
return [
    // Carbon translatedFormat() patterns (App\Services\Pdf\ExportFormat).
    'formats' => [
        'datetime' => 'Y년 n월 j일 H:i',
        'date' => 'Y년 n월 j일',
    ],

    'titles' => [
        'approved' => '승인 완료 보고서',
        'rejected' => '반려 보고서',
        'trial_summary' => '트라이얼 요약 보고서',
        'department_review' => '부서별 검토 보고서',
        'audit_print_log' => '출력 감사 로그',
        'line_config_version' => '라인 구성 보고서 — :code (v:version)',
    ],

    'columns' => [
        'fg_code' => '완제품 코드',
        'approved_date' => '승인일',
        'rejected_date' => '반려일',
        'reason' => '사유 / 최종 의견',
        'printed_by' => '출력자',
        'printed_at' => '출력 일시',
        'report_type' => '보고서 유형',
        'review_status' => '검토 상태',
        'pending_department' => '검토 대기 부서',
        'current_step' => '현재 단계',
        'created_date' => '작성일',
    ],

    'empty' => [
        'approved' => '승인 완료된 보고서가 없습니다.',
        'rejected' => '반려된 보고서가 없습니다.',
        'trial_summary' => '트라이얼 데이터가 없습니다.',
        'department_review' => '부서별 검토 데이터가 없습니다.',
        'audit_print_log' => '출력 감사 로그가 없습니다.',
    ],

    'default_report_type' => '보고서',

    'attachments' => [
        'file_not_found' => '파일을 찾을 수 없습니다',
        'pdf_listed_only' => 'PDF 파일 — 보고서 화면에서 다운로드하십시오',
    ],

    'line_config' => [
        'version' => '버전 :version',
        'locked' => '잠금 (읽기 전용)',
        'latest' => '최신',
        'return_prod' => '반송 (PROD)',
        'yes' => '예',
    ],

    'excel' => [
        'sheets' => [
            'info' => '트라이얼 정보',
            'validation' => '검증',
            'weighing' => '계량',
            'review' => '부서별 검토',
            'decision' => '결정',
        ],
        'info_title' => '트라이얼 정보',
        'section' => '구분',
        'reviewer' => '검토자',
        'no_data' => '데이터가 없습니다.',
    ],
];
