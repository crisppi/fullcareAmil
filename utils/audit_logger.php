<?php

require_once(__DIR__ . '/../models/auditLog.php');
require_once(__DIR__ . '/../dao/auditLogDao.php');

if (!function_exists('fullcareAuditDao')) {
    function fullcareAuditDao(PDO $conn, string $baseUrl = ''): AuditLogDAO
    {
        static $instances = [];
        $key = spl_object_hash($conn);
        if (!isset($instances[$key])) {
            $instances[$key] = new AuditLogDAO($conn, $baseUrl);
        }
        return $instances[$key];
    }
}

if (!function_exists('fullcareAuditExcerpt')) {
    function fullcareAuditExcerpt($value, int $max = 280): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 3, 'UTF-8')) . '...';
    }
}

if (!function_exists('fullcareAuditNormalizeValue')) {
    function fullcareAuditNormalizeValue($value)
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_object($item) || is_array($item)) {
                $normalized[$key] = fullcareAuditNormalizeValue($item);
                continue;
            }

            if (is_string($item)) {
                $normalized[$key] = trim($item);
                continue;
            }

            $normalized[$key] = $item;
        }
        return $normalized;
    }
}

if (!function_exists('fullcareAuditFilterSensitiveFields')) {
    function fullcareAuditFilterSensitiveFields(array $record): array
    {
        $hidden = [
            'senha_user',
            'senha',
            'password',
            'password_hash',
            'token',
            'reset_token',
        ];

        foreach ($hidden as $key) {
            if (array_key_exists($key, $record)) {
                $record[$key] = '[REDACTED]';
            }
        }

        return $record;
    }
}

if (!function_exists('fullcareAuditSnapshot')) {
    function fullcareAuditSnapshot($record, string $entityType = ''): ?array
    {
        if ($record === null) {
            return null;
        }

        $data = fullcareAuditNormalizeValue($record);
        if (!is_array($data)) {
            return ['value' => $data];
        }

        $data = fullcareAuditFilterSensitiveFields($data);

        $fieldsByEntity = [
            'internacao' => [
                'id_internacao',
                'fk_hospital_int',
                'fk_paciente_int',
                'fk_patologia_int',
                'fk_cid_int',
                'data_intern_int',
                'acomodacao_int',
                'internado_int',
                'senha_int',
                'grupo_patologia_int',
                'fk_usuario_int',
            ],
            'visita' => [
                'id_visita',
                'fk_internacao_vis',
                'fk_usuario_vis',
                'data_visita_vis',
                'visita_no_vis',
                'retificado',
                'faturado_vis',
            ],
            'alta' => [
                'id_alta',
                'fk_id_int_alt',
                'fk_usuario_alt',
                'data_alta_alt',
                'hora_alta_alt',
                'tipo_alta_alt',
                'internado_alt',
            ],
            'usuario' => [
                'id_usuario',
                'usuario_user',
                'login_user',
                'email_user',
                'email02_user',
                'ativo_user',
                'cargo_user',
                'nivel_user',
                'fk_seguradora_user',
            ],
            'login' => [
                'id_usuario',
                'usuario_user',
                'email_user',
                'cargo_user',
                'nivel_user',
            ],
            'paciente' => [
                'id_paciente',
                'nome_pac',
                'nome_social_pac',
                'cpf_pac',
                'matricula_pac',
                'fk_estipulante_pac',
                'fk_seguradora_pac',
                'num_atendimento_pac',
            ],
            'hospital' => [
                'id_hospital',
                'nome_hosp',
                'cnpj_hosp',
                'cidade_hosp',
                'estado_hosp',
                'ativo_hosp',
                'fk_usuario_hosp',
            ],
            'estipulante' => [
                'id_estipulante',
                'nome_est',
                'cnpj_est',
                'cidade_est',
                'estado_est',
                'fk_usuario_est',
                'nome_contato_est',
                'nome_responsavel_est',
            ],
            'seguradora' => [
                'id_seguradora',
                'seguradora_seg',
                'cnpj_seg',
                'cidade_seg',
                'estado_seg',
                'ativo_seg',
                'fk_usuario_seg',
            ],
            'acomodacao' => [
                'id_acomodacao',
                'acomodacao_aco',
                'valor_aco',
                'fk_hospital',
                'data_contrato_aco',
                'fk_usuario_acomodacao',
            ],
            'antecedente' => [
                'id_antecedente',
                'antecedente_ant',
                'fk_cid_10_ant',
                'fk_usuario_ant',
            ],
            'hospital_user' => [
                'id_hospitalUser',
                'fk_usuario_hosp',
                'fk_hospital_user',
            ],
            'patologia' => [
                'id_patologia',
                'patologia_pat',
                'dias_pato',
                'fk_cid_10_pat',
                'fk_usuario_pat',
            ],
            'censo' => [
                'id_censo',
                'fk_paciente_censo',
                'fk_hospital_censo',
                'data_censo',
                'senha_censo',
                'acomodacao_censo',
                'titular_censo',
            ],
            'gestao' => [
                'id_gestao',
                'fk_internacao_ges',
                'fk_visita_ges',
                'alto_custo_ges',
                'evento_adverso_ges',
                'opme_ges',
                'home_care_ges',
                'desospitalizacao_ges',
                'fk_user_ges',
            ],
            'negociacao' => [
                'id_negociacao',
                'fk_id_int',
                'troca_de',
                'troca_para',
                'qtd',
                'saving',
                'fk_usuario_neg',
            ],
            'prorrogacao' => [
                'id_prorrogacao',
                'fk_internacao_pror',
                'fk_visita_pror',
                'acomod1_pror',
                'prorrog1_ini_pror',
                'prorrog1_fim_pror',
                'diarias_1',
                'fk_usuario_pror',
            ],
            'tuss' => [
                'id_tuss',
                'fk_int_tuss',
                'fk_usuario_tuss',
                'tuss_solicitado',
                'data_realizacao_tuss',
                'qtd_tuss_solicitado',
                'qtd_tuss_liberado',
                'tuss_liberado_sn',
            ],
            'uti' => [
                'id_uti',
                'fk_internacao_uti',
                'fk_visita_uti',
                'data_internacao_uti',
                'data_alta_uti',
                'internado_uti',
                'criterios_uti',
                'especialidade_uti',
                'fk_user_uti',
            ],
            'capeante' => [
                'id_capeante',
                'fk_int_capeante',
                'parcial_capeante',
                'parcial_num',
                'data_inicial_capeante',
                'data_final_capeante',
                'valor_apresentado_capeante',
                'valor_final_capeante',
                'senha_finalizada',
                'em_auditoria_cap',
                'encerrado_cap',
                'fk_user_cap',
            ],
            'imagem' => [
                'id_imagem',
                'fk_imagem',
                'imagem_name_img',
            ],
            'solicitacao_customizacao' => [
                'id_solicitacao',
                'fk_usuario_solicitante',
                'nome',
                'empresa',
                'email',
                'prioridade',
                'status',
                'responsavel',
                'versao_sistema',
            ],
            'detalhes' => [
                'id_detalhes',
                'fk_vis_det',
                'fk_int_det',
                'curativo_det',
                'dieta_det',
                'nivel_consc_det',
                'oxig_det',
                'atb_det',
                'acamado_det',
            ],
        ];

        $fields = $fieldsByEntity[$entityType] ?? [];
        $snapshot = [];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $snapshot[$field] = $data[$field];
        }

        $textFields = [
            'rel_int',
            'acoes_int',
            'programacao_int',
            'rel_visita_vis',
            'acoes_int_vis',
            'programacao_enf',
            'obs_user',
            'obs_pac',
        ];
        foreach ($textFields as $field) {
            if (array_key_exists($field, $data)) {
                $excerpt = fullcareAuditExcerpt($data[$field], 220);
                if ($excerpt !== null) {
                    $snapshot[$field] = $excerpt;
                }
            }
        }

        if (!$snapshot) {
            foreach ($data as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $snapshot[$key] = is_string($value) ? fullcareAuditExcerpt($value, 220) : $value;
                }
            }
        }

        return $snapshot ?: null;
    }
}

if (!function_exists('fullcareAuditRecordLabel')) {
    function fullcareAuditRecordLabel(string $entityType, ?int $entityId, ?array $after = null, ?array $before = null): string
    {
        $source = $after ?: $before ?: [];
        $label = ucfirst($entityType);

        if ($entityType === 'internacao' && !empty($source['senha_int'])) {
            $label .= ' #' . (string)$source['senha_int'];
        } elseif ($entityType === 'visita' && !empty($source['visita_no_vis'])) {
            $label .= ' ' . (string)$source['visita_no_vis'];
        } elseif ($entityType === 'usuario' && !empty($source['usuario_user'])) {
            $label .= ' ' . (string)$source['usuario_user'];
        } elseif ($entityType === 'login' && !empty($source['usuario_user'])) {
            $label .= ' ' . (string)$source['usuario_user'];
        } elseif ($entityType === 'paciente' && !empty($source['nome_pac'])) {
            $label .= ' ' . (string)$source['nome_pac'];
        } elseif ($entityType === 'hospital' && !empty($source['nome_hosp'])) {
            $label .= ' ' . (string)$source['nome_hosp'];
        } elseif ($entityType === 'estipulante' && !empty($source['nome_est'])) {
            $label .= ' ' . (string)$source['nome_est'];
        } elseif ($entityType === 'seguradora' && !empty($source['seguradora_seg'])) {
            $label .= ' ' . (string)$source['seguradora_seg'];
        } elseif ($entityType === 'acomodacao' && !empty($source['acomodacao_aco'])) {
            $label .= ' ' . (string)$source['acomodacao_aco'];
        } elseif ($entityType === 'antecedente' && !empty($source['antecedente_ant'])) {
            $label .= ' ' . (string)$source['antecedente_ant'];
        } elseif ($entityType === 'hospital_user' && !empty($source['id_hospitalUser'])) {
            $label .= ' #' . (string)$source['id_hospitalUser'];
        } elseif ($entityType === 'patologia' && !empty($source['patologia_pat'])) {
            $label .= ' ' . (string)$source['patologia_pat'];
        } elseif ($entityType === 'censo' && !empty($source['senha_censo'])) {
            $label .= ' #' . (string)$source['senha_censo'];
        } elseif ($entityType === 'negociacao' && !empty($source['id_negociacao'])) {
            $label .= ' #' . (string)$source['id_negociacao'];
        } elseif ($entityType === 'prorrogacao' && !empty($source['id_prorrogacao'])) {
            $label .= ' #' . (string)$source['id_prorrogacao'];
        } elseif ($entityType === 'tuss' && !empty($source['tuss_solicitado'])) {
            $label .= ' ' . (string)$source['tuss_solicitado'];
        } elseif ($entityType === 'uti' && !empty($source['id_uti'])) {
            $label .= ' #' . (string)$source['id_uti'];
        } elseif ($entityType === 'capeante' && !empty($source['id_capeante'])) {
            $label .= ' #' . (string)$source['id_capeante'];
        } elseif ($entityType === 'imagem' && !empty($source['imagem_name_img'])) {
            $label .= ' ' . (string)$source['imagem_name_img'];
        } elseif ($entityType === 'solicitacao_customizacao' && !empty($source['nome'])) {
            $label .= ' ' . (string)$source['nome'];
        } elseif ($entityType === 'detalhes' && !empty($source['id_detalhes'])) {
            $label .= ' #' . (string)$source['id_detalhes'];
        }

        if ($entityId) {
            $label .= ' (ID ' . $entityId . ')';
        }

        return $label;
    }
}

if (!function_exists('fullcareAuditEntityHeadline')) {
    function fullcareAuditEntityHeadline(string $entityType, ?array $after = null, ?array $before = null): ?string
    {
        $source = $after ?: $before ?: [];
        $map = [
            'usuario' => $source['usuario_user'] ?? null,
            'login' => $source['usuario_user'] ?? ($source['email_user'] ?? null),
            'paciente' => $source['nome_pac'] ?? null,
            'hospital' => $source['nome_hosp'] ?? null,
            'estipulante' => $source['nome_est'] ?? null,
            'seguradora' => $source['seguradora_seg'] ?? null,
            'acomodacao' => $source['acomodacao_aco'] ?? null,
            'antecedente' => $source['antecedente_ant'] ?? null,
            'hospital_user' => $source['id_hospitalUser'] ?? null,
            'patologia' => $source['patologia_pat'] ?? null,
            'censo' => $source['senha_censo'] ?? null,
            'gestao' => $source['id_gestao'] ?? null,
            'negociacao' => $source['id_negociacao'] ?? null,
            'prorrogacao' => $source['id_prorrogacao'] ?? null,
            'tuss' => $source['tuss_solicitado'] ?? null,
            'uti' => $source['id_uti'] ?? null,
            'capeante' => $source['id_capeante'] ?? null,
            'imagem' => $source['imagem_name_img'] ?? null,
            'solicitacao_customizacao' => $source['nome'] ?? null,
            'detalhes' => $source['id_detalhes'] ?? null,
            'internacao' => $source['senha_int'] ?? null,
            'visita' => $source['visita_no_vis'] ?? null,
        ];

        $headline = $map[$entityType] ?? null;
        if ($headline === null || $headline === '') {
            return null;
        }

        if ($entityType === 'internacao') {
            return 'Senha ' . $headline;
        }
        if ($entityType === 'visita') {
            return 'Visita ' . $headline;
        }
        if ($entityType === 'censo') {
            return 'Senha ' . $headline;
        }
        if (in_array($entityType, ['hospital_user', 'gestao', 'negociacao', 'prorrogacao', 'uti', 'capeante', 'detalhes'], true)) {
            return 'ID ' . $headline;
        }
        return (string)$headline;
    }
}

if (!function_exists('fullcareAuditActionLabel')) {
    function fullcareAuditActionLabel(string $action): string
    {
        $labels = [
            'create' => 'Criado',
            'update' => 'Atualizado',
            'delete' => 'Excluído',
            'soft_delete' => 'Marcado como excluído',
            'update.password' => 'Senha alterada',
            'login.success' => 'Login realizado',
        ];

        return $labels[$action] ?? $action;
    }
}

if (!function_exists('fullcareAuditSummary')) {
    function fullcareAuditSummary(string $action, string $entityType, ?array $after = null, ?array $before = null): string
    {
        $actionLabel = fullcareAuditActionLabel($action);
        $entityLabel = ucfirst($entityType);
        $headline = fullcareAuditEntityHeadline($entityType, $after, $before);

        if ($headline) {
            return $actionLabel . ': ' . $headline;
        }

        return $actionLabel . ': ' . $entityLabel;
    }
}

if (!function_exists('fullcareAuditActorContext')) {
    function fullcareAuditActorContext(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        return [
            'actor_user_id' => isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null,
            'actor_user_name' => $_SESSION['usuario_user'] ?? ($_SESSION['login_user'] ?? ($_SESSION['email_user'] ?? null)),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];
    }
}

if (!function_exists('fullcareAuditLog')) {
    function fullcareAuditLog(PDO $conn, array $payload, string $baseUrl = ''): ?int
    {
        try {
            $actor = fullcareAuditActorContext();
            $entityType = trim((string)($payload['entity_type'] ?? 'sistema'));
            $entityId = isset($payload['entity_id']) && $payload['entity_id'] !== '' ? (int)$payload['entity_id'] : null;
            $before = fullcareAuditSnapshot($payload['before'] ?? null, $entityType);
            $after = fullcareAuditSnapshot($payload['after'] ?? null, $entityType);
            $context = fullcareAuditNormalizeValue($payload['context'] ?? []);

            $audit = new AuditLog();
            $audit->action = trim((string)($payload['action'] ?? 'evento'));
            $audit->entity_type = $entityType;
            $audit->entity_id = $entityId;
            $audit->record_label = $payload['record_label'] ?? fullcareAuditRecordLabel($entityType, $entityId, $after, $before);
            $audit->actor_user_id = $payload['actor_user_id'] ?? $actor['actor_user_id'];
            $audit->actor_user_name = $payload['actor_user_name'] ?? $actor['actor_user_name'];
            $audit->ip_address = $payload['ip_address'] ?? $actor['ip_address'];
            $audit->trace_id = $payload['trace_id'] ?? null;
            $audit->source = $payload['source'] ?? (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) ?: 'app');
            $summary = $payload['summary'] ?? fullcareAuditSummary($audit->action, $entityType, $after, $before);
            $audit->summary = fullcareAuditExcerpt($summary, 500);
            $audit->before_json = $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $audit->after_json = $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $audit->context_json = $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

            return fullcareAuditDao($conn, $baseUrl)->create($audit);
        } catch (Throwable $e) {
            error_log('[AUDIT_LOG][WRITE] ' . $e->getMessage());
            return null;
        }
    }
}
