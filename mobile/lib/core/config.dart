class AppConfig {
  static const apiBaseUrl = String.fromEnvironment('API_BASE_URL',
      defaultValue: 'https://bql-ankhe-api.onrender.com/api/v1');
  static const appName = 'Thu phí An Khê';
}
