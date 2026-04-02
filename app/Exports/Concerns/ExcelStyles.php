<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait ExcelStyles
{
    protected function styleSheet(Worksheet $sheet, int $dataRows, int $cols): void
    {
        $lastCol = chr(64 + $cols);
        $lastRow = $dataRows + 1;

        // Header row
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A7C6F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Zebra striping
        for ($i = 2; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(18);
            if ($i % 2 === 0) {
                $sheet->getStyle("A{$i}:{$lastCol}{$i}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F7F5F2']],
                ]);
            }
        }

        // Auto-width columns
        for ($i = 1; $i <= $cols; $i++) {
            $sheet->getColumnDimension(chr(64 + $i))->setAutoSize(true);
        }

        // Freeze header
        $sheet->freezePane('A2');
    }

    protected function styleTotalRow(Worksheet $sheet, int $row, int $cols): void
    {
        $lastCol = chr(64 + $cols);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4F0']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE]],
        ]);
    }
}
