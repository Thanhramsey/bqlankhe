<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><style>
@page { margin: 24px; } body { font-family: DejaVu Sans, sans-serif; color:#1f2d26; font-size:10px; }
h1 { margin:0 0 5px; color:#0f7044; font-size:20px; text-align:center; } .subtitle { text-align:center; color:#607067; margin-bottom:16px; }
.summary { width:100%; margin-bottom:15px; border-collapse:separate; border-spacing:8px 0; }.summary td { padding:10px; background:#edf7f1; border:1px solid #cde3d5; }.summary strong { display:block; margin-top:4px; color:#0f7044; font-size:14px; }
table.data { width:100%; border-collapse:collapse; } table.data th { padding:8px 6px; color:white; background:#168451; border:1px solid #0f7044; text-align:center; } table.data td { padding:6px; border-bottom:1px solid #dce5df; } .right{text-align:right}.center{text-align:center}.muted{color:#66776e}.footer{margin-top:12px;text-align:right;color:#66776e}
</style></head><body>
<h1>{{ $report['filters']['report_type'] === 'detail' ? 'BÁO CÁO DOANH THU CHI TIẾT' : 'BÁO CÁO DOANH THU TỔNG HỢP' }}</h1>
<div class="subtitle">Từ {{ date('d/m/Y', strtotime($report['filters']['from_date'])) }} đến {{ date('d/m/Y', strtotime($report['filters']['to_date'])) }} · Căn cứ: {{ $report['filters']['basis'] === 'issued_at' ? 'Ngày xuất hóa đơn' : 'Ngày thu tiền' }}</div>
<table class="summary"><tr><td>Tổng doanh thu<strong>{{ number_format($report['summary']['total_revenue'],0,',','.') }} ₫</strong></td><td>Số giao dịch<strong>{{ number_format($report['summary']['transactions']) }}</strong></td><td>Số hộ dân<strong>{{ number_format($report['summary']['households']) }}</strong></td><td>Bình quân/giao dịch<strong>{{ number_format($report['summary']['average'],0,',','.') }} ₫</strong></td></tr></table>
@if($report['filters']['report_type'] === 'detail')
<table class="data"><thead><tr><th>STT</th><th>Ngày ghi nhận</th><th>Mã phiếu</th><th>Số HĐ</th><th>Hộ dân</th><th>Tuyến thu</th><th>Nhân viên</th><th>Kỳ thu</th><th>Số tiền</th></tr></thead><tbody>
@forelse($report['details'] as $i=>$item)<tr><td class="center">{{ $i+1 }}</td><td>{{ date('d/m/Y H:i',strtotime($item['date'])) }}</td><td>{{ $item['code'] }}</td><td>{{ $item['invoice_no'] ?: '—' }}</td><td>{{ $item['household_code'] }} · {{ $item['household_name'] }}</td><td>{{ $item['route_name'] ?: '—' }}</td><td>{{ $item['collector_name'] ?: '—' }}</td><td>{{ $item['from_month'] }} – {{ $item['to_month'] }}</td><td class="right">{{ number_format($item['amount'],0,',','.') }} ₫</td></tr>@empty<tr><td colspan="9" class="center muted">Không có dữ liệu</td></tr>@endforelse
</tbody></table>
@else
<table class="data"><thead><tr><th>STT</th><th>Nhóm báo cáo</th><th>Số giao dịch</th><th>Số hộ dân</th><th>Doanh thu</th><th>Tỷ trọng</th></tr></thead><tbody>
@forelse($report['groups'] as $i=>$item)<tr><td class="center">{{ $i+1 }}</td><td>{{ $item['label'] }}</td><td class="center">{{ $item['transactions'] }}</td><td class="center">{{ $item['households'] }}</td><td class="right">{{ number_format($item['amount'],0,',','.') }} ₫</td><td class="right">{{ $report['summary']['total_revenue'] > 0 ? number_format($item['amount']/$report['summary']['total_revenue']*100,1,',','.') : 0 }}%</td></tr>@empty<tr><td colspan="6" class="center muted">Không có dữ liệu</td></tr>@endforelse
</tbody></table>
@endif
<div class="footer">Ngày xuất báo cáo: {{ now()->format('d/m/Y H:i:s') }}</div>
</body></html>
