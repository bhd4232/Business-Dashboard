@php
    use App\Support\MoneyFormatter;
    $money = fn ($v) => MoneyFormatter::currency((float) $v);
@endphp
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Investor Register — {{ $project->project_code }}</title>
    @include('investments.reports.partials.report-styles')
    <style>
        table.register th, table.register td { font-size: 10px; padding: 5px 6px; vertical-align: top; }
        table.register td .muted { color: #6b7280; }
    </style>
</head>
<body>
    <div class="print-actions">
        <button class="print-button" id="report-print" type="button">Print</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div class="brand">
                {{ $company['name'] }}
                <div class="sub">বিনিয়োগকারীর বিবরণ &mdash; {{ $project->deal_reference ?: $project->name }}</div>
            </div>
            @if (! empty($company['logo_url']))
                <img src="{{ $company['logo_url'] }}" alt="{{ $company['name'] }}">
            @endif
        </div>

        <h1 class="title">{{ $project->project_code }}</h1>
        @php
            $df = $company['date_format'] ?? 'd M Y';
            $winOpen = $project->getAttribute('investment_opens_at') ?? $project->start_date;
            $winClose = $project->getAttribute('investment_closes_at') ?? $project->end_date;
        @endphp
        <div class="deal">
            Investment window:
            {{ $winOpen?->format($df) ?? '—' }} &ndash; {{ $winClose?->format($df) ?? '—' }}
        </div>

        <table class="register">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Investor</th>
                    <th>Father / Spouse</th>
                    <th>Phone</th>
                    <th>NID / Passport</th>
                    <th>Nominee</th>
                    <th class="num">Investment (৳)</th>
                    <th>Stamp Nos.</th>
                    <th>Cheque No.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            {{ $row['investor']->name }}
                            @if ($row['investor']->display_name)<div class="muted">({{ $row['investor']->display_name }})</div>@endif
                            @if ($row['investor']->address)<div class="muted">{{ $row['investor']->address }}</div>@endif
                        </td>
                        <td>{{ $row['investor']->guardian_name ?: '—' }}</td>
                        <td>{{ $row['investor']->phone }}</td>
                        <td>{{ $row['investor']->nid_number ?: '—' }}</td>
                        <td>
                            {{ $row['investor']->nominee_name ?: '—' }}
                            @if ($row['investor']->nominee_nid_or_passport)<div class="muted">{{ $row['investor']->nominee_nid_or_passport }}</div>@endif
                            @if ($row['investor']->nominee_phone)<div class="muted">{{ $row['investor']->nominee_phone }}</div>@endif
                        </td>
                        <td class="num">{{ $money($row['amount']) }}</td>
                        <td>{{ collect($row['stamps'])->implode(', ') ?: '—' }}</td>
                        <td>{{ $row['cheque_number'] ?: '—' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="6">মোট ইনভেস্টমেন্ট এমাউন্ট ({{ $rows->count() }} investor)</td>
                    <td class="num">{{ $money($totalInvested) }}</td>
                    <td colspan="2"></td>
                </tr>
                @if ((float) $project->company_contribution_amount > 0)
                    <tr class="total">
                        <td colspan="6">Company contribution (gap fill)</td>
                        <td class="num">{{ $money($project->company_contribution_amount) }}</td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="total">
                        <td colspan="6">Total capital base</td>
                        <td class="num">{{ $money($project->totalCapitalBase()) }}</td>
                        <td colspan="2"></td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="foot">
            {{ $company['name'] }} &mdash; generated {{ now()->format('d M Y, h:i A') }}
        </div>
    </div>

    @include('investments.reports.partials.print-script', ['autoPrint' => $autoPrint])
</body>
</html>
