<?php

namespace App\Services;

use App\Models\CollectionRoute;
use App\Models\User;
use App\Models\Neighborhood;
use App\Models\Province;
use App\Models\Ward;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RouteExcelService
{
    private array $sheets = [
        'Tinh' => ['Mã tỉnh *', 'Tên tỉnh *'],
        'PhuongXa' => ['Mã tỉnh *', 'Mã phường/xã *', 'Tên phường/xã *'],
        'ThonXomTo' => ['Mã phường/xã *', 'Mã thôn/xóm/tổ *', 'Tên thôn/xóm/tổ *'],
        'TuyenThu' => ['Mã thôn/xóm/tổ *', 'Mã tuyến *', 'Tên tuyến *', 'Tài khoản người phụ trách (phân cách bởi dấu phẩy)', 'Mô tả'],
    ];

    public function template(): string
    {
        $book = new Spreadsheet(); $book->removeSheetByIndex(0);
        foreach ($this->sheets as $name => $headers) {
            $sheet = $book->createSheet(); $sheet->setTitle($name);
            $sheet->fromArray($headers, null, 'A1');
            $last = chr(64 + count($headers));
            $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF168451');
            $sheet->freezePane('A2'); $sheet->setAutoFilter("A1:{$last}1");
            foreach (range('A', $last) as $column) $sheet->getColumnDimension($column)->setWidth(26);
        }
        $book->getSheetByName('Tinh')->fromArray([['48', 'Thành phố Đà Nẵng']], null, 'A2');
        $book->getSheetByName('PhuongXa')->fromArray([['48', '20275', 'Phường An Khê']], null, 'A2');
        $book->getSheetByName('ThonXomTo')->fromArray([['20275', 'TO-01', 'Tổ 01']], null, 'A2');
        $book->getSheetByName('TuyenThu')->fromArray([['TO-01', 'AK-01', 'Tuyến An Khê 01', 'collector01,collector02', 'Dữ liệu mẫu']], null, 'A2');
        $path = tempnam(sys_get_temp_dir(), 'route-template-').'.xlsx'; (new Xlsx($book))->save($path);
        return $path;
    }

    public function import(UploadedFile $file): array
    {
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        foreach (array_keys($this->sheets) as $name) if (! $book->getSheetByName($name)) throw ValidationException::withMessages(['file' => "Thiếu sheet {$name}."]);
        $counts = ['provinces'=>0,'wards'=>0,'neighborhoods'=>0,'routes'=>0]; $errors = [];
        DB::transaction(function () use ($book, &$counts, &$errors) {
            foreach ($this->rows($book, 'Tinh') as [$row,$v]) try { Province::withTrashed()->updateOrCreate(['code'=>$v[0]], ['name'=>$v[1], 'deleted_at'=>null]); $counts['provinces']++; } catch (\Throwable $e) { $errors[]="Tinh dòng {$row}: {$e->getMessage()}"; }
            foreach ($this->rows($book, 'PhuongXa') as [$row,$v]) try { $p=Province::where('code',$v[0])->firstOrFail(); Ward::withTrashed()->updateOrCreate(['province_id'=>$p->id,'code'=>$v[1]], ['name'=>$v[2], 'deleted_at'=>null]); $counts['wards']++; } catch (\Throwable $e) { $errors[]="PhuongXa dòng {$row}: không tìm thấy mã tỉnh hoặc dữ liệu không hợp lệ."; }
            foreach ($this->rows($book, 'ThonXomTo') as [$row,$v]) try { $w=Ward::where('code',$v[0])->firstOrFail(); Neighborhood::withTrashed()->updateOrCreate(['ward_id'=>$w->id,'code'=>$v[1]], ['name'=>$v[2], 'deleted_at'=>null]); $counts['neighborhoods']++; } catch (\Throwable $e) { $errors[]="ThonXomTo dòng {$row}: không tìm thấy mã phường/xã hoặc dữ liệu không hợp lệ."; }
            foreach ($this->rows($book, 'TuyenThu') as [$row,$v]) try { $n=Neighborhood::where('code',$v[0])->firstOrFail(); $route=CollectionRoute::withTrashed()->updateOrCreate(['code'=>$v[1]], ['neighborhood_id'=>$n->id,'name'=>$v[2],'description'=>$v[4]??null,'is_active'=>true,'deleted_at'=>null]); $accounts=array_filter(array_map('trim',explode(',',(string)($v[3]??'')))); $ids=User::whereIn('username',$accounts)->pluck('id'); if(count($accounts)!==$ids->count()) throw new \RuntimeException('Có tài khoản người dùng không tồn tại.'); $route->users()->sync($ids); $counts['routes']++; } catch (\Throwable $e) { $errors[]="TuyenThu dòng {$row}: {$e->getMessage()}"; }
            if ($errors) throw ValidationException::withMessages(['rows'=>$errors]);
        });
        return $counts;
    }

    private function rows(Spreadsheet $book, string $sheet): array
    {
        $rows=$book->getSheetByName($sheet)->toArray(null,true,true,false); $result=[];
        foreach (array_slice($rows,1,null,true) as $index=>$values) if (trim((string)($values[0]??''))!=='') $result[]=[$index+1,array_map(fn($v)=>trim((string)$v),$values)];
        return $result;
    }
}
