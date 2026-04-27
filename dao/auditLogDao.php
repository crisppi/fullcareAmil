<?php

require_once("models/auditLog.php");

class AuditLogDAO implements AuditLogDAOInterface
{
    private PDO $conn;
    private string $url;

    public function __construct(PDO $conn, string $url)
    {
        $this->conn = $conn;
        $this->url = $url;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                  FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'tb_audit_log'
            ");
            $stmt->execute();
            $exists = (int)$stmt->fetchColumn() > 0;
            if ($exists) {
                return;
            }

            $this->conn->exec("
                CREATE TABLE tb_audit_log (
                    id_audit_log INT AUTO_INCREMENT PRIMARY KEY,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    action VARCHAR(60) NOT NULL,
                    entity_type VARCHAR(60) NOT NULL,
                    entity_id INT NULL,
                    record_label VARCHAR(255) NULL,
                    actor_user_id INT NULL,
                    actor_user_name VARCHAR(180) NULL,
                    ip_address VARCHAR(45) NULL,
                    trace_id VARCHAR(80) NULL,
                    source VARCHAR(120) NULL,
                    summary TEXT NULL,
                    before_json LONGTEXT NULL,
                    after_json LONGTEXT NULL,
                    context_json LONGTEXT NULL,
                    KEY idx_audit_created_at (created_at),
                    KEY idx_audit_actor_user_id (actor_user_id),
                    KEY idx_audit_entity (entity_type, entity_id),
                    KEY idx_audit_action (action),
                    KEY idx_audit_trace_id (trace_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            error_log('[AUDIT_LOG][SCHEMA] ' . $e->getMessage());
        }
    }

    private function buildAuditLog(array $data): AuditLog
    {
        $audit = new AuditLog();
        $audit->id_audit_log = isset($data['id_audit_log']) ? (int)$data['id_audit_log'] : null;
        $audit->created_at = $data['created_at'] ?? null;
        $audit->action = $data['action'] ?? null;
        $audit->entity_type = $data['entity_type'] ?? null;
        $audit->entity_id = isset($data['entity_id']) ? (int)$data['entity_id'] : null;
        $audit->record_label = $data['record_label'] ?? null;
        $audit->actor_user_id = isset($data['actor_user_id']) ? (int)$data['actor_user_id'] : null;
        $audit->actor_user_name = $data['actor_user_name'] ?? null;
        $audit->ip_address = $data['ip_address'] ?? null;
        $audit->trace_id = $data['trace_id'] ?? null;
        $audit->source = $data['source'] ?? null;
        $audit->summary = $data['summary'] ?? null;
        $audit->before_json = $data['before_json'] ?? null;
        $audit->after_json = $data['after_json'] ?? null;
        $audit->context_json = $data['context_json'] ?? null;
        return $audit;
    }

    public function create(AuditLog $auditLog): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO tb_audit_log (
                action,
                entity_type,
                entity_id,
                record_label,
                actor_user_id,
                actor_user_name,
                ip_address,
                trace_id,
                source,
                summary,
                before_json,
                after_json,
                context_json
            ) VALUES (
                :action,
                :entity_type,
                :entity_id,
                :record_label,
                :actor_user_id,
                :actor_user_name,
                :ip_address,
                :trace_id,
                :source,
                :summary,
                :before_json,
                :after_json,
                :context_json
            )
        ");

        $stmt->bindValue(':action', $auditLog->action);
        $stmt->bindValue(':entity_type', $auditLog->entity_type);
        $stmt->bindValue(':entity_id', $auditLog->entity_id, $auditLog->entity_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':record_label', $auditLog->record_label);
        $stmt->bindValue(':actor_user_id', $auditLog->actor_user_id, $auditLog->actor_user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':actor_user_name', $auditLog->actor_user_name);
        $stmt->bindValue(':ip_address', $auditLog->ip_address);
        $stmt->bindValue(':trace_id', $auditLog->trace_id);
        $stmt->bindValue(':source', $auditLog->source);
        $stmt->bindValue(':summary', $auditLog->summary);
        $stmt->bindValue(':before_json', $auditLog->before_json);
        $stmt->bindValue(':after_json', $auditLog->after_json);
        $stmt->bindValue(':context_json', $auditLog->context_json);
        $stmt->execute();

        return (int)$this->conn->lastInsertId();
    }

    public function findById(int $id): ?AuditLog
    {
        $stmt = $this->conn->prepare("
            SELECT *
              FROM tb_audit_log
             WHERE id_audit_log = :id
             LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->buildAuditLog($row) : null;
    }

    private function buildWhere(array $filters, array &$params): string
    {
        $clauses = [];

        if (!empty($filters['date_from'])) {
            $clauses[] = 'created_at >= :date_from';
            $params[':date_from'] = trim((string)$filters['date_from']) . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'created_at <= :date_to';
            $params[':date_to'] = trim((string)$filters['date_to']) . ' 23:59:59';
        }
        if (!empty($filters['action'])) {
            $clauses[] = 'action = :action';
            $params[':action'] = trim((string)$filters['action']);
        }
        if (!empty($filters['entity_type'])) {
            $clauses[] = 'entity_type = :entity_type';
            $params[':entity_type'] = trim((string)$filters['entity_type']);
        }
        if (!empty($filters['entity_id'])) {
            $clauses[] = 'entity_id = :entity_id';
            $params[':entity_id'] = (int)$filters['entity_id'];
        }
        if (!empty($filters['actor_user_id'])) {
            $clauses[] = 'actor_user_id = :actor_user_id';
            $params[':actor_user_id'] = (int)$filters['actor_user_id'];
        }
        if (!empty($filters['trace_id'])) {
            $clauses[] = 'trace_id = :trace_id';
            $params[':trace_id'] = trim((string)$filters['trace_id']);
        }
        if (!empty($filters['q'])) {
            $clauses[] = "(summary LIKE :q OR record_label LIKE :q OR actor_user_name LIKE :q OR source LIKE :q)";
            $params[':q'] = '%' . trim((string)$filters['q']) . '%';
        }

        return $clauses ? (' WHERE ' . implode(' AND ', $clauses)) : '';
    }

    public function search(array $filters = [], int $limit = 200): array
    {
        $params = [];
        $where = $this->buildWhere($filters, $params);
        $limit = max(10, min(1000, (int)$limit));

        $sql = "
            SELECT *
              FROM tb_audit_log
              {$where}
             ORDER BY created_at DESC, id_audit_log DESC
             LIMIT {$limit}
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $this->buildAuditLog($row);
        }
        return $rows;
    }

    public function count(array $filters = []): int
    {
        $params = [];
        $where = $this->buildWhere($filters, $params);
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
              FROM tb_audit_log
              {$where}
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
