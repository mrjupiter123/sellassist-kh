<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->name }} · Count worksheet</title>
    <style>
        body { font-family: Arial, "Noto Sans Khmer", sans-serif; color: #111; max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        header { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 2px solid #111; padding-bottom: 1rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .4rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { border: 1px solid #bbb; padding: .65rem; text-align: left; vertical-align: top; }
        th { background: #f1f1f1; }
        td.blank { min-width: 110px; height: 2.5rem; }
        tr { break-inside: avoid; }
        .no-print { margin-bottom: 1rem; }
        .hint { font-size: .9rem; color: #555; margin-top: 1rem; }
        @media print { .no-print { display: none; } body { max-width: none; margin: 0; } }
    </style>
</head>
<body>
    <div class="no-print"><a href="{{ route('inventory.stock-count-campaigns.show', $campaign) }}">← Campaign</a> <button type="button" onclick="window.print()">Print worksheet</button></div>
    <header><div><h1>{{ $campaign->name }}</h1><div>Physical count worksheet · Open assignments only</div></div><div>Due: {{ $campaign->due_on?->format('d M Y') ?: 'Not set' }}<br>Printed: {{ now('Asia/Phnom_Penh')->format('d M Y H:i') }}</div></header>
    @if($tasks->isEmpty())
        <p>No open assignments remain in this campaign.</p>
    @else
        <table>
            <thead><tr><th>#</th><th>Product / variant</th><th>SKU</th><th>Assigned to</th><th>Physical count</th><th>Notes</th></tr></thead>
            <tbody>@foreach($tasks as $task)<tr><td>{{ $loop->iteration }}</td><td>{{ $task->product->name }}@if($task->variant)<br>{{ $task->variant->display_name }}@endif</td><td>{{ $task->variant?->sku ?: ($task->product->sku ?: '—') }}</td><td>{{ $task->assignee->name }}</td><td class="blank"></td><td class="blank"></td></tr>@endforeach</tbody>
        </table>
    @endif
    <p class="hint">This sheet is for writing down observations only. Enter each count in SellAssist KH; the application checks current stock and records any adjustment in its audit trail. Recorded stock quantities are intentionally not printed.</p>
</body>
</html>
