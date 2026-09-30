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
            font-family: 'IRANSans', serif;
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
        .text-rose-600 { color: #D02749; }

        /* فاصله‌ها */
        .p-10 { padding: 2.5rem; }
        .px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .-mb-2 { margin-bottom: -0.5rem; }
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
        .text-3xl {font-size: 1.875rem}
        .font-bold {font-weight: 700}
    </style>
</head>
<body class="bg-white">

    <div class="flex items-center justify-center gap-2">
        <img src="data:image/png;base64,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" width="42px" alt="Zarenta Logo" />
        <div>
            <div class="text-rose-600 text-3xl font-bold -mb-2">زرنتــــا</div>
            <div class="text-rose-600 text-xs">اعتبار هر معامله</div>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4">
        <div class="text-xs">گزارش تراکنش‌ها از {{ jalali($startDate) }} تا {{ jalali($endDate) }} </div>
        <div class="text-xs">تمامی مبالغ به تومان می‌باشد</div>
    </div>

    <table class="w-full min-w-full border border-gray-200 border-collapse border-spacing-0 text-sm text-right text-gray-500">

        <thead class="text-xs text-gray-700">
        <tr>
            <th scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                ردیف
            </th>
            <th scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                تاریخ
            </th>
            <th scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                شرح
            </th>
            <th scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                مقدار
            </th>
            <th scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50 text-center">
                موجودی قبل
            </th>
            <th scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50 text-center">
                موجودی بعد
            </th>
            <th scope="col" class="px-2 py-2 whitespace-nowrap border border-gray-200 bg-gray-50">
                ارز
            </th>
        </tr>
        </thead>

        <tbody>
        @foreach($transactions as $i => $item)
            <tr class="odd:bg-white even:bg-gray-50 bg-white border-b border-gray-200">
                <th class="px-2 py-2 whitespace-nowrap border border-gray-200">{{(int)$i + 1}}</th>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200" dir="ltr">{{jalali($item->created_at)}}</td>
                <td class="px-2 py-2 text-start border border-gray-200 min-w-20">{{ $item->description }}</td>
                <td class="px-2 py-2 border border-gray-200">
                    <span dir="ltr">{{ number_format($item->amount, 3, '.', '') }}</span>
                    @if ($item->type == 'metal_commitment_in' || $item->type == 'metal_commitment_out' || $item->type == 'settlement_metal_out' || $item->type == 'settlement_metal_in')
                        @if ($item->metal_item_unit == 'gram')
                            گرم
                        @elseif ($item->metal_item_unit == 'count')
                            عدد
                        @endif
                    @elseif ($item->type == 'fiat_debt_in' || $item->type == 'fiat_credit_in' || $item->type == 'settlement_fiat_transfer')
                        تومان
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    <span dir="ltr">{{ number_format($item->balance_before, 3, '.', '') }}</span>
                    @if ($item->type == 'metal_commitment_in' || $item->type == 'metal_commitment_out' || $item->type == 'settlement_metal_out' || $item->type == 'settlement_metal_in')
                        @if ($item->metal_item_unit == 'gram')
                        گرم
                        @elseif ($item->metal_item_unit == 'count')
                        عدد
                        @endif
                    @elseif ($item->type == 'fiat_debt_in' || $item->type == 'fiat_credit_in' || $item->type == 'settlement_fiat_transfer')
                    تومان
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    <span dir="ltr">{{ number_format($item->balance_after, 3, '.', '') }}</span>
                    @if ($item->type == 'metal_commitment_in' || $item->type == 'metal_commitment_out' || $item->type == 'settlement_metal_out' || $item->type == 'settlement_metal_in')
                        @if ($item->metal_item_unit == 'gram')
                        گرم
                        @elseif ($item->metal_item_unit == 'count')
                        عدد
                        @endif
                    @elseif ($item->type == 'fiat_debt_in' || $item->type == 'fiat_credit_in' || $item->type == 'settlement_fiat_transfer')
                        تومان
                    @endif
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    @if ($item->metal_item_title)
                        {{ $item->metal_item_title }}
                    @else
                        کیف پول
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>

    </table>

{{--    <div>--}}
{{--        {!! buildBalanceSummary($voucherBalances) !!}--}}
{{--    </div>--}}
</body>
</html>
