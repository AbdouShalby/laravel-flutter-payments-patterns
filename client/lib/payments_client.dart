// Minimal Flutter/Dart sketch: token auth, idempotency key reuse, retry with backoff.
import 'dart:convert';
import 'dart:math';
import 'package:http/http.dart' as http;

class PaymentsClient {
  PaymentsClient(this.baseUrl, this.token, {http.Client? httpClient})
      : _http = httpClient ?? http.Client();

  final String baseUrl;
  final String token;
  final http.Client _http;

  static String newIdempotencyKey() {
    final r = Random.secure();
    return List.generate(16, (_) => r.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
  }

  /// Create a key once per user action and reuse it for every retry of that action.
  Future<Map<String, dynamic>> createIntent(int amountMinor, String currency, String key) {
    return _withRetry(() => _http.post(
          Uri.parse('$baseUrl/api/payment-intents'),
          headers: _headers({'Idempotency-Key': key}),
          body: jsonEncode({'amount_minor': amountMinor, 'currency': currency}),
        ));
  }

  Future<Map<String, dynamic>> getIntent(String id) {
    return _withRetry(() => _http.get(Uri.parse('$baseUrl/api/payment-intents/$id'), headers: _headers()));
  }

  /// Poll until the server reports a final state.
  Future<String> waitForFinalStatus(String id, {Duration every = const Duration(seconds: 2), int maxPolls = 30}) async {
    for (var i = 0; i < maxPolls; i++) {
      final status = (await getIntent(id))['status'] as String;
      if (status == 'succeeded' || status == 'failed') return status;
      await Future.delayed(every);
    }
    return 'pending';
  }

  Map<String, String> _headers([Map<String, String> extra = const {}]) => {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...extra,
      };

  Future<Map<String, dynamic>> _withRetry(Future<http.Response> Function() send, {int attempts = 4}) async {
    for (var i = 0;; i++) {
      try {
        final res = await send();
        final retryable = res.statusCode >= 500 || res.statusCode == 429;
        if (!retryable) {
          if (res.statusCode >= 400) throw Exception('HTTP ${res.statusCode}: ${res.body}');
          return jsonDecode(res.body) as Map<String, dynamic>;
        }
        if (i + 1 >= attempts) throw Exception('HTTP ${res.statusCode} after $attempts attempts');
      } on http.ClientException {
        if (i + 1 >= attempts) rethrow;
      }
      await Future.delayed(Duration(milliseconds: 300 * pow(2, i).toInt()));
    }
  }
}
