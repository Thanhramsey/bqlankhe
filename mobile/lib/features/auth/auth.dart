import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/api_client.dart';

const secureStorage = FlutterSecureStorage();
final apiProvider = Provider((ref) => ApiClient(secureStorage));

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
    final enabled = prefs.getBool('biometric_login_enabled') ?? false;
    var available = false;
    try {
      available = await localAuth.canCheckBiometrics &&
          (await localAuth.getAvailableBiometrics()).isNotEmpty;
    } catch (_) {}
    state = AuthState(
        biometricEnabled: enabled && available, biometricAvailable: available);
    if (!enabled || !available) await restore();
  }

  Future<void> restore() async {
    if (await secureStorage.read(key: 'access_token') == null) return;
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
      await secureStorage.write(key: 'access_token', value: data['token']);
      state = AuthState(
          user: Map<String, dynamic>.from(data['user']),
          biometricEnabled: state.biometricEnabled,
          biometricAvailable: state.biometricAvailable);
      return true;
    } catch (e) {
      state = AuthState(
          error: 'Tên đăng nhập hoặc mật khẩu không đúng.',
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
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('biometric_login_enabled', false);
    state = AuthState(biometricAvailable: state.biometricAvailable);
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
      final token = await secureStorage.read(key: 'access_token');
      if (token == null) throw StateError('missing token');
      state = AuthState(
          user: Map<String, dynamic>.from(await api.get('/auth/me')),
          biometricEnabled: true,
          biometricAvailable: true);
      return true;
    } catch (_) {
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
      } catch (_) {
        return false;
      }
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
