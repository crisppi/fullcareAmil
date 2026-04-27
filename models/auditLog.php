<?php

class AuditLog
{
    public $id_audit_log;
    public $created_at;
    public $action;
    public $entity_type;
    public $entity_id;
    public $record_label;
    public $actor_user_id;
    public $actor_user_name;
    public $ip_address;
    public $trace_id;
    public $source;
    public $summary;
    public $before_json;
    public $after_json;
    public $context_json;
}

interface AuditLogDAOInterface
{
    public function create(AuditLog $auditLog): int;
    public function findById(int $id): ?AuditLog;
    public function search(array $filters = [], int $limit = 200): array;
    public function count(array $filters = []): int;
}
