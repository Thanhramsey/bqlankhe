import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/api_client.dart';

const secureStorage = FlutterSecureStorage();
final apiProvider = Provider((ref) => ApiClient(secureStorage));

Future<String?> safeSecureRead(String key) async {
  try {
    return await secureStorage.read(key: key);
  } catch (error) {
    // Clear entries encrypted with a stale Android Keystore key. This commonly
    // occurs after reinstalling/restoring the debug app.
    debugPrint('Secure storage was reset after a read error: $error');
    await secureStorage.deleteAll();
    return null;
  }
}

class AuthState {
  const AuthState(
      {this.user,
      this.loading = false,
      this.error,
      this.biometricEnabled = false,
      this.biometricAvailable = false});
  final Map<String, dynamic>? user;
  final bool loading;
  final String? error;
  final bool biometricEnabled, biometricAvailable;
  bool get loggedIn => user != null;
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this.api) : super(const AuthState()) {
    initialize();
  }
  final ApiClient api;
  final LocalAuthentication localAuth = LocalAuthentication();

  Future<void> initialize() async {
    final prefs = await SharedPreferences.getInstance();
    var enabled = prefs.getBool('biometric_login_enabled') ?? false;
    var available = false;
    try {
      available = await localAuth.canCheckBiometrics &&
          (await localAuth.getAvailableBiometrics()).isNotEmpty;
    } catch (_) {}
    final biometricIdentifier = await safeSecureRead('biometric_identifier');
    final biometricPassword = await safeSecureRead('biometric_password');
    if (enabled &&
        (biometricIdentifier == null || biometricPassword == null)) {
      enabled = false;
      await prefs.setBool('biometric_login_enabled', false);
    }
    await secureStorage.delete(key: 'biometric_access_token');
    state = AuthState(
        biometricEnabled: enabled && available, biometricAvailable: available);
    if (!enabled || !available) await restore();
  }

  Future<void> restore() async {
    if (await safeSecureRead('access_token') == null) return;
    try {
      state = AuthState(
          user: Map<String, dynamic>.from(await api.get('/auth/me')),
          biometricEnabled: state.biometricEnabled,
          biometricAvailable: state.biometricAvailable);
    } catch (_) {
      await secureStorage.delete(key: 'access_token');
    }
  }

  Future<bool> login(String identifier, String password) async {
    state = AuthState(
        loading: true,
        biometricEnabled: state.biometricEnabled,
        biometricAvailable: state.biometricAvailable);
    try {
      final data = await api.post('/auth/login',
          data: {'identifier': identifier, 'password': password});
      final token = '${data['token']}';
      final loggedInUser = Map<String, dynamic>.from(data['user'] as Map);
      await secureStorage.write(key: 'access_token', value: token);
      try {
        await secureStorage.write(
            key: 'biometric_candidate_identifier', value: identifier);
        await secureStorage.write(
            key: 'biometric_candidate_password', value: password);
        if (state.biometricEnabled) {
          await secureStorage.write(
              key: 'biometric_identifier', value: identifier);
          await secureStorage.write(key: 'biometric_password', value: password);
        }
      } catch (storageError, storageStack) {
        debugPrint('Biometric credential storage failed: $storageError');
        debugPrintStack(stackTrace: storageStack);
      }
      state = AuthState(
          user: loggedInUser,
          biometricEnabled: state.biometricEnabled,
          biometricAvailable: state.biometricAvailable);
      return true;
    } catch (e, stackTrace) {
      debugPrint('Password login failed: $e');
      debugPrintStack(stackTrace: stackTrace);
      var message = 'Không thể đăng nhập. Vui lòng thử lại.';
      if (e is DioException) {
        final responseData = e.response?.data;
        if (responseData is Map && responseData['message'] != null) {
          message = '${responseData['message']}';
        } else if (e.type == DioExceptionType.connectionTimeout ||
            e.type == DioExceptionType.receiveTimeout) {
          message = 'Máy chủ phản hồi quá chậm. Vui lòng chờ Render khởi động rồi thử lại.';
        } else if (e.type == DioExceptionType.connectionError) {
          message = 'Không kết nối được máy chủ ${api.dio.options.baseUrl}.';
        }
      } else if (kDebugMode) {
        message = 'Lỗi ứng dụng: ${e.runtimeType} — $e';
      }
      state = AuthState(
          error: message,
          biometricEnabled: state.biometricEnabled,
          biometricAvailable: state.biometricAvailable);
      return false;
    }
  }

  Future<void> logout() async {
    try {
      await api.post('/auth/logout');
    } catch (_) {}
    await secureStorage.delete(key: 'access_token');
    await secureStorage.delete(key: 'biometric_candidate_identifier');
    await secureStorage.delete(key: 'biometric_candidate_password');
    if (!state.biometricEnabled) {
      await secureStorage.delete(key: 'biometric_identifier');
      await secureStorage.delete(key: 'biometric_password');
    }
    state = AuthState(
        biometricEnabled: state.biometricEnabled,
        biometricAvailable: state.biometricAvailable);
  }

  Future<bool> biometricLogin() async {
    if (!state.biometricEnabled || !state.biometricAvailable) return false;
    state = AuthState(
        loading: true, biometricEnabled: true, biometricAvailable: true);
    try {
      final authenticated = await localAuth.authenticate(
          localizedReason: 'Xác thực vân tay để đăng nhập ứng dụng thu phí',
          biometricOnly: true,
          persistAcrossBackgrounding: true);
      if (!authenticated) {
        state =
            const AuthState(biometricEnabled: true, biometricAvailable: true);
        return false;
      }
      final identifier = await safeSecureRead('biometric_identifier');
      final password = await safeSecureRead('biometric_password');
      if (identifier == null || password == null) {
        throw StateError('missing biometric credentials');
      }
      final data = await api.post('/auth/login',
          data: {'identifier': identifier, 'password': password});
      await secureStorage.write(key: 'access_token', value: data['token']);
      state = AuthState(
          user: Map<String, dynamic>.from(data['user']),
          biometricEnabled: true,
          biometricAvailable: true);
      return true;
    } catch (_) {
      await secureStorage.delete(key: 'access_token');
      state = const AuthState(
          error: 'Không thể đăng nhập bằng vân tay. Vui lòng dùng mật khẩu.',
          biometricEnabled: true,
          biometricAvailable: true);
      return false;
    }
  }

  Future<bool> setBiometricEnabled(bool enabled) async {
    if (!state.biometricAvailable) return false;
    if (enabled) {
      try {
        final authenticated = await localAuth.authenticate(
            localizedReason: 'Xác nhận bật đăng nhập bằng vân tay',
            biometricOnly: true,
            persistAcrossBackgrounding: true);
        if (!authenticated) return false;
        final identifier =
            await safeSecureRead('biometric_candidate_identifier');
        final password = await safeSecureRead('biometric_candidate_password');
        if (identifier == null || password == null) return false;
        await secureStorage.write(
            key: 'biometric_identifier', value: identifier);
        await secureStorage.write(key: 'biometric_password', value: password);
      } catch (_) {
        return false;
      }
    } else {
      await secureStorage.delete(key: 'biometric_identifier');
      await secureStorage.delete(key: 'biometric_password');
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('biometric_login_enabled', enabled);
    state = AuthState(
        user: state.user,
        biometricEnabled: enabled,
        biometricAvailable: state.biometricAvailable);
    return true;
  }
}

final authProvider = StateNotifierProvider<AuthController, AuthState>(
    (ref) => AuthController(ref.read(apiProvider)));
