{{--
    "FORM PEMERIKSAAN START UP" — the 9-item (17 sub-item) Pemeriksaan Awal checklist + sign-off
    row, shared by the Startup report (approval-startup.blade.php) and, since both paper forms
    FR.QAC.192.02 (Filling) and FR.QAC.182.03 (Packing) embed this exact same section at their
    own top, the Filling/Packing report (approval-filling-packing.blade.php) too — extracted here
    so the three reports can't drift apart on this shared section.

    Expects $startupCheck and $startupChecklistGroups (StartupCheck::checklistGroups()) in scope.
--}}
@if ($startupCheck)
    <p style="margin: 0 0 8px; font-size: 9.5px;">
        <strong>Tanggal:</strong> {{ optional($startupCheck->completed_at ?? $startupCheck->created_at)->translatedFormat('d/m/Y') ?? '—' }}
    </p>

    {{-- Paper forms FR.QAC.192.02 / FR.QAC.182.03 show IM Number and "Lampirkan Label/Warna"
         (Warna + Hasil Coding Primer/Sekunder/Tersier) right at the top, beside the Tanggal/Kode
         FG/Kode Bulk/No Batch block — all 3 are real ipc_attachments photo fields on
         startup_checks (im_number/color/coding), same source data approval-startup.blade.php's
         own copy of this header already uses, just not previously rendered on this document. --}}
    <div class="photo-groups">
        <div class="photo-group">
            <div class="photo-group-title">IM Number</div>
            <div class="attachment-grid attachment-grid--cols-1">
                <figure class="attachment-tile attachment-tile--sm">
                    @if ($photoUrls['startup']['im_number'] ?? null)
                        <img src="{{ $photoUrls['startup']['im_number'] }}" alt="IM Number">
                    @else
                        <div class="placeholder">Belum ada foto</div>
                    @endif
                </figure>
            </div>
        </div>

        <div class="photo-group">
            <div class="photo-group-title">Lampirkan Label / Warna Dibawah Ini</div>
            <div class="attachment-grid attachment-grid--cols-2">
                @foreach ([['color', 'Warna'], ['coding', 'Hasil Coding (Primer, Sekunder, Tersier)']] as [$field, $label])
                    <figure class="attachment-tile attachment-tile--sm">
                        @if ($photoUrls['startup'][$field] ?? null)
                            <img src="{{ $photoUrls['startup'][$field] }}" alt="{{ $label }}">
                        @else
                            <div class="placeholder">Belum ada foto</div>
                        @endif
                        <figcaption><strong>{{ $label }}</strong></figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </div>

    <div class="startup-layout">
        <div class="startup-main">
            <table class="record-table--compact">
                <thead>
                    <tr><th style="width: 5%;">No</th><th>Parameter Pemeriksaan</th><th style="width: 30%;">Sesuai (Y/N)</th></tr>
                </thead>
                <tbody>
                    @php
                        // Display order on this report follows the original 9-point paper checklist
                        // (Standar Produk / Sample Challenge Test / WI-IM / Semua PM & BOM / Bulk
                        // Release / Scan NA / Periksa Mesin Pendeteksi / Laporan Validasi / Papan
                        // Identitas Line), with the app's own 4 extra items — not on that original
                        // form — appended at the end instead of interleaved. StartupCheck::checklistGroups()
                        // itself is left untouched (still drives the live Startup Check form and
                        // approval-startup.blade.php's own report) — this ordering/labeling is scoped
                        // to this shared Filling/Packing partial only, direct user request 2026-10-21.
                        $checklistByField = collect($startupChecklistGroups)->flatMap(fn ($group) => $group['fields']);
                        $machineFields = $checklistByField->filter(fn ($label, $field) => str_starts_with($field, 'machine_'));
                        $labelOverrides = [
                            'sample_30pcs_vacuum_status' => 'Vacuum Test',
                            'sample_30pcs_appearance_status' => 'Appearance, Coding, Attribute',
                            'functional_test_status' => 'Functional Test',
                        ];
                        $orderedFields = [
                            'product_standard_status',
                            'sample_challenge_test_status',
                            'wi_im_match_status',
                            'pm_bom_match_status',
                            'bulk_status_status',
                            'scan_bpom_status',
                            '__machine_check__',
                            '__validation_report__',
                            'identity_line_board_status',
                            'sample_30pcs_vacuum_status',
                            'standard_weight_masterbox_status',
                            'sample_30pcs_appearance_status',
                            'functional_test_status',
                        ];
                        $no = 1;
                    @endphp
                    @foreach ($orderedFields as $field)
                        @if ($field === '__machine_check__')
                            <tr>
                                <td class="center">{{ $no++ }}</td>
                                <td>Check Detection Machine</td>
                                <td class="nested-cell">
                                    <table class="nested-table">
                                        <tbody>
                                            @foreach ($machineFields as $mField => $mLabel)
                                                <tr>
                                                    <td>{{ str_replace('Machine ', '', $mLabel) }}</td>
                                                    <td>@include('pdf._status-pill', ['value' => $startupCheck[$mField] ?? null])</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @elseif ($field === '__validation_report__')
                            <tr>
                                <td class="center">{{ $no++ }}</td>
                                <td>Validation Report (NPD Product)</td>
                                <td>@include('pdf._status-pill', ['value' => $startupCheck->validation_report_status ?? null])</td>
                            </tr>
                        @else
                            <tr>
                                <td class="center">{{ $no++ }}</td>
                                <td>{{ $labelOverrides[$field] ?? ($checklistByField[$field] ?? $field) }}</td>
                                <td>@include('pdf._status-pill', ['value' => $startupCheck[$field] ?? null])</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>

            <div class="two-col">
                <table style="margin-bottom: 4px;">
                    <tbody>
                        <tr><td><strong>Setting Suhu</strong></td><td>{{ $startupCheck->heating ?? '—' }}</td></tr>
                        <tr><td><strong>Density</strong></td><td>{{ $startupCheck?->density_not_applicable ? 'N/A' : ($startupCheck?->density ?? '—') }}</td></tr>
                        {{-- "Hasil Cek Warna (STD vs Running)" is a field on the real FR.QAC.182.03
                             (Packing) paper form — filled by hand (e.g. a drawn color swatch), with
                             no equivalent column on startup_checks, so it can't be rendered with a
                             real value, same gap-documentation convention used elsewhere in this
                             report (e.g. "Format Penulisan Coding..." below). FR.QAC.192.02 (Filling)
                             has no such row, so this is Packing-only, direct user request 2026-10-22. --}}
                        @if ($side === 'packing')
                            <tr>
                                <td><strong>Hasil Cek Warna (STD vs Running)</strong></td>
                                <td class="muted">— (tidak ditangkap sebagai data terstruktur, lihat foto Warna di atas)</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                <table style="margin-bottom: 4px;">
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
                 Review By (LL Produksi) / Verification By (IPC).

                 Packing-only exception (direct user request 2026-10-22): the real FR.QAC.182.03
                 (Packing) paper form has no "Verification By" box here at all — only two
                 signatures ("Prepared By (Line Leader Production)" / "Review By (QC IPC)"), placed
                 at the very bottom of its own page (after Pemeriksaan Berat Produk), not beside the
                 checklist. So on the Packing side this mid-page block is skipped entirely and the
                 2-signature version is rendered later, right after the Appereance/Uji Kebocoran/Uji
                 Fungsional summary — see approval-filling-packing.blade.php. The Filling side (and
                 the standalone Startup report, which has no $side at all) keep this 3-signature
                 block unchanged. --}}
            @if ($side !== 'packing')
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
            @endif
        </div>
    </div>
@else
    <p class="muted">Startup Check belum diisi.</p>
@endif
