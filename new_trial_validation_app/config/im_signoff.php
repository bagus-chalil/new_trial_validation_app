<?php

/*
 | IM sign-off sheet (mirrors the VALIDATION page of the paper IM).
 |
 | review_labels:   titles the QA preparer can give a reviewer row.
 | knowledge_slots: "Knowledge by" rows signed electronically by the matching
 |                  team. `teams` are matched (case-insensitive) against the
 |                  account's department or review unit, so membership is
 |                  managed in Access Rights (Team master), not here. These
 |                  rows never block release: they record who acknowledged.
 */
return [
    'prepared_label' => 'QA Staff',
    'approved_label' => 'QAC Leader',

    'review_labels' => ['QA Part Leader', 'QA Support', 'Regulatory', 'Packaging Development'],

    'knowledge_slots' => [
        ['label' => 'QC Part Leader', 'teams' => ['QC', 'QCO']],
        ['label' => 'RNI Team', 'teams' => ['RNI']],
        ['label' => 'Production Leader', 'teams' => ['PRD', 'PROD', 'PRODUCTION']],
        ['label' => 'PPIC Leader', 'teams' => ['PPIC']],
    ],
];
