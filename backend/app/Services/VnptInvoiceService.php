<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class VnptInvoiceService
{
    public function __construct(private readonly InvoiceSettingService $settingService) {}

    public function publish(Payment $payment, ?int $issuerId = null): array
    {
        $payment->loadMissing(['household.services.service', 'months.householdService.service', 'invoice']);
        $invoice = $payment->invoice;
        if (! $invoice) throw ValidationException::withMessages(['invoice' => 'Giao dịch chưa có hóa đơn chờ phát hành.']);
        if ($invoice->status === 'DA_PHAT_HANH') throw ValidationException::withMessages(['invoice' => 'Hóa đơn đã được phát hành.']);
        $settings = $this->settingService->loadForPublish();
        $invoice->update(['status' => 'DANG_PHAT_HANH']);

        $xmlData = $this->buildInvoiceXml($payment, $settings);
        $credentials = [
            [$settings['WS_USER_ID'], $settings['WS_PASSWORD_ID'], $settings['C_USER_ID'], $settings['C_PASSWORD_ID']],
            [$settings['C_USER_ID'], $settings['C_PASSWORD_ID'], $settings['WS_USER_ID'], $settings['WS_PASSWORD_ID']],
        ];
        $last = null;
        foreach ($credentials as [$account, $accountPassword, $username, $password]) {
            try {
                $response = Http::timeout(45)->withHeaders(['SOAPAction' => 'http://tempuri.org/ImportAndPublishInv'])
                    ->withBody($this->soapEnvelope($xmlData, $settings, $account, $accountPassword, $username, $password), 'text/xml; charset=utf-8')
                    ->post($settings['PUBLISH_SERVICE_ADDRESS_ID']);
                if (! $response->successful()) throw new \RuntimeException('VNPT trả về HTTP '.$response->status());
                $last = $this->parseResult($response->body(), $payment->code);
                if ($last['success'] || ! str_contains($last['message'], 'ERR:1')) break;
            } catch (\Throwable $exception) {
                $last = ['success' => false, 'message' => $exception->getMessage(), 'fkey' => null, 'invoice_no' => null];
            }
        }
        if (! $last || ! $last['success']) {
            $invoice->update(['status' => 'PHAT_HANH_LOI', 'provider_response' => $last]);
            throw ValidationException::withMessages(['invoice' => $last['message'] ?? 'Không thể phát hành hóa đơn VNPT.']);
        }
        $invoice->update(['status' => 'DA_PHAT_HANH', 'invoice_no' => $last['invoice_no'], 'provider_response' => $last, 'issued_at' => now(), 'issued_by' => $issuerId]);
        $payment->update(['status' => 'DA_PHAT_HANH_HOA_DON']);

        return ['payment_id' => $payment->id, 'invoice_id' => $invoice->id, ...$last];
    }

    private function buildInvoiceXml(Payment $payment, array $settings): string
    {
        $household = $payment->household;
        $total = (float) $payment->amount;
        $base = round($payment->months->sum(fn ($month) => (float) ($month->base_price ?? $month->amount)), 2);
        $tax = round($payment->months->sum(fn ($month) => (float) ($month->tax_fee_amount ?? 0)), 2);
        $period = $payment->from_month->format('m/Y').' - '.$payment->to_month->format('m/Y');
        $key = $payment->code;
        $e = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $products = $payment->months->flatMap(function ($month) use ($e) {
            $service = $month->householdService?->service?->name ?? 'Dịch vụ thu gom rác';
            $lines = $month->pricing_breakdown ?: [['document_number' => $month->document_number, 'effective_from' => $month->month->toDateString(), 'effective_to' => $month->month->copy()->endOfMonth()->toDateString(), 'days' => $month->month->daysInMonth, 'days_in_month' => $month->month->daysInMonth, 'base_price' => (float) $month->base_price, 'tax_fee_rate' => (float) $month->tax_fee_rate, 'tax_fee_amount' => (float) $month->tax_fee_amount]];
            return collect($lines)->map(function (array $line) use ($e, $service, $month) {
                $range = ($line['days'] ?? null) === ($line['days_in_month'] ?? null) ? '' : ' ('.$line['effective_from'].' - '.$line['effective_to'].')';
                $name = $service.' tháng '.$month->month->format('m/Y').' - theo '.$line['document_number'].$range;
                return '<Product><ProdName>'.$e($name).'</ProdName><ProdUnit>Tháng</ProdUnit><ProdQuantity>1</ProdQuantity><ProdPrice>'.round((float) $line['base_price']).'</ProdPrice><Amount>'.round((float) $line['base_price']).'</Amount><VATRate>'.(float) $line['tax_fee_rate'].'</VATRate><VATAmount>'.round((float) $line['tax_fee_amount']).'</VATAmount></Product>';
            });
        })->join('');
        return '<Invoices><Inv><key>'.$e($key).'</key><Invoice><CusCode>'.$e($household->code).'</CusCode><CusName>'.$e($household->owner_name).'</CusName><CusAddress>'.$e($household->invoice_address ?: $household->address).'</CusAddress><CusPhone>'.$e($household->phone).'</CusPhone><CusTaxCode>'.$e($household->tax_code).'</CusTaxCode><Buyer>'.$e($household->representative ?: $household->owner_name).'</Buyer><ArisingDate>'.now()->format('d/m/Y').'</ArisingDate><PaymentMethod>TM/CK</PaymentMethod><Products>'.$products.'</Products><Total>'.round($base).'</Total><VATAmount>'.round($tax).'</VATAmount><Amount>'.round($total).'</Amount><AmountInWords>'.$e(number_format($total, 0, ',', '.').' đồng').'</AmountInWords><ComName>'.$e($settings['Tên đơn vị']).'</ComName><ComTaxCode>'.$e($settings['Mã số thuế']).'</ComTaxCode><ComAddress>'.$e($settings['Địa chỉ']).'</ComAddress><ComPhone>'.$e($settings['Số điện thoại']).'</ComPhone><Note>'.$e('Kỳ thu '.$period).'</Note></Invoice></Inv></Invoices>';
    }

    private function soapEnvelope(string $xml, array $settings, string $account, string $accountPassword, string $username, string $password): string
    {
        $e = fn ($value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ImportAndPublishInv xmlns="http://tempuri.org/"><Account>'.$e($account).'</Account><ACpass>'.$e($accountPassword).'</ACpass><xmlInvData>'.$e($xml).'</xmlInvData><username>'.$e($username).'</username><password>'.$e($password).'</password><pattern>'.$e($settings['Mẫu số hóa đơn']).'</pattern><serial>'.$e($settings['Ký hiệu hóa đơn']).'</serial><convert>0</convert></ImportAndPublishInv></soap:Body></soap:Envelope>';
    }

    private function parseResult(string $soap, string $fkey): array
    {
        preg_match('/<(?:\w+:)?ImportAndPublishInvResult[^>]*>(.*?)<\/(?:\w+:)?ImportAndPublishInvResult>/si', $soap, $match);
        $result = trim(html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if ($result === '' || str_starts_with(strtoupper($result), 'ERR')) return ['success' => false, 'message' => $result ?: 'Không đọc được phản hồi VNPT.', 'fkey' => null, 'invoice_no' => null];
        $invoiceNo = null;
        if (preg_match('/'.preg_quote($fkey, '/').'_(\d+)/i', $result, $numberMatch)) $invoiceNo = str_pad($numberMatch[1], 7, '0', STR_PAD_LEFT);
        if (! $invoiceNo && preg_match('/(?:invoiceNo|invNo|serial)\s*[:=|]\s*([A-Za-z0-9.\-\/]+)/i', $result, $numberMatch)) $invoiceNo = $numberMatch[1];
        return ['success' => true, 'message' => $result, 'fkey' => $fkey, 'invoice_no' => $invoiceNo];
    }

    public function downloadOfficialInvoice(Payment $payment): array
    {
        $payment->loadMissing('invoice');
        $invoice = $payment->invoice;
        if (! $invoice || $invoice->status !== 'DA_PHAT_HANH') throw ValidationException::withMessages(['invoice' => 'Hóa đơn chưa được phát hành.']);
        $fkey = $invoice->provider_response['fkey'] ?? $payment->code;
        $settings = $this->settingService->loadForPublish();
        $url = $settings['PORTAL_SERVICE_ADDRESS_ID'] ?: preg_replace('~/Publishservice\.asmx$~i', '/portalservice.asmx', $settings['PUBLISH_SERVICE_ADDRESS_ID']);
        $credentials = [[$settings['WS_USER_ID'], $settings['WS_PASSWORD_ID']], [$settings['C_USER_ID'], $settings['C_PASSWORD_ID']]];
        $errors = [];
        foreach ($credentials as $credentialIndex => [$username, $password]) {
            foreach ([['getInvViewFkeyNoPay', 'http://tempuri.org/getInvViewFkeyNoPay'], ['GetInvViewFkeyNoPay', 'http://tempuri.org/GetInvViewFkeyNoPay']] as [$operation, $action]) {
                $body = $this->portalEnvelope($operation, $fkey, $username, $password);
                try {
                    $response = Http::timeout(45)->withHeaders(['SOAPAction' => $action])->withBody($body, 'text/xml; charset=utf-8')->post($url);
                    if (! $response->successful()) { $errors[] = "{$operation}: HTTP {$response->status()}"; continue; }
                    $raw = $this->extractSoapResult($response->body(), $operation.'Result');
                    if ($raw === '') { $errors[] = "{$operation}: VNPT không trả nội dung"; continue; }
                    if (str_starts_with(strtoupper($raw), 'ERR')) { $errors[] = "Tài khoản #".($credentialIndex + 1).": {$raw}"; continue; }
                    return $this->normalizeInvoiceContent($raw, $invoice->invoice_no ?: $payment->code);
                } catch (\Throwable $exception) { $errors[] = "{$operation}: {$exception->getMessage()}"; }
            }
        }

        foreach ($credentials as [$username, $password]) {
            $operation = 'GetLinkInvViewFkey';
            try {
                $response = Http::timeout(45)->withHeaders(['SOAPAction' => 'http://tempuri.org/GetLinkInvViewFkey'])
                    ->withBody($this->portalEnvelope($operation, $fkey, $username, $password), 'text/xml; charset=utf-8')->post($url);
                if (! $response->successful()) { $errors[] = "{$operation}: HTTP {$response->status()}"; continue; }
                $raw = $this->extractSoapResult($response->body(), 'GetLinkInvViewFkeyResult');
                if (preg_match('~https?://[^\s"\'<>]+~i', $raw, $link)) {
                    $safeUrl = htmlspecialchars($link[0], ENT_QUOTES, 'UTF-8');
                    return ['content' => '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url='.$safeUrl.'"></head><body><p>Đang mở hóa đơn VNPT...</p><p><a href="'.$safeUrl.'">Bấm vào đây nếu trang không tự chuyển.</a></p></body></html>', 'mime' => 'text/html; charset=utf-8', 'filename' => 'hoa-don-'.$payment->code.'.html'];
                }
                $errors[] = $raw !== '' ? "{$operation}: {$raw}" : "{$operation}: VNPT không trả link";
            } catch (\Throwable $exception) { $errors[] = "{$operation}: {$exception->getMessage()}"; }
        }
        $detail = implode(' | ', array_slice(array_unique($errors), -4));
        throw ValidationException::withMessages(['invoice' => 'Không tải được hóa đơn VNPT bằng Fkey '.$fkey.'. '.$detail]);
    }

    private function portalEnvelope(string $operation, string $fkey, string $username, string $password): string
    {
        $e = fn ($value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><'.$operation.' xmlns="http://tempuri.org/"><fkey>'.$e($fkey).'</fkey><userName>'.$e($username).'</userName><userPass>'.$e($password).'</userPass></'.$operation.'></soap:Body></soap:Envelope>';
    }

    private function extractSoapResult(string $soap, string $tag): string
    {
        preg_match('/<(?:\w+:)?'.preg_quote($tag, '/').'[^>]*>(.*?)<\/(?:\w+:)?'.preg_quote($tag, '/').'>/si', $soap, $match);
        $raw = trim(html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if (preg_match('/<!\[CDATA\[(.*?)\]\]>/si', $raw, $cdata)) $raw = trim($cdata[1]);
        return $raw;
    }

    private function normalizeInvoiceContent(string $raw, string $name): array
    {
        if (preg_match('/<!doctype html|<html[\s>]/i', $raw)) return ['content' => $raw, 'mime' => 'text/html; charset=utf-8', 'filename' => "hoa-don-{$name}.html"];
        $compact = preg_replace('/\s+/', '', $raw);
        $decoded = base64_decode($compact, true);
        if ($decoded !== false) {
            if (str_starts_with($decoded, '%PDF')) return ['content' => $decoded, 'mime' => 'application/pdf', 'filename' => "hoa-don-{$name}.pdf"];
            if (preg_match('/<!doctype html|<html[\s>]/i', $decoded)) return ['content' => $decoded, 'mime' => 'text/html; charset=utf-8', 'filename' => "hoa-don-{$name}.html"];
        }
        return ['content' => $raw, 'mime' => 'text/html; charset=utf-8', 'filename' => "hoa-don-{$name}.html"];
    }
}
