        {{-- FR.QAC.182.03's own sign-off: two signatures ("Prepared By (Line Leader Production)" /
             "Review By (QC IPC)") at the very bottom of its page — see pdf._startup-checklist's own
             comment for why this is split out from the shared 3-signature block it uses for Filling.
             Same data source as that block's Review By / Verification By fields (line_leader_name /
             the startup check's own verifier), just different roles/position for this form. --}}
        <div class="sign-grid sign-grid--tight sign-grid--role-tight" style="margin: 6px 0;">
            <div>
                <span>Prepared By</span>
                <strong>{{ $startupCheck->line_leader_name ?: '—' }}</strong>
                <small class="role">Line Leader Production</small>
            </div>
            <div>
                <span>Review By</span>
                <strong>{{ $startupCheck->user->name ?? '—' }}</strong>
                <small class="sign-date">{{ optional($startupCheck->completed_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
                <small class="role">QC IPC</small>
            </div>
        </div>

