import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'config.dart';

class ApiClient {
  ApiClient(this.storage)
      : dio = Dio(BaseOptions(
            baseUrl: AppConfig.apiBaseUrl,
            // Render Free can take close to a minute to wake after being idle.
            connectTimeout: const Duration(seconds: 75),
            receiveTimeout: const Duration(seconds: 90))) {
    dio.interceptors
        .add(InterceptorsWrapper(onRequest: (options, handler) async {
      // Login must never depend on an old encrypted token. Android can retain
      // secure-storage data whose encryption key is no longer valid after an
      // app reinstall/restore, which otherwise prevents login altogether.
      if (!options.path.endsWith('/auth/login')) {
        try {
          final token = await storage.read(key: 'access_token');
          if (token != null) options.headers['Authorization'] = 'Bearer $token';
        } catch (_) {
          await storage.deleteAll();
        }
      }
      handler.next(options);
    }, onError: (error, handler) async {
      if (error.response?.statusCode == 401) {
        await storage.delete(key: 'access_token');
      }
      handler.next(error);
    }));
  }
  final FlutterSecureStorage storage;
  final Dio dio;
  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async =>
      (await dio.get(path, queryParameters: query)).data['data'];
  Future<dynamic> post(String path, {Object? data}) async =>
      (await dio.post(path, data: data)).data['data'];
  Future<dynamic> put(String path, {Object? data}) async =>
      (await dio.put(path, data: data)).data['data'];
  Future<dynamic> delete(String path) async =>
      (await dio.delete(path)).data['data'];

  Future<void> download(String path, String savePath) async {
    await dio.download(path, savePath);
  }
}
