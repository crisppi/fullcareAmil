<?php

    require_once("./models/alta.php");
    require_once("./models/hospital.php");
    require_once("./models/internacao.php");
    require_once("./models/message.php");

    class altaDAO implements altaDAOInterface
    {

        private $conn;
        private $url;
        public $message;
        private $hasHoraAltaColumn = null;

        public function __construct(PDO $conn, $url)
        {
            $this->conn = $conn;
            $this->url = $url;
            $this->message = new Message($url);
        }

        private function ensureHoraAltaColumn()
        {
            if ($this->hasHoraAltaColumn !== null) {
                return $this->hasHoraAltaColumn;
            }

            try {
                $stmt = $this->conn->query("SHOW COLUMNS FROM tb_alta LIKE 'hora_alta_alt'");
                $this->hasHoraAltaColumn = $stmt && $stmt->fetch() ? true : false;
            } catch (Throwable $th) {
                $this->hasHoraAltaColumn = false;
            }

            return $this->hasHoraAltaColumn;
        }

        public function buildalta($data)
        {
            $alta = new alta();

            $alta->id_alta = $data["id_alta"];
            $alta->fk_id_int_alt = $data["fk_id_int_alt"];
            $alta->tipo_alta_alt = $data["tipo_alta_alt"];
            $alta->data_alta_alt = $data["data_alta_alt"];
            $alta->hora_alta_alt = $data["hora_alta_alt"] ?? null;
            $alta->internado_alt = $data["internado_alt"];
            $alta->usuario_alt = $data["usuario_alt"];
            $alta->data_create_alt = $data["data_create_alt"];
            $alta->fk_usuario_alt = $data["fk_usuario_alt"];

            return $alta;
        }

        // mostrar acomocacao por id_alta

        public function findById($id_alta)
        {
            $alta = [];
            $stmt = $this->conn->prepare("SELECT * FROM tb_alta
                                    WHERE id_alta = :id_alta");

            $stmt->bindParam(":id_alta", $id_alta);
            $stmt->execute();

            $data = $stmt->fetch();
            $alta = $this->buildalta($data);

            return $alta;
        }

        // METODO PARA CRIAR NOVA INTERNACAO EM ALTA ********** concluir *******
        public function create(alta $alta)
        {
            $hasHora = $this->ensureHoraAltaColumn();
            $sql = "INSERT INTO tb_alta (
        fk_id_int_alt,
        tipo_alta_alt, 
        internado_alt,
        usuario_alt,
        data_create_alt,
        data_alta_alt" . ($hasHora ? ",\n        hora_alta_alt" : "") . ",
        fk_usuario_alt
        
      ) VALUES (
        :fk_id_int_alt,
        :tipo_alta_alt, 
        :internado_alt,
        :usuario_alt,
        :data_create_alt,
        :data_alta_alt" . ($hasHora ? ",\n        :hora_alta_alt" : "") . ",
        :fk_usuario_alt

     )";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(":fk_id_int_alt", $alta->fk_id_int_alt);
            $stmt->bindParam(":tipo_alta_alt", $alta->tipo_alta_alt);
            $stmt->bindParam(":internado_alt", $alta->internado_alt);
            $stmt->bindParam(":data_alta_alt", $alta->data_alta_alt);
            if ($hasHora) {
                $stmt->bindParam(":hora_alta_alt", $alta->hora_alta_alt);
            }
            $stmt->bindParam(":usuario_alt", $alta->usuario_alt);
            $stmt->bindParam(":data_create_alt", $alta->data_create_alt);
            $stmt->bindParam(":fk_usuario_alt", $alta->fk_usuario_alt);

            $stmt->execute();

            // Mensagem de sucesso por adicionar alta
            // $this->message->setMessage("alta adicionado com sucesso!", "success", "list_internacao_alta.php");
        }

        public function findGeral()
        {

            $alta = [];

            $stmt = $this->conn->query("SELECT * FROM tb_alta ORDER BY id_alta DESC");

            $stmt->execute();

            $alta = $stmt->fetchAll();

            return $alta;
        }
        // pegar id max da internacao


        public function findAltaInternacao($where = null, $order = null, $limit = null)
        {

            $stmt = $this->conn->query("SELECT 
        ac.id_internacao, 
        ac.internado_int, 
        ac.data_intern_int, 
        ac.fk_paciente_int, 
        pa.id_paciente, 
        pa.nome_pac, 
        ac.usuario_create_int, 
        ac.fk_hospital_int, 
        ac.modo_internacao_int, 
        ac.tipo_admissao_int, 
        ho.id_hospital, 
        ho.nome_hosp, 
        ac.data_visita_int, 
        ac.acomodacao_int, 
        alta.fk_id_int_alt,
        alta.tipo_alta_alt, 
        alta.data_alta_alt 

        FROM tb_internacao ac 

        left JOIN tb_hospital as ho On  
        ac.fk_hospital_int = ho.id_hospital

        left join tb_paciente as pa on
        ac.fk_paciente_int = pa.id_paciente

        WHERE ac.id_internacao = $where
         ");

            $stmt->execute();

            $findaltaInternacao = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $findaltaInternacao;
        }
        public function QtdInternacaoAlta($where = null, $order = null, $limit = null)
        {
            $internacao = [];
            //DADOS DA QUERY
            $where = strlen($where) ? 'WHERE ' . $where : '';
            $order = strlen($order) ? 'ORDER BY ' . $order : '';
            $limit = strlen($limit) ? 'LIMIT ' . $limit : '';

            $stmt = $this->conn->query('SELECT 
        COUNT(id_alta) as qtd, 
        alta.id_alta,
        alta.fk_internacao_alta,
        alta.criterios_alta, 
        alta.data_alta_alta, 
        alta.dva_alta, 
        alta.data_internacao_alta, 
        alta.especialidade_alta, 
        alta.internacao_alta, 
        alta.internado_alta, 
        alta.just_alta,
        alta.motivo_alta,
        alta.rel_alta,
        alta.saps_alta,
        alta.score_alta,
        alta.vm_alta,
        pa.id_paciente,
        pa.nome_pac,
        ho.id_hospital, 
        ho.nome_hosp,
        ac.id_internacao,
        ac.fk_hospital_int,
        ac.data_intern_int,
        ac.internado_int,
        ac.internado_alta_int, 
        ac.internacao_alta_int, 
        ac.fk_paciente_int
        
            FROM tb_alta as alta 
    
            LEFT JOIN tb_internacao AS ac ON
            alta.fk_internacao_alta = ac.id_internacao
    
            INNER JOIN tb_paciente AS pa ON
            ac.fk_paciente_int = pa.id_paciente

            iNNER JOIN tb_hospital AS ho ON  
            ac.fk_hospital_int = ho.id_hospital ' . $where . ' ' . $order . ' ' . $limit);

            $stmt->execute();

            $QtdTotalInt = $stmt->fetch();

            return $QtdTotalInt;
        }

        public function findAltaWhere($where = null, $order = null, $limit = null)
        {
            $internacao = [];
            //DADOS DA QUERY
            $where = strlen($where) ? 'WHERE ' . $where : '';
            $order = strlen($order) ? 'ORDER BY ' . $order : '';
            $limit = strlen($limit) ? 'LIMIT ' . $limit : '';

            $stmt = $this->conn->query('SELECT alta.*, pa.*, ho.*, uti.* FROM tb_alta as alta LEFT JOIN tb_internacao AS ac ON alta.fk_id_int_alt = ac.id_internacao INNER JOIN tb_paciente AS pa ON ac.fk_paciente_int = pa.id_paciente iNNER JOIN tb_hospital AS ho ON  ac.fk_hospital_int = ho.id_hospital LEFT JOIN tb_uti uti ON uti.fk_internacao_uti = ac.id_internacao ' . $where . ' ' . $order . ' ' . $limit);

            $stmt->execute();

            $internacao = $stmt->fetchAll();

            return $internacao;
        }
        /**
         * Retorna internacoes vinculadas às altas informadas.
         * Exige que tb_alta tenha fk id_internacao.
         * @param int[] $altaIds
         * @return array<array{id_alta:int,id_internacao:int}>
         */
        public function getInternacoesByAltaIds(array $altaIds): array
        {
            if (!$altaIds) return [];
            $place = implode(',', array_fill(0, count($altaIds), '?'));
            $sql = "SELECT id_alta, id_internacao
              FROM tb_alta
             WHERE id_alta IN ($place)";

            $stmt = $this->conn->prepare($sql);
            foreach ($altaIds as $k => $id) {
                $stmt->bindValue($k + 1, (int)$id, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        /**
         * Marca as altas como 'REVERTIDA' (soft change) e registra auditoria básica.
         * Ajuste os campos conforme seu schema.
         */
        public function marcarAltasComoRevertidas(array $altaIds, int $userId): int
        {
            if (!$altaIds) return 0;

            $place = implode(',', array_fill(0, count($altaIds), '?'));
            $sql = "UPDATE tb_alta
               SET status_alt = 'REVERTIDA',
                   user_update = :user_id,
                   updated_at  = NOW()
             WHERE id_alta IN ($place)";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

            $pos = 1;
            foreach ($altaIds as $id) {
                $stmt->bindValue($pos++, (int)$id, PDO::PARAM_INT);
            }
            $stmt->execute();
            return (int)$stmt->rowCount();
        }

        /**
         * Reabre as internações afetadas, seguindo sua regra:
         * internado_int = 's' e data_alta_int = NULL.
         * (Ajuste outros campos caso exista, ex.: tipo_alta_int=NULL, obs_alta_int=NULL)
         */
        public function reabrirInternacoesFlag(array $internacaoIds, int $userId): int
        {
            if (!$internacaoIds) return 0;

            $place = implode(',', array_fill(0, count($internacaoIds), '?'));
            $sql = "UPDATE tb_internacao
               SET internado_int = 's',
                   data_alta_int  = NULL,
                   user_update    = :user_id,
                   updated_at     = NOW()
             WHERE id_internacao IN ($place)";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

            $pos = 1;
            foreach ($internacaoIds as $id) {
                $stmt->bindValue($pos++, (int)$id, PDO::PARAM_INT);
            }
            $stmt->execute();
            return (int)$stmt->rowCount();
        }
    }
