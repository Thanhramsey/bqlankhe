import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

import 'auth/auth.dart';
import 'printer/pos_printer.dart';

final routesProvider =
    FutureProvider((ref) => ref.read(apiProvider).get('/mobile/routes'));
final statsProvider = FutureProvider(
    (ref) => ref.read(apiProvider).get('/mobile/statistics/summary'));

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginState();
}

class _LoginState extends ConsumerState<LoginScreen> {
  final user = TextEditingController();
  final pass = TextEditingController();
  bool hidden = true;

  @override
  void dispose() {
    user.dispose();
    pass.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(authProvider);
    return Scaffold(
        body: Container(
            decoration: const BoxDecoration(
                gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xff0e5638), Color(0xff23825a)])),
            child: SafeArea(
                child: Center(
              child: SingleChildScrollView(
                  padding: const EdgeInsets.all(22),
                  child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 420),
                      child: Card(
                          elevation: 12,
                          shadowColor: Colors.black26,
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(28)),
                          child: Padding(
                              padding:
                                  const EdgeInsets.fromLTRB(24, 26, 24, 22),
                              child: Column(children: [
                                Container(
                                    width: 116,
                                    height: 116,
                                    padding: const EdgeInsets.all(5),
                                    decoration: BoxDecoration(
                                        color: Colors.white,
                                        shape: BoxShape.circle,
                                        border: Border.all(
                                            color: const Color(0xffd9eadf),
                                            width: 2),
                                        boxShadow: const [
                                          BoxShadow(
                                              color: Color(0x2215633f),
                                              blurRadius: 18)
                                        ]),
                                    child: ClipOval(
                                        child: Image.asset('assets/logo.png',
                                            fit: BoxFit.cover))),
                                const SizedBox(height: 15),
                                const Text('BAN QUẢN LÝ PHƯỜNG AN KHÊ',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                        color: Color(0xff155f3e),
                                        fontSize: 20,
                                        fontWeight: FontWeight.w900,
                                        letterSpacing: .2)),
                                const SizedBox(height: 5),
                                const Text('Hệ thống quản lý thu phí rác',
                                    style: TextStyle(
                                        color: Color(0xff6b7e74),
                                        fontSize: 14)),
                                const SizedBox(height: 26),
                                TextField(
                                    controller: user,
                                    decoration: const InputDecoration(
                                        labelText: 'Tên đăng nhập',
                                        prefixIcon:
                                            Icon(Icons.person_outline))),
                                const SizedBox(height: 14),
                                TextField(
                                    controller: pass,
                                    obscureText: hidden,
                                    onSubmitted: (_) => login(),
                                    decoration: InputDecoration(
                                        labelText: 'Mật khẩu',
                                        prefixIcon:
                                            const Icon(Icons.lock_outline),
                                        suffixIcon: IconButton(
                                            onPressed: () => setState(
                                                () => hidden = !hidden),
                                            icon: Icon(hidden
                                                ? Icons.visibility
                                                : Icons.visibility_off)))),
                                if (state.error != null)
                                  Padding(
                                      padding: const EdgeInsets.only(top: 12),
                                      child: Text(state.error!,
                                          style: const TextStyle(
                                              color: Colors.red))),
                                const SizedBox(height: 20),
                                SizedBox(
                                    width: double.infinity,
                                    height: 54,
                                    child: FilledButton(
                                        onPressed: state.loading ? null : login,
                                        child: state.loading
                                            ? const CircularProgressIndicator()
                                            : const Text(
                                                'ĐĂNG NHẬP HỆ THỐNG'))),
                                if (state.biometricEnabled &&
                                    state.biometricAvailable) ...[
                                  const SizedBox(height: 12),
                                  SizedBox(
                                      width: double.infinity,
                                      height: 50,
                                      child: OutlinedButton.icon(
                                          onPressed: state.loading
                                              ? null
                                              : () => ref
                                                  .read(authProvider.notifier)
                                                  .biometricLogin(),
                                          icon: const Icon(Icons.fingerprint,
                                              size: 28),
                                          label: const Text(
                                              'ĐĂNG NHẬP BẰNG VÂN TAY')))
                                ],
                                const SizedBox(height: 18),
                                const Text('Phiên bản 1.0.0 · An Khê, Gia Lai',
                                    style: TextStyle(
                                        color: Colors.grey, fontSize: 12)),
                              ]))))),
            ))));
  }

  Future<void> login() async {
    if (user.text.trim().isEmpty || pass.text.length < 8) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Vui lòng nhập đầy đủ tài khoản và mật khẩu.')));
      return;
    }
    await ref.read(authProvider.notifier).login(user.text.trim(), pass.text);
  }
}

class MainShell extends StatefulWidget {
  const MainShell({super.key});
  @override
  State<MainShell> createState() => _ShellState();
}

class _ShellState extends State<MainShell> {
  int index = 0;
  final pages = const [
    HomeScreen(),
    HouseholdScreen(),
    TransactionScreen(),
    AnnouncementScreen(),
    ProfileScreen()
  ];
  @override
  Widget build(BuildContext context) => Scaffold(
        body: IndexedStack(index: index, children: pages),
        bottomNavigationBar: NavigationBar(
            selectedIndex: index,
            onDestinationSelected: (value) => setState(() => index = value),
            destinations: const [
              NavigationDestination(
                  icon: Icon(Icons.home_outlined),
                  selectedIcon: Icon(Icons.home_rounded),
                  label: 'Trang chủ'),
              NavigationDestination(
                  icon: Icon(Icons.payments_outlined),
                  selectedIcon: Icon(Icons.payments_rounded),
                  label: 'Thu tiền'),
              NavigationDestination(
                  icon: Icon(Icons.receipt_long_outlined),
                  selectedIcon: Icon(Icons.receipt_long_rounded),
                  label: 'Giao dịch'),
              NavigationDestination(
                  icon: Icon(Icons.notifications_outlined),
                  selectedIcon: Icon(Icons.notifications_rounded),
                  label: 'Thông báo'),
              NavigationDestination(
                  icon: Icon(Icons.person_outline),
                  selectedIcon: Icon(Icons.person_rounded),
                  label: 'Cá nhân'),
            ]),
      );
}

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    final user = auth.user;
    final stats = ref.watch(statsProvider);
    final name = '${user?['name'] ?? ''}';
    return Scaffold(
        appBar: AppBar(title: const Text('Tổng quan hôm nay'), actions: [
          Padding(
              padding: const EdgeInsets.only(right: 14),
              child: ClipOval(
                  child: Image.asset('assets/logo.png',
                      width: 38, height: 38, fit: BoxFit.cover)))
        ]),
        body: RefreshIndicator(
          onRefresh: () => ref.refresh(statsProvider.future),
          child: ListView(padding: const EdgeInsets.all(16), children: [
            Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                    gradient: const LinearGradient(
                        colors: [Color(0xff135f3e), Color(0xff23865c)]),
                    borderRadius: BorderRadius.circular(22),
                    boxShadow: const [
                      BoxShadow(
                          color: Color(0x33145f3e),
                          blurRadius: 18,
                          offset: Offset(0, 8))
                    ]),
                child: Row(children: [
                  Container(
                      width: 60,
                      height: 60,
                      padding: const EdgeInsets.all(3),
                      decoration: const BoxDecoration(
                          shape: BoxShape.circle, color: Colors.white),
                      child: ClipOval(
                          child: Image.asset('assets/logo.png',
                              fit: BoxFit.cover))),
                  const SizedBox(width: 14),
                  Expanded(
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                        const Text('Xin chào,',
                            style: TextStyle(
                                color: Color(0xffd7eee2), fontSize: 13)),
                        Text(name,
                            style: const TextStyle(
                                color: Colors.white,
                                fontSize: 20,
                                fontWeight: FontWeight.w900)),
                        Text('${user?['phone'] ?? 'Chưa có số điện thoại'}',
                            style: const TextStyle(
                                color: Color(0xffd7eee2), fontSize: 13))
                      ])),
                  const Icon(Icons.verified_user_outlined,
                      color: Color(0xffffd166))
                ])),
            const Padding(
                padding: EdgeInsets.fromLTRB(2, 22, 0, 8),
                child: Text('Kết quả thu phí',
                    style:
                        TextStyle(fontSize: 18, fontWeight: FontWeight.w900))),
            stats.when(
              data: (data) => GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 10,
                  childAspectRatio: 1.38,
                  children: [
                    Kpi('Hộ đã thu', '${data['households']}',
                        Icons.home_work_outlined, const Color(0xff1c7c54)),
                    Kpi('Tiền hôm nay', money(data['total']),
                        Icons.payments_outlined, const Color(0xff2563a6)),
                    Kpi('Tiền mặt', money(data['cash']), Icons.money,
                        const Color(0xffd48806)),
                    Kpi('Chuyển khoản', money(data['bank_transfer']),
                        Icons.account_balance, const Color(0xff7655b5)),
                  ]),
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, stackTrace) =>
                  const Text('Không tải được thống kê. Kéo xuống để thử lại.'),
            ),
            const Padding(
                padding: EdgeInsets.fromLTRB(2, 20, 0, 6),
                child: Text('Tiện ích nội bộ',
                    style:
                        TextStyle(fontSize: 18, fontWeight: FontWeight.w900))),
            Card(
                child: ListTile(
                    contentPadding:
                        const EdgeInsets.symmetric(horizontal: 15, vertical: 7),
                    leading: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                            color: const Color(0xffe2f1e8),
                            borderRadius: BorderRadius.circular(13)),
                        child: const Icon(Icons.contact_phone_outlined,
                            color: Color(0xff176b45))),
                    title: const Text('Danh bạ đơn vị',
                        style: TextStyle(fontWeight: FontWeight.w800)),
                    subtitle:
                        const Text('Tra cứu và liên hệ cán bộ, nhân viên'),
                    trailing:
                        const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                    onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (_) => const DirectoryScreen())))),
          ]),
        ));
  }
}

class Kpi extends StatelessWidget {
  const Kpi(this.label, this.value, this.icon, this.color, {super.key});
  final String label, value;
  final IconData icon;
  final Color color;
  @override
  Widget build(BuildContext context) => Card(
      child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                        color: color.withValues(alpha: .11),
                        borderRadius: BorderRadius.circular(11)),
                    child: Icon(icon, color: color, size: 22)),
                const Spacer(),
                Text(value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        color: Color(0xff1d3329),
                        fontWeight: FontWeight.w900,
                        fontSize: 17)),
                const SizedBox(height: 2),
                Text(label, style: const TextStyle(color: Color(0xff708077)))
              ])));
}

class HouseholdScreen extends ConsumerStatefulWidget {
  const HouseholdScreen({super.key});
  @override
  ConsumerState<HouseholdScreen> createState() => _HouseholdsState();
}

class _HouseholdsState extends ConsumerState<HouseholdScreen> {
  int? route;
  String search = '';
  Timer? timer;
  Future<dynamic> load() =>
      ref.read(apiProvider).get('/mobile/households', query: {
        if (route != null) 'route_id': route,
        'search': search,
        'per_page': 30
      });
  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Ghi thu tiền')),
      body: Column(children: [
        Padding(
            padding: const EdgeInsets.all(12),
            child: Column(children: [
              ref.watch(routesProvider).when(
                    data: (data) => DropdownButtonFormField<int>(
                        initialValue: route,
                        decoration:
                            const InputDecoration(labelText: 'Tuyến thu'),
                        items: [
                          for (final item in data)
                            DropdownMenuItem(
                                value: item['id'] as int,
                                child: Text('${item['name']}'))
                        ],
                        onChanged: (value) => setState(() => route = value)),
                    loading: () => const LinearProgressIndicator(),
                    error: (error, stackTrace) => Row(children: [
                      const Expanded(child: Text('Không tải được tuyến')),
                      TextButton.icon(
                          onPressed: () => ref.invalidate(routesProvider),
                          icon: const Icon(Icons.refresh),
                          label: const Text('Thử lại')),
                    ]),
                  ),
              const SizedBox(height: 10),
              TextField(
                  decoration: const InputDecoration(
                      labelText: 'Tìm tên, mã hộ, SĐT, địa chỉ',
                      prefixIcon: Icon(Icons.search)),
                  onChanged: (value) {
                    timer?.cancel();
                    timer = Timer(const Duration(milliseconds: 450),
                        () => setState(() => search = value));
                  }),
            ])),
        Expanded(
            child: FutureBuilder<dynamic>(
                future: load(),
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return const Center(
                        child: Text('Không tải được danh sách hộ.'));
                  }
                  final list = (snapshot.data?['data'] as List?) ?? [];
                  if (list.isEmpty) {
                    return const Center(child: Text('Không tìm thấy hộ dân.'));
                  }
                  return ListView.builder(
                      itemCount: list.length,
                      itemBuilder: (context, index) {
                        final household = list[index];
                        return Card(
                            margin: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 6),
                            child: ListTile(
                                contentPadding:
                                    const EdgeInsets.fromLTRB(14, 9, 10, 9),
                                leading: Container(
                                    width: 44,
                                    height: 44,
                                    decoration: BoxDecoration(
                                        color: const Color(0xffe2f1e8),
                                        borderRadius:
                                            BorderRadius.circular(13)),
                                    child: const Icon(Icons.home_work_outlined,
                                        color: Color(0xff176b45))),
                                title: Text('${household['owner_name']}',
                                    style: const TextStyle(
                                        color: Color(0xff1c352a),
                                        fontWeight: FontWeight.w800)),
                                subtitle: Text(
                                    '${household['code']} · ${household['address']}\n${household['route']?['name'] ?? 'Chưa có tuyến'} · ${latestPaymentLabel(household['latest_payment'])}'),
                                isThreeLine: true,
                                trailing: FilledButton.icon(
                                    style: FilledButton.styleFrom(
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 11, vertical: 9)),
                                    onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CollectScreen(household))),
                                    icon: const Icon(Icons.payments_outlined, size: 17),
                                    label: const Text('Thu')),
                                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => HouseholdDetailScreen(household['id'] as int)))));
                      });
                })),
      ]));
}

class HouseholdDetailScreen extends ConsumerWidget {
  const HouseholdDetailScreen(this.id, {super.key});
  final int id;
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
      appBar: AppBar(title: const Text('Chi tiết hộ dân')),
      body: FutureBuilder<dynamic>(
          future: ref.read(apiProvider).get('/mobile/households/$id'),
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return const Center(
                  child: Text('Không tải được thông tin hộ dân.'));
            }
            final item = snapshot.data;
            final services = (item['services'] as List?) ?? [];
            final payments = (item['payments'] as List?) ?? [];
            return ListView(padding: const EdgeInsets.all(16), children: [
              Text('${item['owner_name']}',
                  style: Theme.of(context)
                      .textTheme
                      .headlineSmall
                      ?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 6),
              Text(
                  '${item['code']} · ${item['route']?['name'] ?? 'Chưa có tuyến'}'),
              Text('${item['address']}'),
              if (item['phone'] != null) Text('${item['phone']}'),
              const SizedBox(height: 18),
              SizedBox(
                  height: 52,
                  child: FilledButton.icon(
                      onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (_) => CollectScreen(item))),
                      icon: const Icon(Icons.payments_outlined),
                      label: const Text('THU TIỀN'))),
              const SizedBox(height: 22),
              const Text('Dịch vụ đang sử dụng',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
              for (final subscription in services)
                Card(
                    child: ListTile(
                        leading: const Icon(Icons.recycling),
                        title: Text('${subscription['service']?['name']}'),
                        subtitle: Text(
                            'Đơn giá ${money(subscription['service']?['monthly_price'])}/tháng · Thuế, phí ${subscription['service']?['tax_fee'] ?? 0}%'))),
              const SizedBox(height: 18),
              const Text('Lịch sử thanh toán gần nhất',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
              if (payments.isEmpty)
                const Padding(
                    padding: EdgeInsets.all(16),
                    child: Text('Chưa có lịch sử thanh toán.')),
              for (final payment in payments)
                Card(
                    child: ListTile(
                        title: Text(
                            '${month(payment['from_month'])} – ${month(payment['to_month'])}'),
                        subtitle: Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                      '${payment['code']} · ${dateTime(payment['paid_at'])}'),
                                  const SizedBox(height: 6),
                                  InvoiceStatusChip(
                                      invoice: payment['invoice']),
                                ])),
                        isThreeLine: true,
                        trailing: Text(money(payment['amount'])),
                        onTap: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                                builder: (_) => TransactionDetailScreen(
                                    payment['id'] as int))))),
            ]);
          }));
}

class InvoiceStatusChip extends StatelessWidget {
  const InvoiceStatusChip({required this.invoice, super.key});
  final dynamic invoice;

  @override
  Widget build(BuildContext context) {
    final status = '${invoice?['status'] ?? 'CHO_PHAT_HANH'}';
    final issued = status == 'DA_PHAT_HANH';
    final failed = status == 'PHAT_HANH_LOI';
    final color = issued
        ? Colors.green
        : failed
            ? Colors.red
            : Colors.orange;
    final number = invoice?['invoice_no'];
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
          color: color.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(
            issued
                ? Icons.verified_outlined
                : failed
                    ? Icons.error_outline
                    : Icons.schedule,
            size: 16,
            color: color),
        const SizedBox(width: 5),
        Flexible(
            child: Text(
                number == null
                    ? invoiceStatus(status)
                    : '${invoiceStatus(status)} · Số $number',
                style: TextStyle(
                    color: color, fontSize: 12, fontWeight: FontWeight.w700))),
      ]),
    );
  }
}

class CollectScreen extends ConsumerStatefulWidget {
  const CollectScreen(this.household, {super.key});
  final dynamic household;
  @override
  ConsumerState<CollectScreen> createState() => _CollectState();
}

class _CollectState extends ConsumerState<CollectScreen> {
  String from = '', to = '';
  int count = 1;
  dynamic preview;
  dynamic latestPayment;
  bool busy = false;
  String paymentMethod = 'TIEN_MAT';
  final note = TextEditingController();
  @override
  void initState() {
    super.initState();
    suggest();
  }

  @override
  void dispose() {
    note.dispose();
    super.dispose();
  }

  Future<void> suggest() async {
    final data = await ref
        .read(apiProvider)
        .get('/mobile/households/${widget.household['id']}/payment-suggestion');
    if (!mounted) return;
    setState(() {
      from = data['next_month'];
      to = from;
      latestPayment = data['latest_payment'];
    });
    await calculate();
  }

  String addMonths(String value, int number) {
    final parts = value.split('-');
    final date =
        DateTime(int.parse(parts[0]), int.parse(parts[1]) + number - 1);
    return '${date.year}-${date.month.toString().padLeft(2, '0')}';
  }

  Future<void> pickMonth(bool start) async {
    final source = start ? from : to;
    var year = int.tryParse(source.split('-').first) ?? DateTime.now().year;
    final selected = await showDialog<String>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
            builder: (context, setDialogState) => AlertDialog(
                  title: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        IconButton(
                            onPressed: () => setDialogState(() => year--),
                            icon: const Icon(Icons.chevron_left)),
                        Text('$year'),
                        IconButton(
                            onPressed: () => setDialogState(() => year++),
                            icon: const Icon(Icons.chevron_right))
                      ]),
                  content: SizedBox(
                      width: 320,
                      child: GridView.count(
                          shrinkWrap: true,
                          crossAxisCount: 3,
                          childAspectRatio: 1.7,
                          children: [
                            for (var value = 1; value <= 12; value++)
                              TextButton(
                                  onPressed: () => Navigator.pop(dialogContext,
                                      '$year-${value.toString().padLeft(2, '0')}'),
                                  child: Text('Tháng $value'))
                          ])),
                )));
    if (selected == null) return;
    setState(() {
      if (start) {
        from = selected;
        to = addMonths(from, count);
      } else {
        if (selected.compareTo(from) < 0) return;
        to = selected;
        count = monthDifference(from, to);
      }
    });
    await calculate();
  }

  Future<void> calculate() async {
    if (from.isEmpty) return;
    try {
      final data = await ref.read(apiProvider).post('/mobile/payments/preview',
          data: {
            'household_id': widget.household['id'],
            'from_month': from,
            'to_month': to
          });
      if (mounted) setState(() => preview = data);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Khoảng tháng đã thu hoặc không hợp lệ.')));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text('${widget.household['owner_name']}')),
        bottomNavigationBar: SafeArea(
            child: Padding(
                padding: const EdgeInsets.all(12),
                child: SizedBox(
                    height: 54,
                    child: FilledButton(
                        onPressed: busy || preview == null ? null : collect,
                        child: busy
                            ? const CircularProgressIndicator()
                            : Text(
                                'XÁC NHẬN THU ${preview == null ? '' : money(preview['total'])}'))))),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          Text('${widget.household['address']}'),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
                color: Colors.blue.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12)),
            child: Row(children: [
              const Icon(Icons.history, color: Colors.blue),
              const SizedBox(width: 10),
              Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                    const Text('Kỳ thu gần nhất',
                        style: TextStyle(fontSize: 12, color: Colors.grey)),
                    Text(
                        latestPayment == null
                            ? 'Chưa có lịch sử thu'
                            : '${month(latestPayment['from_month'])} – ${month(latestPayment['to_month'])}',
                        style: const TextStyle(fontWeight: FontWeight.bold)),
                  ])),
            ]),
          ),
          const SizedBox(height: 18),
          OutlinedButton.icon(
              onPressed: from.isEmpty ? null : () => pickMonth(true),
              icon: const Icon(Icons.calendar_month),
              label: Text('Từ tháng: ${month(from)}')),
          const SizedBox(height: 10),
          Wrap(
              spacing: 8,
              children: [1, 3, 6]
                  .map((number) => ChoiceChip(
                      label: Text('$number tháng'),
                      selected: count == number,
                      onSelected: (_) {
                        setState(() {
                          count = number;
                          to = addMonths(from, number);
                        });
                        calculate();
                      }))
                  .toList()),
          const SizedBox(height: 10),
          OutlinedButton.icon(
              onPressed: to.isEmpty ? null : () => pickMonth(false),
              icon: const Icon(Icons.event_available),
              label: Text('Đến tháng: ${month(to)}')),
          const Divider(height: 32),
          if (preview != null) ...[
            Text('Tiền dịch vụ: ${money(preview['subtotal'])}'),
            Text('Thuế, phí: ${money(preview['tax_fee'])}'),
            const SizedBox(height: 8),
            Text('Tổng thanh toán: ${money(preview['total'])}',
                style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: Color(0xff16a34a))),
            const SizedBox(height: 12),
            Card(
                margin: EdgeInsets.zero,
                color: const Color(0xfff4f8f5),
                child: ExpansionTile(
                    leading: const Icon(Icons.fact_check_outlined,
                        color: Color(0xff16805a)),
                    title: const Text('Chi tiết áp giá',
                        style: TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: const Text('Giá, thuế và văn bản theo từng tháng'),
                    children: [
                      for (final line in (preview['items'] as List? ?? []))
                        ListTile(
                            dense: true,
                            title: Text(
                                'Tháng ${month('${line['month']}')} · ${money(line['total'])}'),
                            subtitle: Text(
                                '${money(line['price'])} + ${line['tax_fee_rate'] ?? 0}% thuế\nVăn bản: ${line['document_number'] ?? '—'}${line['document_name'] == null ? '' : ' · ${line['document_name']}'}'))
                    ]))
          ],
          const SizedBox(height: 22),
          DropdownButtonFormField<String>(
            initialValue: paymentMethod,
            decoration:
                const InputDecoration(labelText: 'Hình thức thanh toán'),
            items: const [
              DropdownMenuItem(value: 'TIEN_MAT', child: Text('Tiền mặt')),
              DropdownMenuItem(
                  value: 'CHUYEN_KHOAN', child: Text('Chuyển khoản')),
              DropdownMenuItem(value: 'KHAC', child: Text('Khác')),
            ],
            onChanged: (value) =>
                setState(() => paymentMethod = value ?? 'TIEN_MAT'),
          ),
          const SizedBox(height: 12),
          TextField(
              controller: note,
              maxLines: 2,
              decoration: const InputDecoration(labelText: 'Ghi chú')),
        ]),
      );
  Future<void> collect() async {
    final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
              title: const Text('Xác nhận thu tiền'),
              content: Text(
                  '${widget.household['owner_name']}\n${widget.household['address']}\n\nKỳ thu: ${month(from)} – ${month(to)} ($count tháng)\nTổng tiền: ${money(preview['total'])}\nHình thức: ${paymentMethodLabel(paymentMethod)}'),
              actions: [
                TextButton(
                    onPressed: () => Navigator.pop(dialogContext, false),
                    child: const Text('Kiểm tra lại')),
                FilledButton(
                    onPressed: () => Navigator.pop(dialogContext, true),
                    child: const Text('Xác nhận'))
              ],
            ));
    if (confirmed != true) return;
    setState(() => busy = true);
    try {
      final data = await ref.read(apiProvider).post('/mobile/payments', data: {
        'household_id': widget.household['id'],
        'from_month': from,
        'to_month': to,
        'payment_method': paymentMethod,
        'note': note.text.trim().isEmpty ? null : note.text.trim(),
      });
      if (mounted) {
        showDialog<void>(
            context: context,
            builder: (dialogContext) => AlertDialog(
                    title: const Text('Thu tiền thành công'),
                    content: Text(
                        'Số phiếu: ${data['code']}\nTổng tiền: ${money(data['amount'])}'),
                    actions: [
                      FilledButton.icon(
                          onPressed: () => issueInvoice(data),
                          icon: const Icon(Icons.receipt_long),
                          label: const Text('PHÁT HÀNH HÓA ĐƠN')),
                      TextButton.icon(
                          onPressed: () =>
                              printPosReceipt(dialogContext, ref, data['id']),
                          icon: const Icon(Icons.print),
                          label: const Text('In POS 58')),
                      FilledButton(
                          onPressed: () => Navigator.popUntil(
                              dialogContext, (route) => route.isFirst),
                          child: const Text('Về danh sách'))
                    ]));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content:
                Text('Không thể ghi thu. Vui lòng kiểm tra mạng và thử lại.')));
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> issueInvoice(dynamic payment) async {
    try {
      await ref
          .read(apiProvider)
          .post('/mobile/payments/${payment['id']}/issue-invoice');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Phát hành hóa đơn thành công.')));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text(
                'Phát hành hóa đơn chưa thành công. Có thể thử lại trong Giao dịch.')));
      }
    }
  }
}

class TransactionScreen extends ConsumerWidget {
  const TransactionScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => SimpleList(
      title: 'Giao dịch',
      future: ref.read(apiProvider).get('/mobile/payments'),
      builder: (item) => ListTile(
          title: Text('${item['household']?['owner_name'] ?? ''}'),
          subtitle: Text(
              '${month(item['from_month'])} – ${month(item['to_month'])}\nHóa đơn: ${invoiceStatus('${item['invoice']?['status'] ?? 'CHO_PHAT_HANH'}')}'),
          isThreeLine: true,
          trailing: Text(money(item['amount'])),
          onTap: () => Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (_) =>
                      TransactionDetailScreen(item['id'] as int)))));
}

class AnnouncementScreen extends ConsumerWidget {
  const AnnouncementScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => SimpleList(
      title: 'Thông báo điều hành',
      future: ref.read(apiProvider).get('/directives/inbox'),
      builder: (item) => ListTile(
          leading: Icon(item['recipients']?[0]?['pivot']?['read_at'] == null
              ? Icons.mark_email_unread
              : Icons.drafts),
          title: Text('${item['title']}'),
          subtitle: Text('${item['creator']?['name'] ?? ''}'),
          onTap: () => Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (_) =>
                      AnnouncementDetailScreen(item['id'] as int)))));
}

class TransactionDetailScreen extends ConsumerStatefulWidget {
  const TransactionDetailScreen(this.id, {super.key});
  final int id;
  @override
  ConsumerState<TransactionDetailScreen> createState() =>
      _TransactionDetailState();
}

class _TransactionDetailState extends ConsumerState<TransactionDetailScreen> {
  bool issuing = false;
  late Future<dynamic> future = load();
  Future<dynamic> load() =>
      ref.read(apiProvider).get('/mobile/payments/${widget.id}');
  Future<void> issue() async {
    setState(() => issuing = true);
    try {
      await ref
          .read(apiProvider)
          .post('/mobile/payments/${widget.id}/issue-invoice');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Đã phát hành hóa đơn.')));
        setState(() => future = load());
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Không phát hành được hóa đơn. Vui lòng thử lại.')));
      }
    } finally {
      if (mounted) setState(() => issuing = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Chi tiết giao dịch')),
      body: FutureBuilder<dynamic>(
          future: future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return const Center(child: Text('Không tải được giao dịch.'));
            }
            final item = snapshot.data;
            final invoice = item['invoice'];
            final months = (item['months'] as List?) ?? [];
            final issued = invoice?['status'] == 'DA_PHAT_HANH';
            return ListView(padding: const EdgeInsets.all(16), children: [
              Text('${item['household']?['owner_name']}',
                  style: Theme.of(context)
                      .textTheme
                      .headlineSmall
                      ?.copyWith(fontWeight: FontWeight.bold)),
              Text('${item['household']?['address']}'),
              const Divider(height: 30),
              DetailRow('Số phiếu', '${item['code']}'),
              DetailRow('Kỳ thu',
                  '${month(item['from_month'])} – ${month(item['to_month'])}'),
              DetailRow('Ngày thu', dateTime(item['paid_at'])),
              DetailRow('Số tiền', money(item['amount'])),
              DetailRow(
                  'Hình thức', paymentMethodLabel('${item['payment_method']}')),
              DetailRow('Hóa đơn',
                  invoiceStatus('${invoice?['status'] ?? 'CHO_PHAT_HANH'}')),
              if (invoice?['invoice_no'] != null)
                DetailRow('Số hóa đơn', '${invoice['invoice_no']}'),
              if (months.isNotEmpty) ...[
                const SizedBox(height: 12),
                const Text('Chi tiết tính tiền',
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
                const SizedBox(height: 6),
                for (final line in months)
                  Card(
                      child: ListTile(
                          dense: true,
                          title: Text(
                              'Tháng ${month('${line['month']}')} · ${money(line['amount'])}'),
                          subtitle: Text(
                              'Giá ${money(line['base_price'])} · Thuế ${line['tax_fee_rate'] ?? 0}% (${money(line['tax_fee_amount'])})\nVăn bản: ${line['document_number'] ?? line['price_period']?['document_number'] ?? '—'}'))),
              ],
              const SizedBox(height: 18),
              FilledButton.icon(
                  onPressed: () => printPosReceipt(context, ref, widget.id),
                  icon: const Icon(Icons.print),
                  label: const Text('In phiếu POS 58')),
              const SizedBox(height: 10),
              if (!issued)
                FilledButton.tonalIcon(
                    onPressed: issuing ? null : issue,
                    icon: issuing
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.cloud_upload_outlined),
                    label: Text(invoice?['status'] == 'PHAT_HANH_LOI'
                        ? 'Phát hành lại hóa đơn'
                        : 'Phát hành hóa đơn')),
              if (issued)
                FilledButton.tonalIcon(
                    onPressed: () => downloadAndOpen(
                        ref,
                        '/mobile/payments/${widget.id}/invoice',
                        'hoa-don-${item['code']}.pdf'),
                    icon: const Icon(Icons.picture_as_pdf_outlined),
                    label: const Text('Mở hóa đơn VNPT')),
            ]);
          }));
}

class AnnouncementDetailScreen extends ConsumerWidget {
  const AnnouncementDetailScreen(this.id, {super.key});
  final int id;
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
      appBar: AppBar(title: const Text('Thông tin điều hành')),
      body: FutureBuilder<dynamic>(
          future: ref.read(apiProvider).get('/directives/$id'),
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return const Center(child: Text('Không tải được thông tin.'));
            }
            final item = snapshot.data;
            return ListView(padding: const EdgeInsets.all(18), children: [
              Text('${item['title']}',
                  style: Theme.of(context)
                      .textTheme
                      .headlineSmall
                      ?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              Text(
                  '${item['creator']?['name'] ?? ''} · ${dateTime(item['created_at'])}',
                  style: const TextStyle(color: Colors.grey)),
              const Divider(height: 30),
              Text(stripHtml('${item['content']}'),
                  style: const TextStyle(fontSize: 16, height: 1.5)),
              if ((item['attachments'] as List?)?.isNotEmpty == true) ...[
                const SizedBox(height: 24),
                const Text('File đính kèm',
                    style: TextStyle(fontWeight: FontWeight.bold)),
                for (final file in item['attachments'])
                  ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.attach_file),
                      title: Text('${file['original_name']}'),
                      onTap: () => downloadAndOpen(
                          ref,
                          '/directives/attachments/${file['id']}',
                          '${file['original_name']}'))
              ]
            ]);
          }));
}

class DetailRow extends StatelessWidget {
  const DetailRow(this.label, this.value, {super.key});
  final String label, value;
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(
            width: 110,
            child: Text(label, style: const TextStyle(color: Colors.grey))),
        Expanded(
            child: Text(value,
                style: const TextStyle(fontWeight: FontWeight.w600)))
      ]));
}

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    final user = auth.user;
    final name = '${user?['name'] ?? ''}';
    return Scaffold(
        appBar: AppBar(title: const Text('Cá nhân')),
        body: ListView(children: [
          const SizedBox(height: 30),
          CircleAvatar(
              radius: 42,
              child: Text(name.isEmpty ? '?' : name[0],
                  style: const TextStyle(fontSize: 30))),
          const SizedBox(height: 12),
          Center(
              child: Text(name,
                  style: const TextStyle(
                      fontSize: 20, fontWeight: FontWeight.bold))),
          ListTile(
              leading: const Icon(Icons.phone, color: Color(0xff16805a)),
              title: Text('${user?['phone'] ?? 'Chưa cập nhật'}')),
          SwitchListTile(
              secondary: Icon(Icons.fingerprint,
                  color: auth.biometricAvailable
                      ? const Color(0xffd28716)
                      : Colors.grey,
                  size: 28),
              title: const Text('Đăng nhập bằng vân tay'),
              subtitle: Text(!auth.biometricAvailable
                  ? 'Thiết bị chưa có hoặc chưa cài đặt vân tay'
                  : auth.biometricEnabled
                      ? 'Đang bật trên thiết bị này'
                      : 'Dùng vân tay thay cho mật khẩu'),
              activeThumbColor: const Color(0xff176b45),
              value: auth.biometricEnabled,
              onChanged: !auth.biometricAvailable
                  ? null
                  : (value) async {
                      final success = await ref
                          .read(authProvider.notifier)
                          .setBiometricEnabled(value);
                      if (!success && context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                            content: Text(
                                'Không xác thực được vân tay. Hãy kiểm tra cài đặt thiết bị.')));
                      }
                    }),
          ListTile(
              leading: const Icon(Icons.print, color: Color(0xff7357b5)),
              title: const Text('Máy in Bluetooth'),
              subtitle: Text(PosPrinter.instance.connected
                  ? 'Đã kết nối ${PosPrinter.instance.deviceName ?? ''}'
                  : PosPrinter.instance.deviceName ?? 'Chưa cấu hình'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context,
                  MaterialPageRoute(builder: (_) => const PrinterScreen()))),
          ListTile(
              leading: const Icon(Icons.contact_phone_outlined,
                  color: Color(0xff2474b5)),
              title: const Text('Danh bạ đơn vị'),
              subtitle: const Text('Tra cứu cán bộ, nhân viên'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context,
                  MaterialPageRoute(builder: (_) => const DirectoryScreen()))),
          ListTile(
              leading: const Icon(Icons.folder_copy_outlined,
                  color: Color(0xffdd7f18)),
              title: const Text('Văn bản, tài liệu'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context,
                  MaterialPageRoute(builder: (_) => const DocumentsScreen()))),
          ListTile(
              leading:
                  const Icon(Icons.password_outlined, color: Color(0xff168b82)),
              title: const Text('Đổi mật khẩu'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                      builder: (_) => const ChangePasswordScreen()))),
          ListTile(
              leading: const Icon(Icons.logout, color: Colors.red),
              title: const Text('Đăng xuất'),
              onTap: () => ref.read(authProvider.notifier).logout())
        ]));
  }
}

class DirectoryScreen extends ConsumerStatefulWidget {
  const DirectoryScreen({super.key});
  @override
  ConsumerState<DirectoryScreen> createState() => _DirectoryScreenState();
}

class _DirectoryScreenState extends ConsumerState<DirectoryScreen> {
  String search = '';
  Timer? timer;

  Future<dynamic> load() => ref.read(apiProvider).get('/mobile/contacts',
      query: {if (search.isNotEmpty) 'search': search});

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  Future<void> open(String scheme, dynamic value) async {
    final text = '${value ?? ''}'.trim();
    if (text.isEmpty) return;
    final uri = Uri.parse(
        '$scheme:${scheme == 'tel' ? text.replaceAll(' ', '') : text}');
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Không mở được ứng dụng phù hợp.')));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Danh bạ đơn vị')),
      body: Column(children: [
        Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 14),
            child: TextField(
                decoration: const InputDecoration(
                    hintText: 'Tìm tên, chức vụ, SĐT, email...',
                    prefixIcon: Icon(Icons.search),
                    suffixIcon: Icon(Icons.manage_search)),
                onChanged: (value) {
                  timer?.cancel();
                  timer = Timer(const Duration(milliseconds: 350),
                      () => setState(() => search = value.trim()));
                })),
        Expanded(
            child: FutureBuilder<dynamic>(
                future: load(),
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return const Center(
                        child: Text('Không tải được danh bạ đơn vị.'));
                  }
                  final contacts = (snapshot.data as List?) ?? [];
                  if (contacts.isEmpty) {
                    return const Center(
                        child:
                            Column(mainAxisSize: MainAxisSize.min, children: [
                      Icon(Icons.person_search_outlined,
                          size: 58, color: Colors.grey),
                      SizedBox(height: 8),
                      Text('Không tìm thấy người dùng phù hợp.')
                    ]));
                  }
                  return ListView.builder(
                      padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
                      itemCount: contacts.length,
                      itemBuilder: (context, index) {
                        final item = contacts[index];
                        final avatar = item['avatar_url'];
                        return Card(
                            child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Column(children: [
                                  Row(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        CircleAvatar(
                                            radius: 28,
                                            backgroundColor:
                                                const Color(0xffdcefe4),
                                            backgroundImage: avatar == null
                                                ? null
                                                : NetworkImage('$avatar'),
                                            child: avatar == null
                                                ? Text(
                                                    '${item['name'] ?? '?'}'[0]
                                                        .toUpperCase(),
                                                    style: const TextStyle(
                                                        color:
                                                            Color(0xff176b45),
                                                        fontSize: 21,
                                                        fontWeight:
                                                            FontWeight.w900))
                                                : null),
                                        const SizedBox(width: 13),
                                        Expanded(
                                            child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                              Text('${item['name']}',
                                                  style: const TextStyle(
                                                      color: Color(0xff19372a),
                                                      fontSize: 17,
                                                      fontWeight:
                                                          FontWeight.w900)),
                                              const SizedBox(height: 3),
                                              Text(
                                                  '${item['position']?.toString().isNotEmpty == true ? item['position'] : 'Chưa cập nhật chức vụ'}${item['age'] == null ? '' : ' · ${item['age']} tuổi'}',
                                                  style: const TextStyle(
                                                      color: Color(0xff527164),
                                                      fontWeight:
                                                          FontWeight.w600)),
                                            ])),
                                      ]),
                                  const Divider(height: 24),
                                  _ContactLine(Icons.phone_outlined,
                                      'Điện thoại', item['phone']),
                                  _ContactLine(Icons.email_outlined, 'Email',
                                      item['email']),
                                  _ContactLine(Icons.location_on_outlined,
                                      'Địa chỉ', item['address']),
                                  const SizedBox(height: 8),
                                  Row(children: [
                                    Expanded(
                                        child: OutlinedButton.icon(
                                            onPressed: item['phone'] == null
                                                ? null
                                                : () =>
                                                    open('tel', item['phone']),
                                            icon: const Icon(Icons.call),
                                            label: const Text('Gọi điện'))),
                                    const SizedBox(width: 9),
                                    Expanded(
                                        child: OutlinedButton.icon(
                                            onPressed: item['email'] == null
                                                ? null
                                                : () => open(
                                                    'mailto', item['email']),
                                            icon:
                                                const Icon(Icons.mail_outline),
                                            label: const Text('Gửi email')))
                                  ])
                                ])));
                      });
                }))
      ]));
}

class _ContactLine extends StatelessWidget {
  const _ContactLine(this.icon, this.label, this.value);
  final IconData icon;
  final String label;
  final dynamic value;
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 19, color: const Color(0xff488168)),
        const SizedBox(width: 9),
        SizedBox(
            width: 74,
            child:
                Text(label, style: const TextStyle(color: Color(0xff75867d)))),
        Expanded(
            child: Text('${value?.toString().isNotEmpty == true ? value : '—'}',
                style: const TextStyle(fontWeight: FontWeight.w600)))
      ]));
}

class DocumentsScreen extends ConsumerStatefulWidget {
  const DocumentsScreen({super.key});
  @override
  ConsumerState<DocumentsScreen> createState() => _DocumentsState();
}

class _DocumentsState extends ConsumerState<DocumentsScreen> {
  String search = '';
  Timer? timer;
  Future<dynamic> load() => ref
      .read(apiProvider)
      .get('/documents/', query: {'search': search, 'per_page': 30});
  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Văn bản, tài liệu')),
      body: Column(children: [
        Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
                decoration: const InputDecoration(
                    labelText: 'Tìm tài liệu', prefixIcon: Icon(Icons.search)),
                onChanged: (value) {
                  timer?.cancel();
                  timer = Timer(const Duration(milliseconds: 450),
                      () => setState(() => search = value));
                })),
        Expanded(
            child: FutureBuilder<dynamic>(
                future: load(),
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return const Center(
                        child: Text('Không tải được tài liệu.'));
                  }
                  final list = (snapshot.data?['data'] as List?) ?? [];
                  if (list.isEmpty) {
                    return const Center(child: Text('Chưa có tài liệu.'));
                  }
                  return ListView.builder(
                      itemCount: list.length,
                      itemBuilder: (context, index) {
                        final item = list[index];
                        return Card(
                            margin: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 5),
                            child: ListTile(
                                leading:
                                    Icon(documentIcon('${item['extension']}')),
                                title: Text('${item['name']}'),
                                subtitle: Text(
                                    '${item['category']?['name'] ?? ''} · ${fileSize(item['file_size'])}'),
                                trailing: const Icon(Icons.download_outlined),
                                onTap: () => downloadAndOpen(
                                    ref,
                                    '/documents/${item['id']}/download',
                                    '${item['original_name']}')));
                      });
                }))
      ]));
}

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordState();
}

class _ChangePasswordState extends ConsumerState<ChangePasswordScreen> {
  final current = TextEditingController(),
      password = TextEditingController(),
      confirmation = TextEditingController();
  bool busy = false, hidden = true;
  @override
  void dispose() {
    current.dispose();
    password.dispose();
    confirmation.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (password.text.length < 8 || password.text != confirmation.text) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content:
              Text('Mật khẩu mới tối thiểu 8 ký tự và xác nhận phải khớp.')));
      return;
    }
    setState(() => busy = true);
    try {
      await ref.read(apiProvider).post('/auth/change-password', data: {
        'current_password': current.text,
        'password': password.text,
        'password_confirmation': confirmation.text
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Đổi mật khẩu thành công.')));
        Navigator.pop(context);
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text(
                'Mật khẩu hiện tại không đúng hoặc dữ liệu chưa hợp lệ.')));
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Đổi mật khẩu')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        TextField(
            controller: current,
            obscureText: hidden,
            decoration: const InputDecoration(labelText: 'Mật khẩu hiện tại')),
        const SizedBox(height: 12),
        TextField(
            controller: password,
            obscureText: hidden,
            decoration: const InputDecoration(labelText: 'Mật khẩu mới')),
        const SizedBox(height: 12),
        TextField(
            controller: confirmation,
            obscureText: hidden,
            decoration:
                const InputDecoration(labelText: 'Nhập lại mật khẩu mới')),
        SwitchListTile(
            value: !hidden,
            onChanged: (value) => setState(() => hidden = !value),
            title: const Text('Hiện mật khẩu')),
        const SizedBox(height: 12),
        SizedBox(
            height: 52,
            child: FilledButton(
                onPressed: busy ? null : save,
                child: busy
                    ? const CircularProgressIndicator()
                    : const Text('XÁC NHẬN ĐỔI MẬT KHẨU')))
      ]));
}

class SimpleList extends StatelessWidget {
  const SimpleList(
      {required this.title,
      required this.future,
      required this.builder,
      super.key});
  final String title;
  final Future<dynamic> future;
  final Widget Function(dynamic) builder;
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: Text(title)),
      body: FutureBuilder<dynamic>(
          future: future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return const Center(child: Text('Không tải được dữ liệu.'));
            }
            final list = (snapshot.data?['data'] as List?) ?? [];
            if (list.isEmpty) {
              return const Center(child: Text('Chưa có dữ liệu.'));
            }
            return ListView.builder(
                itemCount: list.length,
                itemBuilder: (context, index) => Card(
                    margin: const EdgeInsets.all(6),
                    child: builder(list[index])));
          }));
}

String money(dynamic value) =>
    NumberFormat.currency(locale: 'vi_VN', symbol: '₫', decimalDigits: 0)
        .format(num.tryParse('$value') ?? 0);
String month(dynamic value) {
  final text = '$value';
  if (RegExp(r'^\d{4}-\d{2}$').hasMatch(text)) {
    return '${text.substring(5, 7)}/${text.substring(0, 4)}';
  }

  // Laravel serializes a date-only month at midnight in the application
  // timezone. In JSON that can become 17:00 on the previous UTC date, so
  // reading the first seven characters displays the preceding month.
  final parsed = DateTime.tryParse(text)?.toLocal();
  if (parsed == null) return '—';
  return '${parsed.month.toString().padLeft(2, '0')}/${parsed.year}';
}

String latestPaymentLabel(dynamic payment) =>
    payment == null ? 'Chưa thu' : 'Đã thu đến ${month(payment['to_month'])}';

int monthDifference(String from, String to) {
  final a = from.split('-').map(int.parse).toList(),
      b = to.split('-').map(int.parse).toList();
  return (b[0] - a[0]) * 12 + b[1] - a[1] + 1;
}

String paymentMethodLabel(String value) =>
    {
      'TIEN_MAT': 'Tiền mặt',
      'CHUYEN_KHOAN': 'Chuyển khoản',
      'KHAC': 'Khác'
    }[value] ??
    value;
String invoiceStatus(String value) =>
    {
      'CHO_PHAT_HANH': 'Chờ phát hành',
      'DANG_PHAT_HANH': 'Đang phát hành',
      'DA_PHAT_HANH': 'Đã phát hành',
      'PHAT_HANH_LOI': 'Phát hành lỗi'
    }[value] ??
    value;
String dateTime(dynamic value) {
  final parsed = DateTime.tryParse('$value')?.toLocal();
  return parsed == null ? '—' : DateFormat('dd/MM/yyyy HH:mm').format(parsed);
}

String stripHtml(String value) => value
    .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
    .replaceAll(RegExp(r'<[^>]+>'), '')
    .replaceAll('&nbsp;', ' ')
    .replaceAll('&amp;', '&');

Future<void> printPosReceipt(
    BuildContext context, WidgetRef ref, dynamic paymentId) async {
  final printer = PosPrinter.instance;
  var ready = printer.connected;
  if (!ready && printer.deviceAddress != null) {
    ready = await printer.reconnectSaved();
  }
  if (!ready && context.mounted) {
    final openSettings = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
              title: const Text('Chưa kết nối máy in'),
              content: const Text(
                  'Bạn cần kết nối máy in Bluetooth POS 58 trước khi in phiếu.'),
              actions: [
                TextButton(
                    onPressed: () => Navigator.pop(dialogContext, false),
                    child: const Text('Để sau')),
                FilledButton.icon(
                    onPressed: () => Navigator.pop(dialogContext, true),
                    icon: const Icon(Icons.bluetooth_searching),
                    label: const Text('Kết nối máy in'))
              ],
            ));
    if (openSettings == true && context.mounted) {
      ready = await Navigator.push<bool>(context,
              MaterialPageRoute(builder: (_) => const PrinterScreen())) ==
          true;
    }
  }
  if (!ready || !context.mounted) return;
  try {
    final api = ref.read(apiProvider);
    final raw = await api.get('/mobile/payments/$paymentId/print-data');
    final data = Map<String, dynamic>.from(raw as Map);
    Uint8List? qr;
    final qrUrl = data['payment_qr_url'];
    if (qrUrl != null) {
      final response = await api.dio.get<List<int>>('$qrUrl',
          options: Options(responseType: ResponseType.bytes));
      if (response.data != null) qr = Uint8List.fromList(response.data!);
    }
    await printer.printReceipt(data, paymentQr: qr);
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã gửi phiếu tới máy in POS 58.')));
    }
  } catch (e) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text(
              'Không in được phiếu. Kiểm tra kết nối máy in và thử lại.')));
    }
  }
}

Future<void> downloadAndOpen(
    WidgetRef ref, String endpoint, String fileName) async {
  final directory = await getTemporaryDirectory();
  final safeName = fileName.replaceAll(RegExp(r'[\\/:*?"<>|]'), '-');
  final path = '${directory.path}/$safeName';
  await ref.read(apiProvider).download(endpoint, path);
  await OpenFilex.open(path);
}

IconData documentIcon(String extension) {
  if (extension == 'pdf') return Icons.picture_as_pdf_outlined;
  if (['xls', 'xlsx'].contains(extension)) return Icons.table_chart_outlined;
  if (['jpg', 'jpeg', 'png', 'webp'].contains(extension)) {
    return Icons.image_outlined;
  }
  return Icons.description_outlined;
}

String fileSize(dynamic value) {
  final bytes = int.tryParse('$value') ?? 0;
  if (bytes >= 1048576) return '${(bytes / 1048576).toStringAsFixed(1)} MB';
  if (bytes >= 1024) return '${(bytes / 1024).toStringAsFixed(0)} KB';
  return '$bytes B';
}
