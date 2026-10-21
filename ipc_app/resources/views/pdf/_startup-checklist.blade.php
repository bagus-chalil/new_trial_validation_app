{{--
    "FORM PEMERIKSAAN START UP" — the 9-item (17 sub-item) Pemeriksaan Awal checklist + sign-off
    row, shared by the Startup report (approval-startup.blade.php) and, since both paper forms
    FR.QAC.192.02 (Filling) and FR.QAC.182.03 (Packing) embed this exact same section at their
    own top, the Filling/Packing report (approval-filling-packing.blade.php) too — extracted here
    so the three reports can't drift apart on this shared section.

    Expects $startupCheck and $startupChecklistGroups (StartupCheck::checklistGroups()) in scope.
--}}
@if ($startupCheck)
    <div class="startup-layout">
        <div class="startup-main">
            <table>
                <thead>
                    <tr><th style="width: 5%;">No</th><th>Parameter Pemeriksaan</th><th style="width: 24%;">Sesuai (Y/N)</th></tr>
                </thead>
                <tbody>
                    @php $no = 1; @endphp
                    @foreach ($startupChecklistGroups as $group)
                        @php
                            $machineFields = collect($group['fields'])->filter(fn ($label, $field) => str_starts_with($field, 'machine_'));
                            $otherFields = collect($group['fields'])->reject(fn ($label, $field) => str_starts_with($field, 'machine_'));
                        @endphp
                        @foreach ($otherFields as $field => $label)
                            <tr>
                                <td class="center">{{ $no++ }}</td>
                                <td>{{ $label }}</td>
                                <td>@include('pdf._status-pill', ['value' => $startupCheck[$field] ?? null])</td>
                            </tr>
                        @endforeach
                        @if ($machineFields->count() > 0)
                            <tr>
                                <td class="center">{{ $no++ }}</td>
                                <td>Check Detection Machine</td>
                                <td class="nested-cell">
                                    <table class="nested-table">
                                        <tbody>
                                            @foreach ($machineFields as $field => $label)
                                                <tr>
                                                    <td>{{ str_replace('Machine ', '', $label) }}</td>
                                                    <td>@include('pdf._status-pill', ['value' => $startupCheck[$field] ?? null])</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    <tr>
                        <td class="center">{{ $no++ }}</td>
                        <td>Validation Report (NPD Product)</td>
                        <td>@include('pdf._status-pill', ['value' => $startupCheck->validation_report_status ?? null])</td>
                    </tr>
                </tbody>
            </table>

            <div class="two-col">
                <table>
                    <tbody>
                        <tr><td><strong>Setting Suhu</strong></td><td>{{ $startupCheck->heating ?? '—' }}</td></tr>
                        <tr><td><strong>Density</strong></td><td>{{ $startupCheck?->density_not_applicable ? 'N/A' : ($startupCheck?->density ?? '—') }}</td></tr>
                    </tbody>
                </table>
                <table>
                    <tbody>
                        <tr><td><strong>Filling Range Min</strong></td><td>{{ $startupCheck->filling_range_min ?? '—' }}</td></tr>
                        <tr><td><strong>Filling Range Max</strong></td><td>{{ $startupCheck->filling_range_max ?? '—' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="startup-side">
            {{-- Operator / Line Leader are typed in on the Startup Check form; the IPC verifier
                 is whoever saved that form. Paper form labels these Prepared By (Operator) /
                 Review By (LL Produksi) / Verification By (IPC). --}}
            <div class="sign-grid sign-grid--stack">
                <div>
                    <span>Prepared By</span>
                    <strong>{{ $startupCheck->operator_name ?: '—' }}</strong>
                    <small class="role">Operator</small>
                </div>
                <div>
                    <span>Review By</span>
                    <strong>{{ $startupCheck->line_leader_name ?: '—' }}</strong>
                    <small class="role">LL Produksi</small>
                </div>
                <div>
                    <span>Verification By</span>
                    <strong>{{ $startupCheck->user->name ?? '—' }}</strong>
                    <small class="sign-date">{{ optional($startupCheck->completed_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
                    <small class="role">IPC</small>
                </div>
            </div>
        </div>
    </div>
@else
    <p class="muted">Startup Check belum diisi.</p>
@endif
