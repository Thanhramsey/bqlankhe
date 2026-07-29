<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExportService
{
    public function create(string $title, array $headers, array $rows, array $widths = [], array $currencyColumns = []): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', mb_strtoupper($title));
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'Xuất lúc: '.now()->format('d/m/Y H:i:s').' · Tổng số: '.count($rows));
        $sheet->fromArray($headers, null, 'A4');
        if ($rows) $sheet->fromArray($rows, null, 'A5');

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0F7044']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => 'FF52665B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF168451']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF0F7044']]],
        ]);
        $lastRow = max(4, count($rows) + 4);
        if ($rows) {
            $sheet->getStyle("A5:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A5:{$lastColumn}{$lastRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFDDE5E0');
            foreach ($currencyColumns as $column) $sheet->getStyle("{$column}5:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0" ₫"');
        }
        foreach ($headers as $index => $_) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setWidth($widths[$index] ?? 18);
        }
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(4)->setRowHeight(30);
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:{$lastColumn}{$lastRow}");
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setRight(0.3)->setBottom(0.5)->setLeft(0.3);

        $path = tempnam(sys_get_temp_dir(), 'export-').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return $path;
    }
}
