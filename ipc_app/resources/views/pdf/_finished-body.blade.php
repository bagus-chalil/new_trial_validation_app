<div class="fp-compact fg-compact">
    <div class="section-title">Finished Good Inspection Report</div>

    @if ($finishedCheck)
        <div class="attachment-grid">
            {{-- wi_number holds up to 4 photos; one tile per photo. --}}
            @foreach ([['wi_number', 'WI Number'], ['exp_date', 'Exp Date'], ['color', 'Color Test']] as [$field, $label])
                @php
                    $urls = $photoUrls['finished'][$field] ?? null;
                    $urls = is_array($urls) ? array_values(array_filter($urls)) : array_filter([$urls]);
                @endphp
                @forelse ($urls as $i => $url)
                    <figure class="attachment-tile attachment-tile--sm">
                        <img src="{{ $url }}" alt="{{ $label }}">
                        <figcaption><strong>{{ $label }}{{ count($urls) > 1 ? ' '.($i + 1) : '' }}</strong></figcaption>
                    </figure>
                @empty
                    <figure class="attachment-tile attachment-tile--sm">
                        <div class="placeholder">Belum ada foto</div>
                        <figcaption><strong>{{ $label }}</strong></figcaption>
                    </figure>
                @endforelse
            @endforeach
        </div>

        <table>
            <tbody>
                <tr>
                    <td style="width: 20%;"><strong>Quantity WI</strong></td><td style="width: 30%;">{{ $finishedCheck->quantity_wi ?? '—' }}</td>
                    <td style="width: 20%;"><strong>Masterbox</strong></td><td>{{ $finishedCheck->masterbox ?? '—' }}</td>
                </tr>
                <tr>
                    <td><strong>No. Pallet & Qty</strong></td><td>{{ $finishedCheck->no_pallet_qty ?? '—' }}</td>
                    <td><strong>Line Leader</strong></td><td>{{ $finishedCheck->line_leader_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td><strong>TH Progress</strong></td><td>{{ $finishedCheck->save_count ?? 0 }}</td>
                    <td><strong>QC FG Inspector</strong></td><td>{{ $finishedCheck->user->name ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="two-col">
            <table>
                <thead><tr><th colspan="2">General Inspection</th></tr></thead>
                <tbody>
                    <tr><td style="width: 45%;">Qty of Sampling</td><td>{{ $finishedCheck->quantity_sampling_aql ?? '—' }}</td></tr>
                    <tr><td>CD / MD / mD</td><td>{{ $finishedCheck->quantity_sample_aql_cd ?? '—' }} / {{ $finishedCheck->quantity_sample_aql_md ?? '—' }} / {{ $finishedCheck->quantity_sample_aql_mnd ?? '—' }}</td></tr>
                </tbody>
            </table>
            <table>
                <thead><tr><th colspan="2">Special Inspection</th></tr></thead>
                <tbody>
                    <tr><td style="width: 45%;">Qty of Sampling</td><td>{{ $finishedCheck->quantity_special_inspection ?? '—' }}</td></tr>
                    <tr><td>CD / MD / mD</td><td>{{ $finishedCheck->quantity_special_inspection_cd ?? '—' }} / {{ $finishedCheck->quantity_special_inspection_md ?? '—' }} / {{ $finishedCheck->quantity_special_inspection_mnd ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        {{-- Labelled "Pal / qty" per the real paper form, which repeats this whole AC/CD/MD/mD
             matrix once per pallet/lot sampled (up to 7 on the blank form). This app only ever
             captures ONE set of AC/CD/MD/mD per parameter for the whole batch (FinishedCheckSample
             has no pallet/lot dimension) — a real data-model gap, not a template bug, flagged here
             rather than fabricating empty pallet columns. See the note below the table. --}}
        @php
            $samplesByKey = $finishedCheck->samples->keyBy('parameter_key');
            // Cosmetic-only relabel to match the real form's own wording (FinishedCheckSample::sampleGroups()
            // is shared with the Stage 5 input form and is left untouched).
            $paperLabels = ['special_test_bulk' => 'Bulk condition', 'special_test_color' => 'Color application'];
            // Continuous item numbering 1-19 across all groups, then split into two side-by-side
            // columns (Tersier+Secondary | Primary+Special) so the landscape page needs far less height.
            $itemNo = 0;
            $columns = [[], []];
            foreach ($finishedSampleGroups as $gi => $group) {
                $isSpecial = in_array($group['key'], ['functional', 'special'], true);
                $col = ($gi < 2) ? 0 : 1;
                $rows = [];
                foreach ($group['parameters'] as $key => $label) {
                    $itemNo++;
                    $rows[] = ['no' => $itemNo, 'label' => $paperLabels[$key] ?? $label, 'row' => $samplesByKey[$key] ?? null];
                }
                if ($isSpecial) {
                    $last = count($columns[$col]) - 1;
                    if ($last >= 0 && ($columns[$col][$last]['special'] ?? false)) {
                        $columns[$col][$last]['rows'] = array_merge($columns[$col][$last]['rows'], $rows);
                        continue;
                    }
                }
                $columns[$col][] = ['title' => $isSpecial ? 'Special Inspection' : $group['label'].' Packaging', 'special' => $isSpecial, 'rows' => $rows];
            }
        @endphp
        <div class="two-col">
            @foreach ($columns as $blocks)
                <table class="record-table record-table--compact">
                    <colgroup><col style="width: 8%;"><col><col style="width: 13%;"><col style="width: 13%;"><col style="width: 13%;"><col style="width: 13%;"></colgroup>
                    <thead>
                        <tr>
                            <th class="center">No</th><th>Parameter</th>
                            <th class="center">AC</th><th class="center">CD</th><th class="center">MD</th><th class="center" style="text-transform: none;">mD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blocks as $block)
                            <tr class="group-row"><td colspan="6" @if ($block['special']) style="background:#1f2937;color:#fff;" @endif>{{ $block['title'] }}</td></tr>
                            @foreach ($block['rows'] as $r)
                                <tr>
                                    <td class="center">{{ $r['no'] }}</td>
                                    <td>{{ $r['label'] }}</td>
                                    <td class="center">{{ $r['row']->ac ?? 'N/A' }}</td>
                                    <td class="center">{{ $r['row']->cd ?? 'N/A' }}</td>
                                    <td class="center">{{ $r['row']->md ?? 'N/A' }}</td>
                                    <td class="center">{{ $r['row']->mnd ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
        <p class="muted">ZD = Zero Defect &nbsp; C = Critical Defect &nbsp; M = Major Defect &nbsp; m = Minor Defect &nbsp; (AC = Accepted Sample count per parameter)</p>
        <p class="muted" style="margin-top: 0;">Form FR.QAC.193.00 menyediakan hingga 7 kolom Pal/qty (satu set AC/CD/MD/mD per pallet/lot) — aplikasi ini baru menangkap satu set nilai per parameter untuk seluruh batch, belum per pallet/lot.</p>

        {{-- Micro Check block of FR.QAC.193.00, filled by the Microbiologist (own role) once Finished Good has been saved. --}}
        @php
            $micro = $microCheck ?? null;
        @endphp
        <div class="two-col">
        <table>
            <thead>
                <tr><th colspan="3">Micro Check</th></tr>
            </thead>
            <tbody>
                @foreach (\App\Models\MicroCheck::PARAMETERS as $key => $label)
                    <tr>
                        <td style="width: 40%;">{{ $label }}</td>
                        <td class="center" style="width: 20%;">{{ $micro?->{$key.'_result'} ?? '—' }}</td>
                        <td>Std (cfu/gr): {{ $micro?->{$key.'_std'} ?? '—' }}</td>
                    </tr>
                @endforeach
                <tr><td colspan="3" style="font-size: 8px;">Microbiologist: {{ $micro?->user?->name ?? '—' }}{{ $micro ? ' · '.$micro->updated_at?->format('d M Y H:i') : '' }}</td></tr>
            </tbody>
        </table>

        <table>
            <tbody>
                <tr>
                    <td style="width: 15%;"><strong>Disposition</strong></td>
                    <td style="width: 45%;">
                        {{-- Checkbox style, matching the paper form's own 3 checkboxes. --}}
                        @foreach (\App\Models\FinishedCheck::DISPOSITIONS as $option)
                            <div>{{ $option === $finishedCheck->disposition ? '☑' : '☐' }} {{ $option }}</div>
                        @endforeach
                    </td>
                    <td style="width: 12%;"><strong>Color Test</strong></td>
                    <td>Lihat foto Color di atas</td>
                </tr>
                <tr>
                    <td><strong>Remarks</strong></td>
                    <td colspan="3" style="white-space: pre-line;">{{ $finishedCheck->remarks ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        </div>

        @if ($finishedCheck->revisions->count() > 0)
            <div class="section-title" style="margin-top: 4px;">Riwayat Simpan — Finished Check (TH Progress)</div>
            <table>
                <thead>
                    <tr><th style="width: 6%;">#</th><th style="width: 20%;">Waktu</th><th style="width: 16%;">User</th><th style="width: 10%;">Status</th><th>Ringkasan</th></tr>
                </thead>
                <tbody>
                    @foreach ($finishedCheck->revisions->sortByDesc('revision_no') as $rev)
                        @php $filled = $rev->samples->filter(fn ($s) => $s->ac !== null || $s->cd !== null || $s->md !== null || $s->mnd !== null)->count(); @endphp
                        <tr>
                            <td class="center">{{ $rev->revision_no }}</td>
                            <td>{{ optional($rev->created_at)->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            <td>{{ $rev->user->name ?? '—' }}</td>
                            <td>{{ $rev->finalize ? 'Selesai' : 'Draft' }}</td>
                            <td>
                                {{ $rev->disposition ? 'Disposition: '.$rev->disposition.'. ' : '' }}
                                Sample: {{ $filled }}/{{ count(\App\Models\FinishedCheckSample::PARAMETER_KEYS) }}.
                                {{ $rev->remarks ? ' Remarks: '.$rev->remarks : '' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="sign-grid">
            <div>
                <span>QC FG Inspector</span>
                <strong>{{ $finishedCheck->user->name ?? '—' }}</strong>
                <small class="sign-date">{{ optional($finishedCheck->completed_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
            </div>
            <div>
                {{-- Paper form's second signature box is labelled "QC Staff" — mapped to the same
                     approver data as the "Review By" box elsewhere in this report (whoever
                     reviewed/approved this check), since no second distinct staff user is
                     captured separately on finished_checks. --}}
                <span>QC Staff</span>
                @if ($finishedApproval)
                    <strong>{{ $finishedApproval->approver->name ?? '—' }}</strong>
                    <small class="sign-date">{{ optional($finishedApproval->approved_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
                @endif
            </div>
            <div class="qr-box">
                <span>Verifikasi</span>
                @if ($finishedApproval)
                    <div class="qr-code">{!! $verificationQr !!}</div>
                    <small>{{ $finishedApproval->decision }}</small>
                @else
                    <small class="muted">Belum disetujui</small>
                @endif
            </div>
        </div>
    @else
        <p class="muted">Finished Check belum diisi.</p>
    @endif
    @include('pdf._approval-remarks', ['approval' => $finishedApproval])
</div>
