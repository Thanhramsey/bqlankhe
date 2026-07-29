import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import 'auth/auth.dart';

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
        body: SafeArea(
            child: Center(
                child: SingleChildScrollView(
      padding: const EdgeInsets.all(28),
      child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Column(children: [
            const Icon(Icons.recycling, size: 96, color: Color(0xff168451)),
            const SizedBox(height: 16),
            const Text('QUẢN LÝ THU PHÍ RÁC',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
            const SizedBox(height: 28),
            TextField(
                controller: user,
                decoration: const InputDecoration(
                    labelText: 'Tên đăng nhập',
                    prefixIcon: Icon(Icons.person_outline))),
            const SizedBox(height: 14),
            TextField(
                controller: pass,
                obscureText: hidden,
                onSubmitted: (_) => login(),
                decoration: InputDecoration(
                    labelText: 'Mật khẩu',
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                        onPressed: () => setState(() => hidden = !hidden),
                        icon: Icon(hidden
                            ? Icons.visibility
                            : Icons.visibility_off)))),
            if (state.error != null)
              Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Text(state.error!,
                      style: const TextStyle(color: Colors.red))),
            const SizedBox(height: 20),
            SizedBox(
                width: double.infinity,
                height: 54,
                child: FilledButton(
                    onPressed: state.loading ? null : login,
                    child: state.loading
                        ? const CircularProgressIndicator()
                        : const Text('ĐĂNG NHẬP'))),
            const SizedBox(height: 18),
            const Text('Phiên bản 1.0.0', style: TextStyle(color: Colors.grey)),
          ])),
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
                  icon: Icon(Icons.home_outlined), label: 'Trang chủ'),
              NavigationDestination(
                  icon: Icon(Icons.payments_outlined), label: 'Thu tiền'),
              NavigationDestination(
                  icon: Icon(Icons.receipt_long_outlined), label: 'Giao dịch'),
              NavigationDestination(
                  icon: Icon(Icons.notifications_outlined), label: 'Thông báo'),
              NavigationDestination(
                  icon: Icon(Icons.person_outline), label: 'Cá nhân'),
            ]),
      );
}

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user;
    final stats = ref.watch(statsProvider);
    final name = '${user?['name'] ?? ''}';
    return Scaffold(
        appBar: AppBar(title: Text('Xin chào, $name')),
        body: RefreshIndicator(
          onRefresh: () => ref.refresh(statsProvider.future),
          child: ListView(padding: const EdgeInsets.all(16), children: [
            Card(
                child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Row(children: [
                      CircleAvatar(
                          radius: 28,
                          child: Text(name.isEmpty ? '?' : name[0])),
                      const SizedBox(width: 14),
                      Expanded(
                          child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                            Text(name,
                                style: const TextStyle(
                                    fontSize: 18, fontWeight: FontWeight.bold)),
                            Text('${user?['phone'] ?? 'Chưa có số điện thoại'}')
                          ])),
                    ]))),
            const SizedBox(height: 12),
            stats.when(
              data: (data) => GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  childAspectRatio: 1.45,
                  children: [
                    Kpi('Hộ đã thu', '${data['households']}',
                        Icons.home_work_outlined),
                    Kpi('Tiền hôm nay', money(data['total']),
                        Icons.payments_outlined),
                    Kpi('Tiền mặt', money(data['cash']), Icons.money),
                    Kpi('Chuyển khoản', money(data['bank_transfer']),
                        Icons.account_balance),
                  ]),
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, stackTrace) =>
                  const Text('Không tải được thống kê. Kéo xuống để thử lại.'),
            ),
          ]),
        ));
  }
}

class Kpi extends StatelessWidget {
  const Kpi(this.label, this.value, this.icon, {super.key});
  final String label, value;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Card(
      child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(icon, color: const Color(0xff2563eb)),
            Text(value,
                style:
                    const TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
            Text(label)
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
                                horizontal: 12, vertical: 5),
                            child: ListTile(
                                title: Text('${household['owner_name']}',
                                    style: const TextStyle(
                                        fontWeight: FontWeight.bold)),
                                subtitle: Text(
                                    '${household['code']} · ${household['address']}\n${household['route']?['name'] ?? 'Chưa có tuyến'} · ${latestPaymentLabel(household['latest_payment'])}'),
                                isThreeLine: true,
                                trailing: FilledButton(
                                    onPressed: () => Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                            builder: (_) =>
                                                CollectScreen(household))),
                                    child: const Text('Thu tiền')),
                                onTap: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                        builder: (_) => HouseholdDetailScreen(
                                            household['id'] as int)))));
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
                    color: Color(0xff16a34a)))
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
                      TextButton(
                          onPressed: () => downloadAndOpen(
                              ref,
                              '/mobile/payments/${data['id']}/receipt',
                              'phieu-thu-${data['code']}.pdf'),
                          child: const Text('Mở phiếu thu')),
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
              const SizedBox(height: 18),
              FilledButton.icon(
                  onPressed: () => downloadAndOpen(
                      ref,
                      '/mobile/payments/${widget.id}/receipt',
                      'phieu-thu-${item['code']}.pdf'),
                  icon: const Icon(Icons.receipt_long),
                  label: const Text('Mở phiếu thu')),
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
    final user = ref.watch(authProvider).user;
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
              leading: const Icon(Icons.phone),
              title: Text('${user?['phone'] ?? 'Chưa cập nhật'}')),
          const ListTile(
              leading: Icon(Icons.print),
              title: Text('Máy in Bluetooth'),
              subtitle: Text('Chưa cấu hình')),
          ListTile(
              leading: const Icon(Icons.folder_copy_outlined),
              title: const Text('Văn bản, tài liệu'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context,
                  MaterialPageRoute(builder: (_) => const DocumentsScreen()))),
          ListTile(
              leading: const Icon(Icons.password_outlined),
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
