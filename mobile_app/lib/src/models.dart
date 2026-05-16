int _asInt(dynamic value) {
  if (value is int) return value;
  if (value is double) return value.toInt();
  if (value is String) return int.tryParse(value) ?? 0;
  return 0;
}

String _cleanDate(dynamic value) {
  final text = (value as String? ?? '').trim();
  if (text.isEmpty || text == '0000-00-00' || text == '0000-00-00 00:00:00') {
    return '';
  }
  return text;
}

class SessionUser {
  const SessionUser({
    required this.id,
    required this.name,
    required this.email,
    required this.roleLevel,
    required this.roleName,
    required this.token,
  });

  final int id;
  final String name;
  final String email;
  final int roleLevel;
  final String roleName;
  final String token;
}

class AdmissionItem {
  const AdmissionItem({
    required this.id,
    required this.patientName,
    required this.insuranceName,
    required this.hospitalName,
    required this.cidCode,
    required this.authorizationCode,
    required this.evolutionReport,
    required this.admissionDate,
    required this.dischargeDate,
    required this.dischargeType,
  });

  factory AdmissionItem.fromJson(Map<String, dynamic> json) {
    return AdmissionItem(
      id: _asInt(json['id']),
      patientName: json['patient_name'] as String? ?? '-',
      insuranceName: json['insurance_name'] as String? ?? '',
      hospitalName: json['hospital_name'] as String? ?? '',
      cidCode: json['cid_code'] as String? ?? '',
      authorizationCode: json['authorization_code'] as String? ?? '',
      evolutionReport: json['evolution_report'] as String? ?? '',
      admissionDate: _cleanDate(json['admission_date']),
      dischargeDate: _cleanDate(json['discharge_date']),
      dischargeType: json['discharge_type'] as String? ?? '',
    );
  }

  final int id;
  final String patientName;
  final String insuranceName;
  final String hospitalName;
  final String cidCode;
  final String authorizationCode;
  final String evolutionReport;
  final String admissionDate;
  final String dischargeDate;
  final String dischargeType;
}

class TussItem {
  const TussItem({
    required this.id,
    required this.code,
    required this.description,
    required this.requestedQuantity,
    required this.releasedQuantity,
    required this.releasedFlag,
    required this.performedAt,
    required this.releasedAt,
    required this.releasedBy,
  });

  factory TussItem.fromJson(Map<String, dynamic> json) {
    return TussItem(
      id: _asInt(json['id']),
      code: json['code'] as String? ?? '',
      description: json['description'] as String? ?? '',
      requestedQuantity: _asInt(json['requested_quantity']),
      releasedQuantity: _asInt(json['released_quantity']),
      releasedFlag: json['released_flag'] as String? ?? '',
      performedAt: _cleanDate(json['performed_at']),
      releasedAt: _cleanDate(json['released_at']),
      releasedBy: json['released_by'] as String? ?? '',
    );
  }

  final int id;
  final String code;
  final String description;
  final int requestedQuantity;
  final int releasedQuantity;
  final String releasedFlag;
  final String performedAt;
  final String releasedAt;
  final String releasedBy;
}

class ExtensionItem {
  const ExtensionItem({
    required this.id,
    required this.accommodation,
    required this.startDate,
    required this.endDate,
    required this.days,
  });

  factory ExtensionItem.fromJson(Map<String, dynamic> json) {
    return ExtensionItem(
      id: _asInt(json['id']),
      accommodation: json['accommodation'] as String? ?? '',
      startDate: _cleanDate(json['start_date']),
      endDate: _cleanDate(json['end_date']),
      days: _asInt(json['days']),
    );
  }

  final int id;
  final String accommodation;
  final String startDate;
  final String endDate;
  final int days;
}

class AdmissionDetail {
  const AdmissionDetail({
    required this.admission,
    required this.tussItems,
    required this.extensions,
  });

  final AdmissionItem admission;
  final List<TussItem> tussItems;
  final List<ExtensionItem> extensions;
}

class TussCatalogItem {
  const TussCatalogItem({required this.code, required this.description});

  factory TussCatalogItem.fromJson(Map<String, dynamic> json) {
    return TussCatalogItem(
      code: json['code'] as String? ?? '',
      description: json['description'] as String? ?? '',
    );
  }

  final String code;
  final String description;
}

class EvolutionItem {
  const EvolutionItem({
    required this.id,
    required this.report,
    required this.visitedAt,
    required this.createdBy,
    required this.visitNumber,
  });

  factory EvolutionItem.fromJson(Map<String, dynamic> json) {
    return EvolutionItem(
      id: _asInt(json['id']),
      report: json['report'] as String? ?? '',
      visitedAt: _cleanDate(json['visited_at']),
      createdBy: json['created_by'] as String? ?? '',
      visitNumber: _asInt(json['visit_number']),
    );
  }

  final int id;
  final String report;
  final String visitedAt;
  final String createdBy;
  final int visitNumber;
}

class HomeCareCase {
  const HomeCareCase({
    required this.admissionId,
    required this.patientName,
    required this.insuranceName,
    required this.hospitalName,
    required this.admissionDate,
    required this.days,
    required this.updateId,
    required this.updatedAt,
    required this.status,
    required this.approvedMode,
    required this.suggestedMode,
    required this.expectedDate,
    required this.mainBarrier,
    required this.supplier,
    required this.transitionPlan,
    required this.notes,
    required this.neadClassification,
    required this.neadEligible,
    required this.flaggedHomeCare,
  });

  factory HomeCareCase.fromJson(Map<String, dynamic> json) {
    return HomeCareCase(
      admissionId: _asInt(json['admission_id']),
      patientName: json['patient_name'] as String? ?? '-',
      insuranceName: json['insurance_name'] as String? ?? '',
      hospitalName: json['hospital_name'] as String? ?? '',
      admissionDate: _cleanDate(json['admission_date']),
      days: _asInt(json['days']),
      updateId: _asInt(json['update_id']),
      updatedAt: _cleanDate(json['updated_at']),
      status: json['status'] as String? ?? '',
      approvedMode: json['approved_mode'] as String? ?? '',
      suggestedMode: json['suggested_mode'] as String? ?? '',
      expectedDate: _cleanDate(json['expected_date']),
      mainBarrier: json['main_barrier'] as String? ?? '',
      supplier: json['supplier'] as String? ?? '',
      transitionPlan: json['transition_plan'] as String? ?? '',
      notes: json['notes'] as String? ?? '',
      neadClassification: json['nead_classification'] as String? ?? '',
      neadEligible: json['nead_eligible'] as String? ?? '',
      flaggedHomeCare: json['flagged_home_care'] as String? ?? '',
    );
  }

  final int admissionId;
  final String patientName;
  final String insuranceName;
  final String hospitalName;
  final String admissionDate;
  final int days;
  final int updateId;
  final String updatedAt;
  final String status;
  final String approvedMode;
  final String suggestedMode;
  final String expectedDate;
  final String mainBarrier;
  final String supplier;
  final String transitionPlan;
  final String notes;
  final String neadClassification;
  final String neadEligible;
  final String flaggedHomeCare;
}

class AdverseEventCase {
  const AdverseEventCase({
    required this.admissionId,
    required this.patientName,
    required this.insuranceName,
    required this.hospitalName,
    required this.admissionDate,
    required this.days,
    required this.updateId,
    required this.eventDate,
    required this.eventType,
    required this.report,
    required this.signaledFlag,
    required this.concludedFlag,
    required this.closeFlag,
  });

  factory AdverseEventCase.fromJson(Map<String, dynamic> json) {
    return AdverseEventCase(
      admissionId: _asInt(json['admission_id']),
      patientName: json['patient_name'] as String? ?? '-',
      insuranceName: json['insurance_name'] as String? ?? '',
      hospitalName: json['hospital_name'] as String? ?? '',
      admissionDate: _cleanDate(json['admission_date']),
      days: _asInt(json['days']),
      updateId: _asInt(json['update_id']),
      eventDate: _cleanDate(json['event_date']),
      eventType: json['event_type'] as String? ?? '',
      report: json['report'] as String? ?? '',
      signaledFlag: json['signaled_flag'] as String? ?? '',
      concludedFlag: json['concluded_flag'] as String? ?? '',
      closeFlag: json['close_flag'] as String? ?? '',
    );
  }

  final int admissionId;
  final String patientName;
  final String insuranceName;
  final String hospitalName;
  final String admissionDate;
  final int days;
  final int updateId;
  final String eventDate;
  final String eventType;
  final String report;
  final String signaledFlag;
  final String concludedFlag;
  final String closeFlag;
}

class LongStayCase {
  const LongStayCase({
    required this.admissionId,
    required this.patientName,
    required this.insuranceName,
    required this.hospitalName,
    required this.admissionDate,
    required this.days,
    required this.thresholdDays,
    required this.updateId,
    required this.updatedAt,
    required this.status,
    required this.mainReason,
    required this.owner,
    required this.nextReviewDate,
    required this.expectedDischargeDate,
    required this.escalatedFlag,
    required this.riskLevel,
    required this.clinicalBarrier,
    required this.administrativeBarrier,
    required this.actionPlan,
    required this.notes,
    required this.dehospitalizationFlag,
  });

  factory LongStayCase.fromJson(Map<String, dynamic> json) {
    return LongStayCase(
      admissionId: _asInt(json['admission_id']),
      patientName: json['patient_name'] as String? ?? '-',
      insuranceName: json['insurance_name'] as String? ?? '',
      hospitalName: json['hospital_name'] as String? ?? '',
      admissionDate: _cleanDate(json['admission_date']),
      days: _asInt(json['days']),
      thresholdDays: _asInt(json['threshold_days']),
      updateId: _asInt(json['update_id']),
      updatedAt: _cleanDate(json['updated_at']),
      status: json['status'] as String? ?? '',
      mainReason: json['main_reason'] as String? ?? '',
      owner: json['owner'] as String? ?? '',
      nextReviewDate: _cleanDate(json['next_review_date']),
      expectedDischargeDate: _cleanDate(json['expected_discharge_date']),
      escalatedFlag: json['escalated_flag'] as String? ?? '',
      riskLevel: json['risk_level'] as String? ?? '',
      clinicalBarrier: json['clinical_barrier'] as String? ?? '',
      administrativeBarrier: json['administrative_barrier'] as String? ?? '',
      actionPlan: json['action_plan'] as String? ?? '',
      notes: json['notes'] as String? ?? '',
      dehospitalizationFlag: json['dehospitalization_flag'] as String? ?? '',
    );
  }

  final int admissionId;
  final String patientName;
  final String insuranceName;
  final String hospitalName;
  final String admissionDate;
  final int days;
  final int thresholdDays;
  final int updateId;
  final String updatedAt;
  final String status;
  final String mainReason;
  final String owner;
  final String nextReviewDate;
  final String expectedDischargeDate;
  final String escalatedFlag;
  final String riskLevel;
  final String clinicalBarrier;
  final String administrativeBarrier;
  final String actionPlan;
  final String notes;
  final String dehospitalizationFlag;
}
