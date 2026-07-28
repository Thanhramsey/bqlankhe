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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HouseholdExcelService
{
    private array $headers = ['Số thứ tự', 'Mã hộ *', 'Họ tên *', 'Số điện thoại', 'Địa chỉ *', 'CCCD', 'Mã tuyến', 'Mã dịch vụ *', 'Email', 'Mã số thuế', 'Người đại diện', 'Ghi chú', 'Trạng thái (HOAT_DONG/NGUNG)'];

    public function template(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('HoDan');
        $sheet->fromArray($this->headers, null, 'A1');
        $sheet->fromArray([[1, 'HD-0001', 'Nguyễn Văn A', '0905000000', '01 Nguyễn Tất Thành, An Khê', '048000000001', 'AK-01', 'RAC-HO', 'vana@example.com', '0400000001', 'Nguyễn Văn A', 'Dữ liệu mẫu', 'HOAT_DONG']], null, 'A2');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:M2');
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF168451']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF0F7044']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);
        $widths = [12, 16, 24, 18, 36, 20, 16, 34, 28, 20, 24, 30, 30];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(chr(65 + $index))->setWidth($width);
        }
        $sheet->getStyle('A2:M200')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A2:M200')->getAlignment()->setWrapText(true);
        $validation = $sheet->getCell('M2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(false)->setShowDropDown(true)->setFormula1('"HOAT_DONG,NGUNG"');
        for ($row = 3; $row <= 200; $row++) {
            $sheet->getCell("M{$row}")->setDataValidation(clone $validation);
        }
        $path = tempnam(sys_get_temp_dir(), 'household-template-').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function import(UploadedFile $file): array
    {
        $sheet = IOFactory::load($file->getRealPath())->getSheetByName('HoDan');
        if (! $sheet) {
            throw ValidationException::withMessages(['file' => 'Thiếu sheet HoDan. Vui lòng sử dụng đúng file mẫu.']);
        }
        $rows = $sheet->toArray(null, true, true, false);
        $errors = []; $imported = 0;
        DB::transaction(function () use ($rows, &$errors, &$imported) {
            foreach (array_slice($rows, 1, null, true) as $index => $values) {
                $row = $index + 1;
                if (trim((string) ($values[1] ?? '')) === '') continue;
                try {
                    $route = trim((string) ($values[6] ?? '')) === '' ? null : CollectionRoute::where('code', trim((string) $values[6]))->firstOrFail();
                    $serviceCode = trim((string) ($values[7] ?? ''));
                    $service = Service::where('code', $serviceCode)->where('is_active', true)->first();
                    if (! $service) throw new \RuntimeException('Mã dịch vụ không tồn tại hoặc đang ngừng hoạt động.');
                    $code = trim((string) $values[1]);
                    $household = Household::withTrashed()->firstOrNew(['code' => $code]);
                    $household->fill([
                        'sequence_number' => ($values[0] ?? '') === '' ? null : (int) $values[0], 'owner_name' => trim((string) $values[2]),
                        'phone' => $this->nullable($values[3] ?? null), 'address' => trim((string) $values[4]), 'identity_number' => $this->nullable($values[5] ?? null),
                        'collection_route_id' => $route?->id, 'email' => $this->nullable($values[8] ?? null), 'tax_code' => $this->nullable($values[9] ?? null),
                        'representative' => $this->nullable($values[10] ?? null), 'note' => $this->nullable($values[11] ?? null),
                        'ward' => 'An Khê', 'is_active' => strtoupper(trim((string) ($values[12] ?? 'HOAT_DONG'))) !== 'NGUNG', 'deleted_at' => null,
                    ]);
                    if ($household->owner_name === '' || $household->address === '') throw new \RuntimeException('Họ tên và địa chỉ là bắt buộc.');
                    $household->save();
                    app(HouseholdService::class)->update($household, [...$household->only($household->getFillable()), 'service_id' => $service->id]);
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
}
