<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="utf-8">
    <title>گزارش تراکنش‌ها</title>
    <style>
        @font-face {
            font-family: 'IRANSans';
            src: url('data:font/woff2;charset=utf-8;base64,{{ base64_encode(file_get_contents(resource_path("fonts/IRANSans.woff2"))) }}') format('woff2');
            font-weight: normal;
            font-style: normal;
        }
        body {
            font-family: 'IRANSans';
            direction: rtl;
            unicode-bidi: isolate;
        }
        /* رنگ‌ها (Tailwind Color Palette) */
        .bg-white { background-color: rgb(255, 255, 255); }
        .bg-gray-50 { background-color: rgb(249, 250, 251); }

        .text-base { font-size: 1rem; }               /* 16px */
        .text-sm { font-size: 0.875rem; }              /* 14px */
        .text-xs { font-size: 0.75rem; }               /* 12px */

        .text-gray-500 { color: rgb(107, 114, 128); }
        .text-gray-700 { color: rgb(55, 65, 81); }
        .text-blue-500 { color: oklch(62.3% 0.214 259.815); }
        .text-red-500 { color: oklch(63.7% 0.237 25.331); }

        /* فاصله‌ها */
        .p-10 { padding: 2.5rem; }
        .px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .w-6 { width: 1.5rem }
        .h-12 { height: 3rem }
        .h-10 { height: 2.5rem }

        /* Flexbox */
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .justify-center { justify-content: center; }
        .gap-2{ gap: 0.5rem }

        /* جدول‌ها */
        .w-full { width: 100%; }
        .min-w-full { min-width: 100%; }
        .border { border-width: 1px; border-style: solid; }
        .border-gray-200 { border-color: rgb(229, 231, 235); }
        .border-b { border-bottom-width: 1px; }

        .border-separate { border-collapse: separate; }
        .border-collapse { border-collapse: collapse; }
        .border-spacing-0 { border-spacing: 0; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-start { text-align: start; }

        .whitespace-nowrap { white-space: nowrap; }
        .min-w-20 { min-width: 5rem; }

        /* حالت odd/even در جدول */
        tr:nth-child(odd) { background-color: rgb(255, 255, 255); }
        tr:nth-child(even) { background-color: rgb(249, 250, 251); }
    </style>
</head>
<body class="bg-white">

    <div class="flex items-center justify-center gap-2">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAE8AAAB7CAYAAAA13909AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAydpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDkuMS1jMDAxIDc5LjE0NjI4OTk3NzcsIDIwMjMvMDYvMjUtMjM6NTc6MTQgICAgICAgICI+IDxyZGY6UkRGIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyI+IDxyZGY6RGVzY3JpcHRpb24gcmRmOmFib3V0PSIiIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bXA6Q3JlYXRvclRvb2w9IkFkb2JlIFBob3Rvc2hvcCAyNC43IChXaW5kb3dzKSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDpCQUE4NzU3OUMwQ0MxMUYwQkUyMjlEMkEwM0ExNkM5MiIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDpCQUE4NzU3QUMwQ0MxMUYwQkUyMjlEMkEwM0ExNkM5MiI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOkJBQTg3NTc3QzBDQzExRjBCRTIyOUQyQTAzQTE2QzkyIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOkJBQTg3NTc4QzBDQzExRjBCRTIyOUQyQTAzQTE2QzkyIi8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+qhQKUgAAC+1JREFUeNrs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" class="w-6 h-10" alt="Zhik Gold Logo" />
        <div>طلای ژیک</div>
    </div>

    <div class="flex items-center justify-between mb-4">
        <div class="text-xs">گزارش تراکنش‌ها از {{ jalali($startDate) }} تا {{ jalali($endDate) }} </div>
        <div class="text-xs">تمامی مبالغ به تومان می‌باشد</div>
    </div>

    <table class="w-full min-w-full border border-gray-200 border-collapse border-spacing-0 text-sm text-right text-gray-500">

        <thead class="text-xs text-gray-700">
        <tr>
            <th rowspan="2" scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                ردیف
            </th>
            <th rowspan="2" scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                تاریخ
            </th>
            <th rowspan="2" scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                شرح
            </th>
            <th rowspan="2" scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                وزن
            </th>
            <th colspan="3" scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50 text-center">
                وزن ۷۵۰
            </th>
            <th colspan="3" scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50 text-center">
                مبلغ
            </th>
            <th rowspan="2" scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                ارز
            </th>
        </tr>
        <tr>
            <th class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">دریافتی</th>
            <th class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">پرداختی</th>
            <th class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">مانده</th>

            <th class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">دریافتی</th>
            <th class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">پرداختی</th>
            <th class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">مانده</th>
        </tr>
        </thead>

        <tbody>
        @foreach($transactions['Items'] as $i => $item)
            <tr class="odd:bg-white even:bg-gray-50 bg-white border-b border-gray-200">
                <th class="px-2 py-2 whitespace-nowrap border border-gray-200">{{(int)$i + 1}}</th>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200" dir="ltr">{{jalali($item['Date'])}}</td>
                <td class="px-2 py-2 text-start border border-gray-200 min-w-20">{{ buildTransactionDescription($item) }}</td>
                <td class="px-2 py-2 border border-gray-200">
                    @if(isset($item['Weight']))
                        {{ number_format(abs($item['Weight']), 3, '.', '') }}
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    @if(isset($item['Weight750']) && $item['Weight750'] > 0)
                        {{ number_format($item['Weight750'], 3, '.', '') }}
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    @if(isset($item['Weight750']) && $item['Weight750'] < 0)
                        {{ number_format(abs($item['Weight750']), 3, '.', '') }}
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200 {{ isset($item['CumulativeWeight750']) && $item['CumulativeWeight750'] > 0 ? 'text-blue-500' : (isset($item['CumulativeWeight750']) && $item['CumulativeWeight750'] < 0 ? 'text-red-500' : '') }}">
                    @if($item['CumulativeWeight750'] > 0)
                        بس
                    @else
                        بد
                    @endif
                    {{ number_format(abs($item['CumulativeWeight750']), 3, '.', '') }}
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    @if (isset($item['SumMoney']) && $item['SumMoney'] > 0)
                        @if (! in_array($item['CurrencyId'], [15, 16, 17, 18]))
                            {{ number_format(round($item['SumMoney'] / 10)) }}
                        @else
                            {{ number_format($item['SumMoney']) }}
                        @endif
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    @if (isset($item['SumMoney']) && $item['SumMoney'] < 0)
                        @if (!in_array($item['CurrencyId'], [15, 16, 17, 18]))
                            {{ number_format(round(abs($item['SumMoney']) / 10)) }}
                        @else
                            {{ number_format(abs($item['SumMoney'])) }}
                        @endif
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200 {{ $item['CumulativeSumMoney'] > 0 ? 'text-blue-500' : ($item['CumulativeSumMoney'] < 0 ? 'text-red-500' : '') }}">
                    {{ isset($item['CumulativeSumMoney']) && $item['CumulativeSumMoney'] > 0 ? 'بس ' : 'بد ' }}
                    @if (!in_array($item['CurrencyId'], [15, 16, 17, 18]))
                        {{ number_format(round(abs($item['CumulativeSumMoney']) / 10)) }}
                    @else
                        {{ number_format(abs($item['CumulativeSumMoney'])) }}
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    @switch($item['CurrencyId'])
                        @case(11)
                            تومان
                            @break
                        @case(15)
                            تمام سکه قدیم
                            @break
                        @case(16)
                            تمام سکه ۸۶
                            @break
                        @case(17)
                            نیم سکه ۸۶
                            @break
                        @case(18)
                            ربع سکه ۸۶
                            @break
                        @default
                            {{ $item['CurrencySymbol'] }}
                    @endswitch
                </td>
            </tr>
        @endforeach
        </tbody>

    </table>

    <div>
        {!! buildBalanceSummary($voucherBalances) !!}
    </div>
</body>
</html>
