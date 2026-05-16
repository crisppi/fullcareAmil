import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:mobile_app/src/models.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MobileApi {
  MobileApi();

  static const String _baseUrl =
      'http://10.0.2.2/FullCare/api/mobile/index.php';
  static const String _tokenKey = 'fullcare_mobile_token';

  String? _token;

  Future<void> loadSavedToken() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_tokenKey);
  }

  Future<void> clearSession() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
  }

  bool get hasToken => _token != null && _token!.isNotEmpty;

  Future<SessionUser> login({
    required String email,
    required String password,
  }) async {
    final payload = await _request(
      method: 'POST',
      action: 'login',
      body: {'email': email, 'password': password},
    );

    final data = payload['data'] as Map<String, dynamic>;
    final user = data['user'] as Map<String, dynamic>;
    final token = data['token'] as String;
    _token = token;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);

    return SessionUser(
      id: user['id'] as int? ?? 0,
      name: user['name'] as String? ?? '',
      email: user['email'] as String? ?? '',
      roleLevel: user['role_level'] as int? ?? 99,
      roleName: user['role_name'] as String? ?? '',
      token: token,
    );
  }

  Future<SessionUser> me() async {
    final payload = await _request(action: 'me');
    final data = payload['data'] as Map<String, dynamic>;
    return SessionUser(
      id: data['id'] as int? ?? 0,
      name: data['name'] as String? ?? '',
      email: data['email'] as String? ?? '',
      roleLevel: data['role_level'] as int? ?? 99,
      roleName: data['role_name'] as String? ?? '',
      token: _token ?? '',
    );
  }

  Future<List<AdmissionItem>> listAdmissions(String query) async {
    final payload = await _request(
      action: 'admissions',
      query: {'query': query},
    );
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => AdmissionItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<AdmissionDetail> fetchAdmissionDetail(int admissionId) async {
    final payload = await _request(
      action: 'admission',
      query: {'id': '$admissionId'},
    );
    final data = payload['data'] as Map<String, dynamic>;
    return AdmissionDetail(
      admission: AdmissionItem.fromJson(
        data['admission'] as Map<String, dynamic>,
      ),
      tussItems:
          ((data['tuss_items'] as List<dynamic>? ?? []))
              .map((item) => TussItem.fromJson(item as Map<String, dynamic>))
              .toList(),
      extensions:
          ((data['extensions'] as List<dynamic>? ?? []))
              .map(
                (item) => ExtensionItem.fromJson(item as Map<String, dynamic>),
              )
              .toList(),
    );
  }

  Future<List<TussCatalogItem>> searchTussCatalog(String query) async {
    final payload = await _request(
      action: 'tuss-catalog',
      query: {'query': query},
    );
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => TussCatalogItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<String>> listDischargeTypes() async {
    final payload = await _request(action: 'discharge-types');
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => item?.toString().trim() ?? '')
        .where((item) => item.isNotEmpty)
        .toList();
  }

  Future<TussItem> createTuss({
    required int admissionId,
    required String code,
    required int requestedQuantity,
    required int releasedQuantity,
    required String releasedFlag,
    String performedAt = '',
  }) async {
    final payload = await _request(
      method: 'POST',
      action: 'admission-tuss',
      body: {
        'admission_id': admissionId,
        'code': code,
        'requested_quantity': requestedQuantity,
        'released_quantity': releasedQuantity,
        'released_flag': releasedFlag,
        'performed_at': performedAt,
      },
    );

    return TussItem.fromJson(payload['data'] as Map<String, dynamic>);
  }

  Future<ExtensionItem> createExtension({
    required int admissionId,
    required String accommodation,
    required int days,
    required String startDate,
    required String endDate,
    String isolationFlag = 'n',
  }) async {
    final payload = await _request(
      method: 'POST',
      action: 'admission-extension',
      body: {
        'admission_id': admissionId,
        'accommodation': accommodation,
        'days': days,
        'start_date': startDate,
        'end_date': endDate,
        'isolation_flag': isolationFlag,
      },
    );

    return ExtensionItem.fromJson(payload['data'] as Map<String, dynamic>);
  }

  Future<void> createDischarge({
    required int admissionId,
    required String type,
    required String date,
    required String time,
  }) async {
    await _request(
      method: 'POST',
      action: 'admission-discharge',
      body: {
        'admission_id': admissionId,
        'type': type,
        'date': date,
        'time': time,
      },
    );
  }

  Future<List<EvolutionItem>> listEvolutions(int admissionId) async {
    final payload = await _request(
      action: 'admission-evolutions',
      query: {'id': '$admissionId'},
    );
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => EvolutionItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<HomeCareCase>> listHomeCareCases(String query) async {
    final payload = await _request(
      action: 'home-care-cases',
      query: {'query': query},
    );
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => HomeCareCase.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<LongStayCase>> listLongStayCases(String query) async {
    final payload = await _request(
      action: 'long-stay-cases',
      query: {'query': query},
    );
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => LongStayCase.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<String>> listLongStayStatuses() async {
    final payload = await _request(action: 'long-stay-statuses');
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => item?.toString().trim() ?? '')
        .where((item) => item.isNotEmpty)
        .toList();
  }

  Future<List<String>> listLongStayReasons() async {
    final payload = await _request(action: 'long-stay-reasons');
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => item?.toString().trim() ?? '')
        .where((item) => item.isNotEmpty)
        .toList();
  }

  Future<List<String>> listLongStayRisks() async {
    final payload = await _request(action: 'long-stay-risks');
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => item?.toString().trim() ?? '')
        .where((item) => item.isNotEmpty)
        .toList();
  }

  Future<LongStayCase> saveLongStayUpdate({
    required int admissionId,
    required String status,
    required String mainReason,
    required String clinicalBarrier,
    required String administrativeBarrier,
    required String actionPlan,
    required String owner,
    required String deadlineDate,
    required String expectedDischargeDate,
    required String nextReviewDate,
    required String dehospitalizationFlag,
    required String escalatedFlag,
    required String riskLevel,
    required String notes,
  }) async {
    final payload = await _request(
      method: 'POST',
      action: 'long-stay-update',
      body: {
        'admission_id': admissionId,
        'status': status,
        'main_reason': mainReason,
        'clinical_barrier': clinicalBarrier,
        'administrative_barrier': administrativeBarrier,
        'action_plan': actionPlan,
        'owner': owner,
        'deadline_date': deadlineDate,
        'expected_discharge_date': expectedDischargeDate,
        'next_review_date': nextReviewDate,
        'dehospitalization_flag': dehospitalizationFlag,
        'escalated_flag': escalatedFlag,
        'risk_level': riskLevel,
        'notes': notes,
      },
    );

    return LongStayCase.fromJson(payload['data'] as Map<String, dynamic>);
  }

  Future<HomeCareCase> saveHomeCareUpdate({
    required int admissionId,
    required String status,
    required String supplier,
    required String approvedMode,
    required String expectedDate,
    required String mainBarrier,
    required String transitionPlan,
    required String notes,
  }) async {
    final payload = await _request(
      method: 'POST',
      action: 'home-care-update',
      body: {
        'admission_id': admissionId,
        'status': status,
        'supplier': supplier,
        'approved_mode': approvedMode,
        'expected_date': expectedDate,
        'main_barrier': mainBarrier,
        'transition_plan': transitionPlan,
        'notes': notes,
      },
    );

    return HomeCareCase.fromJson(payload['data'] as Map<String, dynamic>);
  }

  Future<List<AdverseEventCase>> listAdverseEventCases(String query) async {
    final payload = await _request(
      action: 'adverse-event-cases',
      query: {'query': query},
    );
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => AdverseEventCase.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<String>> listAdverseEventTypes() async {
    final payload = await _request(action: 'adverse-event-types');
    final items =
        (payload['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ??
        [];
    return items
        .map((item) => item?.toString().trim() ?? '')
        .where((item) => item.isNotEmpty)
        .toList();
  }

  Future<AdverseEventCase> saveAdverseEventUpdate({
    required int admissionId,
    required String eventType,
    required String report,
    required String eventDate,
    String signaledFlag = 's',
    String concludedFlag = 'n',
    String closeFlag = 'n',
  }) async {
    final payload = await _request(
      method: 'POST',
      action: 'adverse-event-update',
      body: {
        'admission_id': admissionId,
        'event_type': eventType,
        'report': report,
        'event_date': eventDate,
        'signaled_flag': signaledFlag,
        'concluded_flag': concludedFlag,
        'close_flag': closeFlag,
      },
    );

    return AdverseEventCase.fromJson(payload['data'] as Map<String, dynamic>);
  }

  Future<EvolutionItem> saveEvolution({
    required int admissionId,
    required String report,
  }) async {
    final payload = await _request(
      method: 'POST',
      action: 'admission-evolution',
      body: {'admission_id': admissionId, 'report': report},
    );

    final data = payload['data'] as Map<String, dynamic>;
    return EvolutionItem.fromJson(data);
  }

  Future<Map<String, dynamic>> _request({
    String method = 'GET',
    required String action,
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    final queryParameters = <String, String>{'action': action, ...?query};
    if (_token != null && _token!.isNotEmpty) {
      queryParameters['token'] = _token!;
    }

    final uri = Uri.parse(_baseUrl).replace(queryParameters: queryParameters);

    final headers = <String, String>{'Content-Type': 'application/json'};
    if (_token != null && _token!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $_token';
    }

    late final http.Response response;
    if (method == 'POST') {
      response = await http.post(
        uri,
        headers: headers,
        body: jsonEncode(body ?? <String, dynamic>{}),
      );
    } else {
      response = await http.get(uri, headers: headers);
    }

    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode == 401) {
      await clearSession();
      throw Exception(decoded['message'] ?? 'Sessao expirada.');
    }
    if (response.statusCode >= 400 || decoded['success'] != true) {
      throw Exception(decoded['message'] ?? 'Falha na requisicao.');
    }

    return decoded;
  }
}
