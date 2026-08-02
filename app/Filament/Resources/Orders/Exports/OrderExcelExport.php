<?php

namespace App\Filament\Resources\Orders\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderExcelExport
{
    public function export(Collection $orders): StreamedResponse
    {
        $orders->load(['user', 'items', 'shipping.shippingMethod', 'address.city', 'address.province']);

        $spreadsheet = new Spreadsheet;
        $ordersData = [];

        foreach ($orders as $order) {
            $ordersData[] = $this->prepareOrderData($order);
        }

        $this->createDataSheet($spreadsheet, $ordersData);
        $this->createLabelSheet($spreadsheet, $ordersData);

        $spreadsheet->setActiveSheetIndexByName('گزارش');

        $filename = 'orders-export-'.now()->format('Y-m-d-H-i').'.xlsx';

        return Response::stream(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment;filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function prepareOrderData($order): array
    {
        $statusLabels = [
            'pending' => 'در انتظار بررسی',
            'confirmed' => 'تایید شده',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        ];
        $status = $statusLabels[$order->status] ?? $order->status;

        $gatewayLabels = [
            'online' => 'پرداخت آنلاین',
            'installment' => 'اقساط',
            'installment_nofee' => 'اقساط بدون کارمزد',
        ];
        $gateway = $gatewayLabels[$order->payment_method] ?? $order->payment_method;

        $dateStr = $order->created_at ? $this->toJalali($order->created_at->format('Y-m-d H:i:s')) : '';

        $buyerFirstName = $order->user?->first_name ?? '';
        $buyerLastName = $order->user?->last_name ?? '';
        $buyerName = trim($buyerFirstName.' '.$buyerLastName);
        $buyerMobile = $order->user?->phone ?? '';

        $nationalCode = $order->user?->national_code ?? $order->address?->receiver_national_code ?? '';

        $trackingCode = $order->shipping?->tracking_number ?? '';

        $itemsText = '';
        foreach ($order->items as $item) {
            $itemsText .= $item->product_name.': '.$item->quantity.' عدد'."\n";
        }
        $itemsText = trim($itemsText);
        $itemCount = $order->items->sum('quantity');
        $itemCountStr = 'تعداد اقلام سفارش '.$itemCount.' عدد';

        $shippingMethodName = $order->shipping?->shipping_method_name ?? '';
        $isPickup = $order->shipping?->shippingMethod?->is_pickup ?? false;

        if ($isPickup) {
            $deliveryType = 'تحویل در شرکت';
            $shippingMethodName = '-';
        } else {
            $deliveryType = 'ارسال به نشانی خریدار';
        }

        $paymentType = 'پرداخت از درگاه بانکی';

        $city = $order->address?->city?->name ?? '';

        $street = $order->address?->address_line ?? '';
        if ($order->address?->plate) {
            $street .= ' پ'.$order->address->plate;
        }
        if ($order->address?->unit) {
            $street .= ' واحد'.$order->address->unit;
        }
        $postcode = $order->address?->postal_code ?? '';

        if ($postcode && $city && $street) {
            $address = 'کدپستی: '.$postcode.'، '.$city.' | '.$street;
        } elseif ($postcode && $city) {
            $address = 'کدپستی: '.$postcode.'، '.$city;
        } else {
            $address = collect([$street, $city, $postcode])->filter()->implode('، ');
        }

        $receiverName = $order->address?->receiver_name ?? $buyerName;
        $receiverMobile = $order->address?->receiver_phone ?? $buyerMobile;

        return [
            $status, $gateway, $dateStr, $order->order_number,
            $buyerName, $buyerMobile, $nationalCode, $trackingCode,
            $itemsText, $itemCountStr, $shippingMethodName, $deliveryType,
            $paymentType, $order->total_amount, $city, $address,
            $receiverName, $receiverMobile,
        ];
    }

    private function createDataSheet(Spreadsheet $spreadsheet, array $ordersData): void
    {
        $sheet = $spreadsheet->getSheetByName('Worksheet');
        if ($sheet) {
            $sheet->setTitle('خروجی اکسل سایت');
        } else {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('خروجی اکسل سایت');
        }
        $sheet->setRightToLeft(true);

        $headers = [
            'ردیف', 'وضعیت', 'درگاه پرداخت', 'تاریخ و ساعت', 'شماره فاکتور',
            'نام خریدار', 'موبایل خریدار', 'کد ملی', 'کد پیگیری', 'اقلام خریداری شده',
            'تعداد اقلام', 'روش ارسال', 'نوع تحویل', 'نوع پرداخت', 'مبلغ پرداخت شده',
            'شهر', 'آدرس', 'نام گیرنده', 'موبایل گیرنده',
        ];

        $headerStyle = [
            'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ];

        foreach ($headers as $col => $header) {
            $cell = $sheet->getCell($this->colLetter($col + 1).'1');
            $cell->setValue($header);
            $cell->getStyle()->applyFromArray($headerStyle);
        }

        $dataStyle = [
            'font' => ['name' => 'Tahoma', 'size' => 10],
            'alignment' => ['vertical' => 'center'],
        ];

        $rowNum = 2;
        $index = 1;
        foreach ($ordersData as $data) {
            $rowData = array_merge([$index], $data);
            foreach ($rowData as $col => $value) {
                $cell = $sheet->getCell($this->colLetter($col + 1).$rowNum);
                $cell->setValue($value);
                $cell->getStyle()->applyFromArray($dataStyle);
            }
            $rowNum++;
            $index++;
        }

        $widths = [10, 18, 16, 22, 24, 24, 18, 0, 22, 50, 14, 22, 0, 20, 0, 18, 70, 24, 18];
        foreach ($widths as $i => $w) {
            if ($w > 0) {
                $sheet->getColumnDimension($this->colLetter($i + 1))->setWidth($w);
            }
        }
    }

    private function createLabelSheet(Spreadsheet $spreadsheet, array $ordersData): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('گزارش');
        $sheet->setRightToLeft(true);

        $senderName = ' فرستنده : شرکت تجارت الکترونیکی فناوران ساویس مرجان';
        $senderAddress = ' " تهران - خ کریم خان زند تقاطع حافظ مجتمع الماس کریم خان طبقه اول اداری واحد109 اداری " ';
        $senderInfo = ' تلفن: 09924457068-86038543  -  کدپستی: 1598738119  ';

        $colWidths = [23.43, 38.57, 9.71, 15.86, 12.57, 13.14, 12.43];
        foreach ($colWidths as $i => $w) {
            $sheet->getColumnDimension($this->colLetter($i + 1))->setWidth($w);
        }

        $normalHeights = [40.5, 19.5, 19.5, 20.25, 31.5, 31.5, 31.5, 59.25, 31.5, 31.5];

        $labelIndex = 0;
        foreach ($ordersData as $data) {
            $labelIndex++;
            $dataRow = $labelIndex + 1;
            $base = ($labelIndex - 1) * 10;

            foreach ($normalHeights as $i => $h) {
                $sheet->getRowDimension($base + $i + 1)->setRowHeight($h);
            }

            $r = $base + 1;

            $sheet->mergeCells("A$r:F$r");
            $c = $sheet->getCell("A$r");
            $c->setValue($senderName);
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 20, 'bold' => true],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);

            $r2 = $r + 1;
            $r4 = $r + 3;
            $sheet->mergeCells("A$r2:F$r4");
            $c = $sheet->getCell("A$r2");
            $c->setValue('"'.trim($senderAddress, ' "').'"'."\n".$senderInfo);
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 16, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            ]);
            for ($col = 1; $col <= 6; $col++) {
                $sheet->getStyle($this->colLetter($col).$r4)->applyFromArray([
                    'border' => ['bottom' => ['style' => Border::BORDER_MEDIUM]],
                ]);
            }

            $r5 = $r + 4;
            $r6 = $r + 5;
            $r7 = $r + 6;
            $r8 = $r + 7;
            $r9 = $r + 8;
            $r10 = $r + 9;

            $sheet->mergeCells("A$r5:A$r6");
            $c = $sheet->getCell("A$r5");
            $c->setValue('سفارش دهنده:');
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 20, 'bold' => true, 'color' => ['rgb' => 'FF0000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);

            $c = $sheet->getCell("B$r5");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,6)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 20, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['vertical' => 'center'],
            ]);

            $c = $sheet->getCell("B$r6");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,7)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 26, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
            ]);

            $sheet->mergeCells("C$r5:C$r6");
            $c = $sheet->getCell("C$r5");
            $c->setValue('گیرنده:');
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 16, 'bold' => true, 'color' => ['rgb' => 'FF0000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            ]);

            $sheet->mergeCells("D$r5:F$r5");
            $c = $sheet->getCell("D$r5");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,18)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 20, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
            ]);

            $sheet->mergeCells("D$r6:F$r6");
            $c = $sheet->getCell("D$r6");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,19)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 26, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
            ]);

            $sheet->mergeCells("A$r7:F$r8");
            $c = $sheet->getCell("A$r7");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,17)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 16, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            ]);

            $c = $sheet->getCell("A$r9");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,4)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 12, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);

            $c = $sheet->getCell("B$r9");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,11)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 17, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            ]);

            $sheet->mergeCells("D$r9:E$r9");
            $c = $sheet->getCell("D$r9");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,13)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 12, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);

            $c = $sheet->getCell("F$r9");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,12)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);

            $c = $sheet->getCell("A$r10");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,5)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 12, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);

            $c = $sheet->getCell("B$r10");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,8)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 16, 'bold' => true, 'color' => ['rgb' => 'FF0000']],
                'alignment' => ['horizontal' => 'left'],
            ]);

            $c = $sheet->getCell("C$r10");
            $c->setValue('ش.سفارش');
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '000000']],
                'alignment' => ['horizontal' => 'right', 'vertical' => 'center', 'wrapText' => true],
            ]);

            $c = $sheet->getCell("D$r10");
            $c->setValue("=INDEX('خروجی اکسل سایت'!A:X,G$r10,16)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 17, 'bold' => true, 'color' => ['rgb' => 'FF0000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);

            $c = $sheet->getCell("E$r10");
            $c->setValue("=ROUND(INDEX('خروجی اکسل سایت'!A:X,G$r10,15)/10000000,0)");
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 20, 'bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFFF00']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);

            $c = $sheet->getCell("F$r10");
            $c->setValue('=IF(E'.$r10.'>100,100,E'.$r10.')');
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 20, 'bold' => true, 'color' => ['rgb' => 'FF0000']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'numberFormat' => ['formatCode' => '0"م ن"'],
            ]);

            $c = $sheet->getCell("G$r10");
            $c->setValue($labelIndex + 1);
            $c->getStyle()->applyFromArray([
                'font' => ['name' => 'B Titr', 'size' => 20, 'bold' => true, 'color' => ['rgb' => 'FF0000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFFF00']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);
        }
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $n = $index;
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26);
        }

        return $letter;
    }

    private function toJalali(string $datetime): string
    {
        $parts = explode(' ', $datetime);
        $dateParts = explode('-', $parts[0]);
        $y = (int) $dateParts[0];
        $m = (int) $dateParts[1];
        $d = (int) $dateParts[2];
        $time = $parts[1] ?? '00:00:00';

        $gDM = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy = $y - 1600;
        $gm = $m - 1;
        $gd = $d - 1;
        $gDayNo = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
        $gDayNo += $gDM[$gm] + $gd;

        $jDayNo = $gDayNo - 79;
        $jNp = intdiv($jDayNo, 12053);
        $jDayNo %= 12053;
        $jy = 979 + 33 * $jNp + 4 * intdiv($jDayNo, 1461);
        $jDayNo %= 1461;
        if ($jDayNo >= 366) {
            $jy += intdiv($jDayNo - 1, 365);
            $jDayNo = ($jDayNo - 1) % 365;
        }
        if ($jDayNo < 186) {
            $jm = 1 + intdiv($jDayNo, 31);
            $jd = 1 + ($jDayNo % 31);
        } else {
            $jDayNo -= 186;
            $jm = 7 + intdiv($jDayNo, 30);
            $jd = 1 + ($jDayNo % 30);
        }

        $hm = str_pad($jm, 2, '0', STR_PAD_LEFT);
        $hd = str_pad($jd, 2, '0', STR_PAD_LEFT);
        $timeParts = explode(':', $time);
        $hh = str_pad($timeParts[0] ?? '00', 2, '0', STR_PAD_LEFT);
        $ii = str_pad($timeParts[1] ?? '00', 2, '0', STR_PAD_LEFT);

        return "{$jy}/{$hm}/{$hd} - {$hh}:{$ii}";
    }
}
