import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'config.dart';

class ApiClient {
  ApiClient(this.storage)
      : dio = Dio(BaseOptions(
            baseUrl: AppConfig.apiBaseUrl,
            connectTimeout: const Duration(seconds: 12),
            receiveTimeout: const Duration(seconds: 20))) {
    dio.interceptors
        .add(InterceptorsWrapper(onRequest: (options, handler) async {
      final token = await storage.read(key: 'access_token');
      if (token != null) options.headers['Authorization'] = 'Bearer $token';
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

  Future<void> download(String path, String savePath) async {
    await dio.download(path, savePath);
  }
}
