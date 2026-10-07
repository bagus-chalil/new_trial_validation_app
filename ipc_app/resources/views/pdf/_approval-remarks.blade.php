{{-- The QC Coordinator's note from the approval decision (required on Rejected, optional otherwise). --}}
@if ($approval && filled($approval->remarks))
    <table style="margin-top: 6px;">
        <tbody>
            <tr>
                <td style="width: 18%;"><strong>Catatan Approval</strong></td>
                <td style="white-space: pre-line;">{{ $approval->remarks }}</td>
            </tr>
        </tbody>
    </table>
@endif
