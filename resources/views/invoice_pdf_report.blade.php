<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .invoice-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: auto;
        }
        .invoice-header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .invoice-header div {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-table th, .invoice-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .invoice-table th {
            background-color: #f8f8f8;
        }
        .total-section {
            margin-top: 20px;
            text-align: right;
            font-size: 16px;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div>
                <strong>Ragione Sociale</strong> Test<br>
                <strong>C.F:</strong> 2569845
            </div>
            <div>
                <strong>cliente Ragione Sociale:</strong>{{ $client->ragione_sociale }}<br>
                <strong>C.F:</strong> {{ $client->iva??$client->cf??"" }}
            </div>
        </div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $total = 0;
                @endphp
                @foreach ($tasks as $task)
                    <tr>
                        <td>{{ $task->serviceCategory->name }}</td>
                        <td>1</td>
                        <td>{{ $task->price_after_discount }}</td>
                        <td>{{ 1 * $task->price_after_discount }}</td>
                    </tr>
                    @php
                        $total += 1 * $task->price_after_discount
                    @endphp
                @endforeach
            </tbody>
        </table>
        <div class="total-section">
            Total: $ {{ $total }}
        </div>
        <div class="footer">
            <p><strong>Expire Date:</strong> {{ $invoice->end_at?\Carbon\Carbon::parse($invoice->end_at)->format('d/m/Y'): 'N/Y' }}</p>
            <p><strong>Payment Method:</strong> {{ $paymentMethod }}</p>
        </div>
    </div>
</body>
</html>
