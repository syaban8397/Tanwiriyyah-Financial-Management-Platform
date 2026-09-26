<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1b1914; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p { margin: 0 0 12px; color: #5c564c; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #d9d2c5; padding: 5px 6px; text-align: left; }
        th { font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        tfoot td { font-weight: bold; border-top: 1px solid #1b1914; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <p>Yayasan Tanwiriyyah{{ $report['period'] ? ' · '.$report['period'] : '' }}</p>
    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ is_int($cell) ? number_format($cell, 0, ',', '.') : $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ max(count($report['columns']) - 1, 1) }}">{{ $report['total_label'] }}</td>
                <td>{{ number_format((int) $report['total'], 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
