import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../core/api_client.dart';

const secureStorage = FlutterSecureStorage();
final apiProvider = Provider((ref) => ApiClient(secureStorage));

class AuthState {
  const AuthState({this.user, this.loading = false, this.error});
  final Map<String, dynamic>? user;
  final bool loading;
  final String? error;
  bool get loggedIn => user != null;
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this.api) : super(const AuthState()) {
    restore();
  }
  final ApiClient api;
  Future<void> restore() async {
    if (await secureStorage.read(key: 'access_token') == null) return;
    try {
      state =
          AuthState(user: Map<String, dynamic>.from(await api.get('/auth/me')));
    } catch (_) {
      await secureStorage.delete(key: 'access_token');
    }
  }

  Future<bool> login(String identifier, String password) async {
    state = const AuthState(loading: true);
    try {
      final data = await api.post('/auth/login',
          data: {'identifier': identifier, 'password': password});
      await secureStorage.write(key: 'access_token', value: data['token']);
      state = AuthState(user: Map<String, dynamic>.from(data['user']));
      return true;
    } catch (e) {
      state = const AuthState(error: 'Tên đăng nhập hoặc mật khẩu không đúng.');
      return false;
    }
  }

  Future<void> logout() async {
    try {
      await api.post('/auth/logout');
    } catch (_) {}
    await secureStorage.delete(key: 'access_token');
    state = const AuthState();
  }
}

final authProvider = StateNotifierProvider<AuthController, AuthState>(
    (ref) => AuthController(ref.read(apiProvider)));
