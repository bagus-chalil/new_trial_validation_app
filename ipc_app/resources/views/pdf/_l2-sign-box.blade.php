{{-- Level 2 approval signature (QC Team Leader / Part Leader / Supervisor), two-level approval 2026-11-21.
     Shown beside the Level 1 "Review By" box; empty until Level 2 has approved. --}}
<div>
    <span>Approved By (L2)</span>
    @if ($approval && $approval->l2_decision === \App\Models\IpcApproval::DECISION_APPROVED)
        <strong>{{ $approval->l2Approver->name ?? '—' }}</strong>
        <small class="sign-date">{{ optional($approval->l2_decided_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
    @endif
</div>
