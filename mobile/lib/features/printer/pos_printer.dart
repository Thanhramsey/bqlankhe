import 'dart:typed_data';

import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_classic_bluetooth/flutter_classic_bluetooth.dart';
import 'package:image/image.dart' as img;
import 'package:intl/intl.dart';
import 'package:screenshot/screenshot.dart';
import 'package:shared_preferences/shared_preferences.dart';

class PosPrinter extends ChangeNotifier {
  PosPrinter._();
  static final instance = PosPrinter._();
  final bluetooth = FlutterClassicBluetooth();
  BtcConnection? _connection;
  List<BtcDevice> devices = [];
  bool scanning = false, connecting = false, printing = false;
  String? deviceName, deviceAddress, error;

  bool get connected => _connection?.isConnected == true;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    deviceAddress = prefs.getString('pos_printer_address');
    deviceName = prefs.getString('pos_printer_name');
    notifyListeners();
  }

  Future<void> scan() async {
    scanning = true;
    error = null;
    notifyListeners();
    try {
      final paired = await bluetooth.getPairedDevices();
      final found = await bluetooth.scan(timeout: const Duration(seconds: 8));
      devices = {
        for (final d in [...paired, ...found]) d.address: d
      }.values.toList()
        ..sort((a, b) {
          final ap = a.displayName.toUpperCase().contains('RI-5809DD') ? 0 : 1;
          final bp = b.displayName.toUpperCase().contains('RI-5809DD') ? 0 : 1;
          return ap != bp
              ? ap.compareTo(bp)
              : a.displayName.compareTo(b.displayName);
        });
    } catch (e) {
      error =
          'Không tìm được thiết bị. Hãy bật Bluetooth và cấp quyền cho ứng dụng.';
    } finally {
      scanning = false;
      notifyListeners();
    }
  }

  Future<bool> connect(BtcDevice device) async {
    connecting = true;
    error = null;
    notifyListeners();
    try {
      if (device.bondState != BtcBondState.bonded) {
        await bluetooth.bondDevice(device.address);
      }
      await disconnect();
      _connection = await bluetooth.connect(
          address: device.address, timeout: const Duration(seconds: 15));
      deviceAddress = device.address;
      deviceName = device.displayName;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('pos_printer_address', device.address);
      await prefs.setString('pos_printer_name', device.displayName);
      _connection!.stateStream.listen((_) => notifyListeners());
      return true;
    } catch (e) {
      error =
          'Không kết nối được ${device.displayName}. Hãy kiểm tra máy in và thử lại.';
      return false;
    } finally {
      connecting = false;
      notifyListeners();
    }
  }

  Future<bool> reconnectSaved() async {
    await load();
    if (connected) return true;
    if (deviceAddress == null) return false;
    return connect(BtcDevice(
        address: deviceAddress!,
        name: deviceName,
        bondState: BtcBondState.bonded));
  }

  Future<void> disconnect() async {
    final old = _connection;
    _connection = null;
    if (old != null) {
      await old.close();
      old.dispose();
    }
    notifyListeners();
  }

  Future<void> printReceipt(Map<String, dynamic> data,
      {Uint8List? paymentQr}) async {
    if (!connected) throw StateError('Máy in chưa kết nối');
    printing = true;
    error = null;
    notifyListeners();
    try {
      final screenshot = ScreenshotController();
      final bytes = await screenshot.captureFromWidget(
        _Receipt58(data: data, paymentQr: paymentQr),
        pixelRatio: 1,
        delay: const Duration(milliseconds: 200),
        context: null,
        // The receipt is taller than a phone screen after enlarging the QR.
        // Without an explicit off-screen canvas Flutter paints its overflow
        // warning stripes into the bitmap, making the QR impossible to scan.
        targetSize: const Size(384, 3000),
      );
      final receiptImage = img.decodeImage(bytes);
      if (receiptImage == null) {
        throw StateError('Không tạo được nội dung phiếu');
      }
      final croppedImage = img.trim(receiptImage,
          mode: img.TrimMode.bottomRightColor,
          sides: img.Trim.bottom,
          fuzzy: 0.02,
          padding: 16);
      // Thermal heads reproduce grey anti-aliased pixels inconsistently.
      // Convert once to pure black/white so every QR module has a crisp edge
      // and a solid density before ESC/POS packs the raster bits.
      final printImage = img.luminanceThreshold(croppedImage, threshold: 0.70);
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      final commands = <int>[
        ...generator.reset(),
        ...generator.imageRaster(printImage,
            align: PosAlign.center,
            highDensityHorizontal: true,
            highDensityVertical: true),
        ...generator.feed(1),
        ...generator.cut(),
      ];
      await _connection!.output.writeBytes(commands);
      await _connection!.output.allSent;
    } finally {
      printing = false;
      notifyListeners();
    }
  }
}

class _Receipt58 extends StatelessWidget {
  const _Receipt58({required this.data, this.paymentQr});
  final Map<String, dynamic> data;
  final Uint8List? paymentQr;

  @override
  Widget build(BuildContext context) {
    final org = data['organization'] as Map<String, dynamic>;
    final receipt = data['receipt'] as Map<String, dynamic>;
    final household = data['household'] as Map<String, dynamic>;
    final paidAt = DateTime.tryParse('${receipt['paid_at']}')?.toLocal();
    final money =
        NumberFormat.currency(locale: 'vi_VN', symbol: 'đ', decimalDigits: 0)
            .format(num.tryParse('${receipt['amount']}') ?? 0);
    String m(dynamic value) {
      final s = '$value';
      return s.length >= 7 ? '${s.substring(5, 7)}/${s.substring(0, 4)}' : '—';
    }

    Text t(String value,
            {double size = 23,
            FontWeight weight = FontWeight.normal,
            TextAlign align = TextAlign.left}) =>
        Text(value,
            textAlign: align,
            style: TextStyle(
                color: Colors.black,
                fontSize: size,
                height: 1.08,
                fontWeight: weight));
    Widget row(String label, dynamic value) => Padding(
        padding: const EdgeInsets.only(top: 3),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 110, child: t(label, size: 20)),
          Expanded(
              child: t('${value ?? '—'}', size: 20, weight: FontWeight.w600))
        ]));
    return Material(
        color: Colors.white,
        child: Container(
            width: 384,
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            color: Colors.white,
            child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  t('${org['name'] ?? ''}'.toUpperCase(),
                      size: 26,
                      weight: FontWeight.bold,
                      align: TextAlign.center),
                  t('${org['address'] ?? ''}',
                      size: 19, align: TextAlign.center),
                  t('MST: ${org['tax_code'] ?? '—'}  ·  ĐT: ${org['phone'] ?? '—'}',
                      size: 18, align: TextAlign.center),
                  t('TK: ${org['bank_account'] ?? '—'} - ${org['bank_code'] ?? ''}',
                      size: 18, align: TextAlign.center),
                  const Divider(color: Colors.black, thickness: 1.5),
                  t('PHIẾU THU TIỀN RÁC',
                      size: 31,
                      weight: FontWeight.bold,
                      align: TextAlign.center),
                  t('Ngày thu: ${paidAt == null ? '—' : DateFormat('dd/MM/yyyy HH:mm').format(paidAt)}',
                      size: 19, align: TextAlign.center),
                  t('Mã phiếu: ${receipt['code']}',
                      size: 19, align: TextAlign.center),
                  const Divider(color: Colors.black),
                  row('Mã hộ:', household['code']),
                  row('Khách hàng:', household['name']),
                  row('Địa chỉ:', household['address']),
                  row('Dịch vụ:', household['service']),
                  row('Kỳ thu:',
                      '${m(receipt['from_month'])} - ${m(receipt['to_month'])}'),
                  const Divider(color: Colors.black),
                  t('TỔNG TIỀN: $money',
                      size: 31,
                      weight: FontWeight.bold,
                      align: TextAlign.center),
                  if (paymentQr != null) ...[
                    const SizedBox(height: 8),
                    t('QUÉT MÃ THANH TOÁN',
                        size: 19,
                        weight: FontWeight.bold,
                        align: TextAlign.center),
                    Center(
                        child: Container(
                            color: Colors.white,
                            padding: const EdgeInsets.all(16),
                            child: Image.memory(paymentQr!,
                                width: 280,
                                height: 280,
                                fit: BoxFit.contain,
                                filterQuality: FilterQuality.none,
                                isAntiAlias: false))),
                    const SizedBox(height: 24),
                  ],
                  if (data['invoice_lookup_url'] != null) ...[
                    const Divider(color: Colors.black),
                    t('TRA CỨU HÓA ĐƠN',
                        size: 19,
                        weight: FontWeight.bold,
                        align: TextAlign.center),
                    const SizedBox(height: 5),
                    t('${data['invoice_lookup_url']}',
                        size: 16,
                        weight: FontWeight.w600,
                        align: TextAlign.center),
                    const SizedBox(height: 5),
                    t('Mã tra cứu: ${data['invoice_fkey']}',
                        size: 18, align: TextAlign.center)
                  ],
                  const Divider(color: Colors.black),
                  t('Người thu: ${receipt['collector_name'] ?? '—'}',
                      size: 20,
                      weight: FontWeight.bold,
                      align: TextAlign.center),
                  const SizedBox(height: 8),
                  t('Cảm ơn Quý khách!', size: 19, align: TextAlign.center),
                ])));
  }
}

class PrinterScreen extends StatefulWidget {
  const PrinterScreen({super.key});
  @override
  State<PrinterScreen> createState() => _PrinterScreenState();
}

class _PrinterScreenState extends State<PrinterScreen> {
  final printer = PosPrinter.instance;
  @override
  void initState() {
    super.initState();
    printer.addListener(refresh);
    printer.load().then((_) => printer.scan());
  }

  void refresh() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    printer.removeListener(refresh);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Kết nối máy in POS 58')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Card(
            child: ListTile(
                leading: Icon(
                    printer.connected
                        ? Icons.bluetooth_connected
                        : Icons.bluetooth_disabled,
                    color: printer.connected ? Colors.green : Colors.grey),
                title: Text(printer.connected ? 'Đã kết nối' : 'Chưa kết nối'),
                subtitle: Text(printer.deviceName ?? 'RI-5809DD'),
                trailing: printer.connected
                    ? TextButton(
                        onPressed: printer.disconnect,
                        child: const Text('Ngắt'))
                    : null)),
        if (printer.error != null)
          Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(printer.error!,
                  style: const TextStyle(color: Colors.red))),
        const SizedBox(height: 16),
        FilledButton.icon(
            onPressed: printer.scanning ? null : printer.scan,
            icon: printer.scanning
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.search),
            label: Text(printer.scanning
                ? 'Đang tìm thiết bị...'
                : 'Tìm kiếm thiết bị')),
        const SizedBox(height: 12),
        const Text('Thiết bị Bluetooth',
            style: TextStyle(fontWeight: FontWeight.bold)),
        for (final device in printer.devices)
          Card(
              child: ListTile(
                  leading: const Icon(Icons.print_outlined),
                  title: Text(device.displayName),
                  subtitle: Text(
                      '${device.address} · ${device.bondState == BtcBondState.bonded ? 'Đã ghép đôi' : 'Chưa ghép đôi'}'),
                  trailing: printer.connecting
                      ? null
                      : const Icon(Icons.chevron_right),
                  onTap: printer.connecting
                      ? null
                      : () async {
                          final success = await printer.connect(device);
                          if (!context.mounted) return;
                          if (success) {
                            Navigator.pop(context, true);
                          }
                        })),
      ]));
}
