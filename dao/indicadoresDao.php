<?php

class indicadoresDAO
{
    private $conn;
    private $url;
    public $message;

    public function __construct(PDO $conn, $url)
    {
        $this->conn = $conn;
        $this->url  = $url;
        // Se Message não for necessário aqui, pode remover estas 2 linhas
        if (class_exists('Message')) {
            $this->message = new Message($url);
        }
    }

    /** Helpers para montar cláusulas de forma segura */
    private function where($where): string
    {
        return ($where !== null && trim($where) !== '') ? ' WHERE ' . $where : '';
    }
    private function andWhere($where): string
    {
        return ($where !== null && trim($where) !== '') ? ' AND ' . $where : '';
    }

    /** Retorna ex.: ['perc' => '12.34%'] */
    public function getUtiPerc($where)
    {
        // Fórmula: internações com UTI / total de internações * 100.
        // Usa DISTINCT para não duplicar internações com múltiplos registros em tb_uti.
        $sql = "
            SELECT CONCAT(
                TRUNCATE(
                    (
                        COUNT(DISTINCT CASE WHEN i.internado_int = 's' AND uti.fk_internacao_uti IS NOT NULL THEN i.id_internacao END)
                        /
                        NULLIF(COUNT(DISTINCT CASE WHEN i.internado_int = 's' THEN i.id_internacao END), 0)
                    ) * 100
                , 2),
                '%'
            ) AS perc
            FROM tb_internacao i
            LEFT JOIN tb_uti uti ON i.id_internacao = uti.fk_internacao_uti
        " . $this->where($where);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $perc_uti = $stmt->fetch(PDO::FETCH_ASSOC);

        return $perc_uti ?: ['perc' => '0.00%'];
    }

    /** Retorna count(*) como array (ex.: ['0' => '5']) */
    public function getDrgAcima($where)
    {
        $sql = "
            SELECT COUNT(*)
            FROM tb_internacao i
            JOIN tb_patologia p ON i.fk_patologia_int = p.id_patologia
            WHERE i.internado_int = 's'
              AND p.dias_pato > 1
              AND p.dias_pato < DATEDIFF(CURRENT_DATE, i.data_intern_int)
        " . $this->andWhere($where);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $drg = $stmt->fetch(PDO::FETCH_NUM);

        return $drg ?: ['0' => 0];
    }

    /** Retorna lista com id/nome/data/hospital; nunca injeta WHERE vazio */
    public function getLongaPermanencia($where)
    {
        $sql = "
            SELECT
                i.id_internacao,
                p.nome_pac,
                i.data_intern_int,
                hos.nome_hosp,
                s.dias_visita_seg,
                s.seguradora_seg
            FROM tb_internacao i
            JOIN tb_paciente  p   ON i.fk_paciente_int  = p.id_paciente
            JOIN tb_hospital  hos ON i.fk_hospital_int  = hos.id_hospital
            JOIN tb_seguradora s   ON p.fk_seguradora_pac = s.id_seguradora
            WHERE i.internado_int = 's'
              AND NOT EXISTS (
                    SELECT 1
                    FROM tb_alta al
                    WHERE al.fk_id_int_alt = i.id_internacao
                      AND al.data_alta_alt IS NOT NULL
                      AND al.data_alta_alt <> '0000-00-00'
              )
        " . $this->andWhere($where);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $longa = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $longa ?: [];
    }

    /** Retorna total de internações ativas com permanência acima de X dias */
    public function countLongaPermanenciaByDays($where, int $dias): int
    {
        $dias = max(0, (int)$dias);
        $sql = "
            SELECT COUNT(DISTINCT i.id_internacao) AS total
            FROM tb_internacao i
            WHERE i.internado_int = 's'
              AND NOT EXISTS (
                    SELECT 1
                    FROM tb_alta al
                    WHERE al.fk_id_int_alt = i.id_internacao
                      AND al.data_alta_alt IS NOT NULL
                      AND al.data_alta_alt <> '0000-00-00'
              )
              AND DATEDIFF(CURRENT_DATE(), i.data_intern_int) > :dias
        " . $this->andWhere($where);

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /** Retorna ['0' => total] – evita 'WHERE ' pendurado */
    public function getContasParadas($where)
    {
        $sql = "
            SELECT COUNT(*)
            FROM tb_capeante c
            JOIN tb_internacao i ON c.fk_int_capeante = i.id_internacao
        " . $this->where($where);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $contas_paradas = $stmt->fetch(PDO::FETCH_NUM);

        return $contas_paradas ?: ['0' => 0];
    }

    /** Retorna ['0' => total] – com cláusula base e AND dinâmico */
    public function getUtiPertinente($where)
    {
        $sql = "
            SELECT COUNT(*)
            FROM tb_internacao i
            JOIN tb_uti u ON u.fk_internacao_uti = i.id_internacao
            WHERE i.internado_int = 's'
              AND u.just_uti = 'Não pertinente'
        " . $this->andWhere($where);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_NUM);

        return $res ?: ['0' => 0];
    }

    /** Retorna ['0' => total] – score_uti < 0 ou não nulo */
    public function getScoreBaixo($where)
    {
        $sql = "
            SELECT COUNT(*)
            FROM tb_internacao i
            JOIN tb_uti u ON u.fk_internacao_uti = i.id_internacao
            WHERE i.internado_int = 's'
              AND u.score_uti < 0
              AND u.score_uti IS NOT NULL
        " . $this->andWhere($where);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_NUM);

        return $res ?: ['0' => 0];
    }
}
