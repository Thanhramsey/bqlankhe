import 'package:bqlankhe_collector/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:bqlankhe_collector/features/screens.dart';

void main() {
  test('formats API month timestamps in the device timezone', () {
    expect(month('2026-06'), '06/2026');
    expect(month('2026-05-31T17:00:00.000000Z'), '06/2026');
  });
  testWidgets('hiển thị màn hình đăng nhập', (tester) async {
    await tester.pumpWidget(const ProviderScope(child: CollectorApp()));
    await tester.pump();
    expect(find.text('QUẢN LÝ THU PHÍ RÁC'), findsOneWidget);
    expect(find.byType(FilledButton), findsOneWidget);
  });
}
