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
        .text-primary { color: oklch(85.2% 0.199 91.936); }

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
        <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzNTkuMzIgNDI4LjAyIj4NCgk8cGF0aCBmaWxsPSIjZmZkMTAwIiBkPSJNMTExLjE4LDMuMzVzLTExLjU1LDI1LjUxLDIxLjY2LDQ4LjEzLDEzMy4zMiw1NC44NywxODAuOTcsMTAxLjA3LDU5LjIsOTUuNzgsMjguNCwxNTcuMzhjMCwwLDIuNDEtMjcuNDMtMjUuOTktNjAuMTZzLTU1LjM1LTU3Ljc2LTE0NS44My0xMDguMjlTODIuMyw0OS4wNywxMTEuMTgsMy4zNVoiPjwvcGF0aD4NCgk8cGF0aCBmaWxsPSIjZmZkMTAwIiBkPSJNMTcyLjMsMTU0LjQ3bDI1Ljk5LDE0Ljkycy0yMC4yMSw1MC4wNS0xMDAuNTksMTk5Ljc0TDAsMzg1LjAyczQzLjMyLTY5LjMxLDczLjE2LTEwNy44MSw1NC4zOS02NS45NCw5OS4xNS0xMjIuNzNaIj48L3BhdGg+DQoJPHBhdGggZmlsbD0iI2ZmZDEwMCIgZD0iTTI5Ny40NCwyNDQuOTZzLTEuNDQsMjkuMzYtNDMuMzIsNzEuMjMtMTM1LjczLDYxLjEyLTIyOS41OCw3NC42YzAsMCwzNS4xMywzLjM3LDczLjE2LDE2Ljg1czcwLjI3LDI2LjQ3LDExNS4wMywxNy4zMyw4MS40OC0zNS4wMiwxMDIuODItNjQuMzIsMzMuMTgtNjIuNS0xOC4xMS0xMTUuNjhaIj48L3BhdGg+DQoJPHBvbHlnb24gZmlsbD0iI2ZmZDEwMCIgIHBvaW50cz0iMjUwLjcxIDAgMTgxLjYzIDMwLjQ3IDE3Ni45NyA1Ny4zNCAyNTAuMjIgOTIuNDQgMjczLjY5IDE0LjY5IDI1MC43MSAwIj48L3BvbHlnb24+DQo8L3N2Zz4=" width="42px" alt="Zarenta Logo" />
        <div>
            <div class="text-primary text-3xl font-bold -mb-2">زرنتــــا</div>
            <div class="text-primary text-xs">اعتبار هر معامله</div>
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
                    <span dir="ltr">{{ format_pdf_number(wallet_transaction_signed_amount($item)) }}</span>
                    {{ wallet_transaction_unit_label($item) }}
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    <span dir="ltr">{{ format_pdf_number($item->balance_before) }}</span>
                    {{ wallet_transaction_unit_label($item) }}
                </td>
                <td class="px-2 py-2 whitespace-nowrap border border-gray-200">
                    <span dir="ltr">{{ format_pdf_number($item->balance_after) }}</span>
                    {{ wallet_transaction_unit_label($item) }}
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
