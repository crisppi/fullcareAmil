<?php
// /ajax/visitas_paciente.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../globals.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/_auth_scope.php';

ajax_require_active_session();

try {
  $ctx = ajax_user_context($conn);
  $pacId = filter_input(INPUT_GET, 'id_paciente', FILTER_VALIDATE_INT);
  $page  = max(1, (int)($_GET['page'] ?? 1));
  $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
  $offset = ($page - 1) * $limit;

  if (!$pacId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id_paciente obrigatório']);
    exit;
  }

  $scopeParams = [];
  $scopeSql = ajax_scope_clause_for_internacao($ctx, 'ac', $scopeParams, 'vp');

  // Total de visitas do paciente (somente não retificadas)
  $sqlTotal = "
    SELECT COUNT(*) AS total
    FROM tb_visita vi
    JOIN tb_internacao ac ON ac.id_internacao = vi.fk_internacao_vis
    WHERE ac.fk_paciente_int = :pacId
      AND (vi.retificado IS NULL OR vi.retificado = 0)
      {$scopeSql}
  ";
  $st = $conn->prepare($sqlTotal);
  ajax_bind_params($st, array_merge([':pacId' => (int)$pacId], $scopeParams));
  $st->execute();
  $total = (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // Lista paginada
  $sql = "
    SELECT
      vi.id_visita,
      vi.data_visita_vis,
      vi.usuario_create,
      vi.visita_no_vis,
      vi.rel_visita_vis,
      vi.acoes_int_vis,
      vi.visita_auditor_prof_med,
      vi.visita_auditor_prof_enf,
      vi.visita_med_vis,
      vi.visita_enf_vis,
      vi.fk_usuario_vis,
      ac.id_internacao,
      ac.fk_hospital_int,
      ho.nome_hosp
    FROM tb_visita vi
    JOIN tb_internacao ac ON ac.id_internacao = vi.fk_internacao_vis
    LEFT JOIN tb_hospital ho ON ho.id_hospital = ac.fk_hospital_int
    WHERE ac.fk_paciente_int = :pacId
      AND (vi.retificado IS NULL OR vi.retificado = 0)
      {$scopeSql}
    ORDER BY vi.data_visita_vis DESC, vi.id_visita DESC
    LIMIT :limit OFFSET :offset
  ";

  $stmt = $conn->prepare($sql);
  ajax_bind_params($stmt, array_merge([
    ':pacId' => (int)$pacId,
    ':limit' => (int)$limit,
    ':offset' => (int)$offset,
  ], $scopeParams));
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // formatação de campos
  $fmtDateTime = function ($d) {
    if (!$d || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '';
    $ts = strtotime($d);
    if (!$ts) return '';
    return date('d/m/Y H:i', $ts);
  };

  $payload = array_map(function($r) use ($fmtDateTime) {
    // resumo curto do relatório
    $rel = trim((string)($r['rel_visita_vis'] ?? ''));
    if (preg_match('/^(Importado do OCR do PDF|Complementado via OCR)/i', $rel)) {
      $rel = '';
    }
    $resumo = mb_substr(preg_replace('/\s+/', ' ', $rel), 0, 120);
    if (mb_strlen($rel) > 120) $resumo .= '…';

    return [
      'id_visita'    => (int)$r['id_visita'],
      'id_internacao'=> (int)$r['id_internacao'],
      'data'         => $fmtDateTime($r['data_visita_vis'] ?? null),
      'responsavel'  => (string)($r['usuario_create'] ?? ''), // ajuste se quiser pegar nome do usuário via join
      'hospital'     => (string)($r['nome_hosp'] ?? ''),
      'visita_no'    => (int)($r['visita_no_vis'] ?? 0),
      'resumo'       => $resumo,
    ];
  }, $rows ?: []);

  echo json_encode([
    'success' => true,
    'total'   => $total,
    'page'    => $page,
    'limit'   => $limit,
    'rows'    => $payload
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error'   => 'Erro interno',
  ]);
  exit;
}
