@use('App\Enums\CarAction')
@use('App\Enums\ComplaintType')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $car->reference }} — Corrective Action Report Form</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eceff3; color: #111; font: 11px/1.45 Arial, Helvetica, sans-serif; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 12mm; box-shadow: 0 4px 18px rgba(0,0,0,.12); }
        .toolbar { width: 210mm; margin: 16px auto 0; display: flex; justify-content: space-between; align-items: center; font-family: system-ui, sans-serif; font-size: 12px; }
        .toolbar button { padding: 6px 14px; border-radius: 6px; border: 1px solid #0f766e; background: #e7f7f5; color: #0f766e; font-weight: 600; cursor: pointer; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 8px; letter-spacing: .02em; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #222; padding: 4px 6px; vertical-align: top; text-align: left; }
        .part { background: #111; color: #fff; font-weight: 700; width: 28%; }
        .label { font-weight: 700; width: 28%; }
        .section { margin-top: 10px; }
        .box { border: 1px solid #222; padding: 6px 8px; min-height: 40px; white-space: pre-line; }
        .muted { color: #555; }
        .check { display: inline-block; width: 10px; height: 10px; border: 1px solid #111; margin-right: 4px; vertical-align: -1px; }
        .check.on { background: #111; }
        .sign { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 6px; }
        .sign div { border-top: 1px solid #222; padding-top: 2px; margin-top: 18px; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        .grid2 > div { border: 1px solid #222; padding: 6px 8px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('cars.show', $car) }}">&larr; Back to {{ $car->reference }}</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="sheet">
        <h1>CORRECTIVE ACTION REPORT FORM</h1>

        <table>
            <tr>
                <td class="part">Part I.</td>
                <td></td>
                <td rowspan="5" style="width:30%;">
                    <strong>Type of Complaint/Problem</strong><br>
                    @foreach (ComplaintType::cases() as $type)
                        <span class="check {{ $car->complaint_type === $type ? 'on' : '' }}"></span>{{ $type->label() }}<br>
                    @endforeach
                </td>
            </tr>
            <tr><td class="label">CAR Reference No.:</td><td>{{ $car->reference }}</td></tr>
            <tr><td class="label">Date Issued:</td><td>{{ strtoupper($car->issued_on->format('F d, Y')) }}</td></tr>
            <tr><td class="label">Customer/Farm Name</td><td>{{ strtoupper($car->complainant) }}</td></tr>
            <tr><td class="label">Assigned Agent</td><td>{{ $car->requestor->name }}</td></tr>
            <tr><td class="label">Issued To</td><td colspan="2">{{ $car->farm->name }} · {{ $car->issuedToUnit->name }} ({{ $car->issuedToUnit->businessLine->name }}) — {{ $car->category->name }} / {{ $car->subcategory->name }}</td></tr>
            <tr><td class="label">Deadlines</td><td colspan="2">Response: {{ $car->response_due_on->format('M j, Y') }} · Implementation: {{ $car->implementation_due_on->format('M j, Y') }}@if ($car->revised_due_on) · Revised: {{ $car->revised_due_on->format('M j, Y') }}@endif</td></tr>
        </table>

        <div class="section">
            <table><tr><td class="part">Part II.</td><td><strong>Problem Information</strong></td></tr></table>
            <div class="box"><strong>Details of Problem:</strong>
{{ $car->problem_details }}</div>
            @if ($car->attachments->isNotEmpty())
                <div class="muted" style="margin-top:4px;">Attachments: {{ $car->attachments->pluck('original_name')->implode(', ') }}</div>
            @endif
            <div class="sign">
                <div>Prepared by: {{ $car->requestor->name }}</div>
                <div>Noted by: {{ $release?->actor?->name ?? '' }}</div>
                <div>Acknowledged by:</div>
            </div>
        </div>

        <div class="section">
            <table><tr><td class="part">Part III.</td><td><strong>Correction</strong></td></tr></table>
            <div class="box"><strong>Interim Containment/Immediate Action/s:</strong>
{{ $response?->containment_actions ?? '' }}@if ($response?->containment_starts_on)

<span class="muted">{{ $response->containment_starts_on->format('M j, Y') }} – {{ $response->containment_ends_on?->format('M j, Y') }} · Responsible: {{ $response->containment_responsible }}</span>@endif</div>
            <table style="margin-top:6px;"><tr><td class="part" style="background:#111;">Root Cause</td><td></td></tr></table>
            <div class="box"><strong>Identify and Define the Root Cause</strong>
{{ $response?->root_cause ?? '' }}@if ($response?->root_cause_responsible)

<span class="muted">Responsible: {{ $response->root_cause_responsible }}</span>@endif</div>

            <div style="margin-top:6px;"><strong>Corrective Action/s &amp; Implementation Date:</strong></div>
            <table>
                <tr><th>Corrective Action</th><th style="width:22%;">Responsible</th><th style="width:20%;">Target Date</th></tr>
                @forelse ($response?->correctiveActions ?? [] as $action)
                    <tr>
                        <td style="white-space:pre-line;">{{ $action->description }}</td>
                        <td>{{ $action->responsible }}</td>
                        <td>{{ $action->starts_on?->format('M j') }} – {{ $action->ends_on?->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td>&nbsp;</td><td></td><td></td></tr>
                @endforelse
            </table>
            <div class="sign" style="grid-template-columns:1fr 1fr;">
                <div>Prepared by: {{ $response?->preparer?->name ?? '' }}</div>
                <div>Noted by: {{ $approval?->actor?->name ?? '' }}</div>
            </div>
        </div>

        <div class="section">
            <table><tr><td class="part">Part V.</td><td><strong>Confirmation of Implementation of Corrective Action (Follow-up)</strong></td></tr></table>
            @php $latestEvidence = $verifications->last(); @endphp
            <div class="box" style="min-height:0;">The proposed corrective action/s of this CAR are confirmed to be in place and implemented.
<span class="muted">Evidence uploaded: {{ $latestEvidence ? $latestEvidence['round']->evidence_uploaded_at->format('M j, Y').' by '.($latestEvidence['round']->evidence_responsible ?? '') : '—' }}</span></div>
            <div class="sign" style="grid-template-columns:1fr 1fr;">
                <div>Confirmed by (Department Head/Auditor): {{ ($latestEvidence['check'] ?? null)?->actor?->name ?? '' }}</div>
                <div>Date: {{ ($latestEvidence['check'] ?? null)?->created_at?->format('M j, Y') ?? '' }}</div>
            </div>
        </div>

        <div class="section">
            <table><tr><td class="part">Part VI.</td><td><strong>Verification as to Effectiveness of Corrective Action</strong></td></tr></table>
            <div class="grid2">
                @foreach ([0, 1] as $index)
                    @php $row = $verifications->get($index); @endphp
                    <div>
                        <strong>{{ $index === 0 ? '1st' : '2nd' }} Verification</strong>
                        <div style="min-height:28px; white-space:pre-line;">{{ ($row['check'] ?? null)?->note ?? ($row && ($row['check'] ?? null)?->action === CarAction::MarkEffective ? 'Corrective action validated as effective.' : '') }}</div>
                        <div>
                            Status:
                            <span class="check {{ $row && ($row['acceptance'] ?? null)?->action === CarAction::Accept ? 'on' : '' }}"></span>Close
                            <span class="check {{ $row && (($row['acceptance'] ?? null)?->action === CarAction::NotAccept || ($row['check'] ?? null)?->action === CarAction::MarkNotEffective) ? 'on' : '' }}"></span>Open
                            &nbsp; Recommit Date: {{ ($row['recommitOn'] ?? null)?->format('M j, Y') ?? '________' }}
                        </div>
                        <div>Verified by: {{ ($row['check'] ?? null)?->actor?->name ?? '________________' }} &nbsp; Date: {{ ($row['check'] ?? null)?->created_at?->format('M j, Y') ?? '________' }}</div>
                        <div>Noted by: {{ ($row['acceptance'] ?? null)?->actor?->name ?? '________________' }} &nbsp; Date: {{ ($row['acceptance'] ?? null)?->created_at?->format('M j, Y') ?? '________' }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="muted" style="margin-top:10px;">Printed {{ now()->format('M j, Y g:i A') }} from the CAR Management System · Status: {{ $car->status->label() }}</p>
    </div>
</body>
</html>
