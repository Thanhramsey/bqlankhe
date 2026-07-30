import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../features/auth/auth.dart';
import '../features/screens.dart';

class CollectorApp extends ConsumerWidget {
  const CollectorApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    const green = Color(0xff176b45);
    final colors = ColorScheme.fromSeed(
        seedColor: green,
        primary: green,
        secondary: const Color(0xffd49b20),
        surface: Colors.white,
        brightness: Brightness.light);
    return MaterialApp(
        debugShowCheckedModeBanner: false,
        title: 'Thu phí An Khê',
        theme: ThemeData(
            colorScheme: colors,
            fontFamily: 'Roboto',
            scaffoldBackgroundColor: const Color(0xfff3f7f4),
            useMaterial3: true,
            appBarTheme: const AppBarTheme(
                backgroundColor: Colors.white,
                foregroundColor: Color(0xff17382b),
                centerTitle: false,
                elevation: 0,
                scrolledUnderElevation: 1,
                titleTextStyle: TextStyle(
                    color: Color(0xff17382b),
                    fontSize: 20,
                    fontWeight: FontWeight.w800)),
            cardTheme: CardThemeData(
                color: Colors.white,
                elevation: 0,
                margin: const EdgeInsets.symmetric(vertical: 6),
                shape: RoundedRectangleBorder(
                    side: const BorderSide(color: Color(0xffe1ebe5)),
                    borderRadius: BorderRadius.circular(18))),
            filledButtonTheme: FilledButtonThemeData(
                style: FilledButton.styleFrom(
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                    textStyle: const TextStyle(fontWeight: FontWeight.w800))),
            inputDecorationTheme: InputDecorationTheme(
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: const BorderSide(color: Color(0xffdce8e0))),
                focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: const BorderSide(color: green, width: 1.7)),
                filled: true,
                fillColor: Colors.white),
            navigationBarTheme: NavigationBarThemeData(
                backgroundColor: Colors.white,
                indicatorColor: const Color(0xffdcefe4),
                height: 70,
                labelTextStyle: WidgetStateProperty.resolveWith((states) =>
                    TextStyle(
                        fontSize: 11,
                        fontWeight: states.contains(WidgetState.selected)
                            ? FontWeight.w800
                            : FontWeight.w500,
                        color: states.contains(WidgetState.selected)
                            ? green
                            : const Color(0xff62746b))))),
        home: auth.loggedIn ? const MainShell() : const LoginScreen());
  }
}
