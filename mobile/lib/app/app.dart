import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../features/auth/auth.dart';
import '../features/screens.dart';

class CollectorApp extends ConsumerWidget {
  const CollectorApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    return MaterialApp(
        debugShowCheckedModeBanner: false,
        title: 'Thu phí An Khê',
        theme: ThemeData(
            colorScheme:
                ColorScheme.fromSeed(seedColor: const Color(0xff2563eb)),
            scaffoldBackgroundColor: const Color(0xfff5f7fb),
            useMaterial3: true,
            inputDecorationTheme: const InputDecorationTheme(
                border: OutlineInputBorder(),
                filled: true,
                fillColor: Colors.white)),
        home: auth.loggedIn ? const MainShell() : const LoginScreen());
  }
}
