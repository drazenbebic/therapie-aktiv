@php
    use App\Billing\BillingCode;
    use App\Support\SvNumber;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Therapie Aktiv – Abrechnung {{ $quarter->label() }}</title>
    <style>
        @page { margin: 18mm 14mm 18mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 2mm 0; }
        .meta { color: #555; margin-bottom: 6mm; }
        table.group { margin-top: 5mm; }
        th.group-title { font-size: 11pt; font-weight: bold; color: #111; border-bottom: 1.5px solid #111; padding: 0 0 1mm 0; }
        th.group-title .count { font-weight: normal; color: #555; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8pt; color: #555; font-weight: normal; border-bottom: 1px solid #999; padding: 1.5mm 1.5mm; }
        td { padding: 1.8mm 1.5mm; border-bottom: 0.5px solid #ccc; vertical-align: middle; }
        tr { page-break-inside: avoid; }
        .num { text-align: right; width: 6mm; color: #777; }
        .center { text-align: center; }
        .code { font-weight: bold; text-align: center; font-size: 10pt; }
        .code-annual { background: #e6e6e6; }
        .note { color: #777; font-size: 7.5pt; }
        .box { display: inline-block; width: 3.5mm; height: 3.5mm; border: 1px solid #333; }
        .summary { margin-top: 8mm; width: 60%; }
        .summary td { border-bottom: 0.5px solid #ccc; }
        .summary .total td { font-weight: bold; border-top: 1.5px solid #111; border-bottom: none; }
        footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5pt; color: #777; }
    </style>
</head>
<body>
    <footer>
        <table>
            <tr>
                <td style="border: none; padding: 0;">Therapie Aktiv – Abrechnung {{ $quarter->label() }}</td>
            </tr>
        </table>
    </footer>

    <h1>Therapie Aktiv – Abrechnung {{ $quarter->label() }}</h1>
    <div class="meta">
        Quartal {{ $quarter->start()->format('d.m.Y') }} – {{ $quarter->end()->format('d.m.Y') }}
        · {{ $totalRegular + $totalAnnual }} Patienten ({{ $totalRegular }}× 97, {{ $totalAnnual }}× 97a)
        · erstellt am {{ $generatedAt->format('d.m.Y H:i') }}
    </div>

    @forelse ($groups as $insurer => $lines)
        <table class="group">
            <thead>
                <tr>
                    <th class="group-title" colspan="8">{{ $insurer }} <span class="count">· {{ $lines->count() }} Patienten</span></th>
                </tr>
                <tr>
                    <th class="num">#</th>
                    <th>Name</th>
                    <th>SV-Nummer</th>
                    <th>Geb.-Datum</th>
                    <th>Eingeschrieben</th>
                    <th class="center">Quartal</th>
                    <th class="center">Position</th>
                    <th class="center">Erledigt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td class="num">{{ $loop->iteration }}</td>
                        <td>
                            {{ $line->patient->full_name }}
                            @if ($line->patient->deregistered_at)
                                <div class="note">
                                    {{ $line->patient->deregistration_reason?->getLabel() ?? 'Abgemeldet' }}
                                    am {{ $line->patient->deregistered_at->format('d.m.Y') }}
                                </div>
                            @endif
                        </td>
                        <td>{{ SvNumber::format($line->patient->sv_number) }}</td>
                        <td>{{ $line->patient->date_of_birth->format('d.m.Y') }}</td>
                        <td>{{ $line->patient->enrolled_at->format('d.m.Y') }}</td>
                        <td class="center">{{ $line->programmeQuarter }}.</td>
                        <td class="code {{ $line->code === BillingCode::Annual ? 'code-annual' : '' }}">{{ $line->code->value }}</td>
                        <td class="center"><span class="box"></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>In diesem Quartal gibt es keine abrechenbaren Patienten.</p>
    @endforelse

    @if ($groups->isNotEmpty())
        <table class="summary">
            <thead>
                <tr>
                    <th>Kasse</th>
                    <th class="center">97</th>
                    <th class="center">97a</th>
                    <th class="center">Gesamt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $insurer => $lines)
                    <tr>
                        <td>{{ $insurer }}</td>
                        <td class="center">{{ $lines->where('code', BillingCode::Regular)->count() }}</td>
                        <td class="center">{{ $lines->where('code', BillingCode::Annual)->count() }}</td>
                        <td class="center">{{ $lines->count() }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Gesamt</td>
                    <td class="center">{{ $totalRegular }}</td>
                    <td class="center">{{ $totalAnnual }}</td>
                    <td class="center">{{ $totalRegular + $totalAnnual }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
