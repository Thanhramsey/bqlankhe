<?php

namespace App\Services;

use App\Models\CollectionRoute;
use App\Models\Household;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HouseholdExcelService
{
    private array $headers = ['Số thứ tự', 'Mã hộ *', 'Họ tên *', 'Số điện thoại', 'Địa chỉ *', 'Địa chỉ HĐ', 'CCCD', 'Mã tuyến', 'Mã dịch vụ *', 'Ngày bắt đầu dịch vụ (dd/mm/yyyy)', 'Email', 'Mã số thuế', 'Người đại diện', 'Ghi chú', 'Trạng thái (HOAT_DONG/NGUNG)'];

    public function template(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('HoDan');
        $sheet->fromArray($this->headers, null, 'A1');
        $sheet->fromArray([[1, 'HD-0001', 'Nguyễn Văn A', '0905000000', '01 Nguyễn Tất Thành, An Khê', 'Trụ sở Công ty A, Gia Lai', '048000000001', 'AK-01', 'RAC-HO', '01/01/2026', 'vana@example.com', '0400000001', 'Nguyễn Văn A', 'Dữ liệu mẫu', 'HOAT_DONG']], null, 'A2');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:O2');
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF168451']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF0F7044']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);
        foreach ([12, 16, 24, 18, 36, 36, 20, 16, 34, 24, 28, 20, 24, 30, 30] as $index => $width) {
            $sheet->getColumnDimension(chr(65 + $index))->setWidth($width);
        }
        $sheet->getStyle('A2:O200')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $validation = $sheet->getCell('O2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(false)->setShowDropDown(true)->setFormula1('"HOAT_DONG,NGUNG"');
        for ($row = 3; $row <= 200; $row++) $sheet->getCell("O{$row}")->setDataValidation(clone $validation);
        $path = tempnam(sys_get_temp_dir(), 'household-template-').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function import(UploadedFile $file): array
    {
        $sheet = IOFactory::load($file->getRealPath())->getSheetByName('HoDan');
        if (! $sheet) throw ValidationException::withMessages(['file' => 'Thiếu sheet HoDan. Vui lòng sử dụng đúng file mẫu.']);
        $rows = $sheet->toArray(null, true, true, false);
        $errors = []; $imported = 0;
        DB::transaction(function () use ($rows, &$errors, &$imported) {
            foreach (array_slice($rows, 1, null, true) as $index => $values) {
                $row = $index + 1;
                if (trim((string) ($values[1] ?? '')) === '') continue;
                try {
                    $route = trim((string) ($values[7] ?? '')) === '' ? null : CollectionRoute::where('code', trim((string) $values[7]))->firstOrFail();
                    $service = Service::where('code', trim((string) ($values[8] ?? '')))->where('is_active', true)->first();
                    if (! $service) throw new \RuntimeException('Mã dịch vụ không tồn tại hoặc đang ngừng hoạt động.');
                    $household = Household::withTrashed()->firstOrNew(['code' => trim((string) $values[1])]);
                    $household->fill([
                        'sequence_number' => ($values[0] ?? '') === '' ? null : (int) $values[0], 'owner_name' => trim((string) $values[2]),
                        'phone' => $this->nullable($values[3] ?? null), 'address' => trim((string) $values[4]), 'invoice_address' => $this->nullable($values[5] ?? null), 'identity_number' => $this->nullable($values[6] ?? null),
                        'collection_route_id' => $route?->id, 'email' => $this->nullable($values[10] ?? null), 'tax_code' => $this->nullable($values[11] ?? null),
                        'representative' => $this->nullable($values[12] ?? null), 'note' => $this->nullable($values[13] ?? null),
                        'ward' => 'An Khê', 'is_active' => strtoupper(trim((string) ($values[14] ?? 'HOAT_DONG'))) !== 'NGUNG', 'deleted_at' => null,
                    ]);
                    if ($household->owner_name === '' || $household->address === '') throw new \RuntimeException('Họ tên và địa chỉ là bắt buộc.');
                    $household->save();
                    app(HouseholdService::class)->update($household, [...$household->only($household->getFillable()), 'service_id' => $service->id, 'service_started_at' => $this->dateValue($values[9] ?? null)]);
                    $imported++;
                } catch (\Throwable $exception) {
                    $errors[] = "Dòng {$row}: {$exception->getMessage()}";
                }
            }
            if ($errors) throw ValidationException::withMessages(['rows' => $errors]);
        });

        return ['households' => $imported];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, trim((string) $value));
            if ($date !== false) return $date->format('Y-m-d');
        }
        throw new \RuntimeException('Ngày bắt đầu dịch vụ không hợp lệ, dùng định dạng dd/mm/yyyy.');
    }
}
