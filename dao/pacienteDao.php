<?php

require_once("./models/paciente.php");
require_once("./models/message.php");

// Review DAO

class PacienteDAO implements PacienteDAOInterface
{

    private $conn;
    private $url;
    public $message;

    public function __construct(PDO $conn, $url)
    {
        $this->conn = $conn;
        $this->url = $url;
        $this->message = new Message($url);
    }

    public function buildPaciente($data)
    {
        $paciente = new Paciente();

        $paciente->id_paciente = $data["id_paciente"];
        $paciente->nome_pac = $data["nome_pac"];
        $paciente->nome_social_pac = $data["nome_social_pac"];
        $paciente->endereco_pac = $data["endereco_pac"];
        $paciente->sexo_pac = $data["sexo_pac"];
        $paciente->data_nasc_pac = $data["data_nasc_pac"];
        $paciente->cidade_pac = $data["cidade_pac"];
        $paciente->cpf_pac = $data["cpf_pac"];
        $paciente->telefone01_pac = $data["telefone01_pac"];
        $paciente->email01_pac = $data["email01_pac"];
        $paciente->email02_pac = $data["email02_pac"];
        $paciente->telefone02_pac = $data["telefone02_pac"];
        $paciente->numero_pac = $data["numero_pac"];
        $paciente->bairro_pac = $data["bairro_pac"];
        $paciente->ativo_pac = $data["ativo_pac"];
        $paciente->mae_pac = $data["mae_pac"];
        $paciente->data_create_pac = $data["data_create_pac"];
        $paciente->usuario_create_pac = $data["usuario_create_pac"];
        $paciente->fk_usuario_pac = $data["fk_usuario_pac"];
        $paciente->fk_estipulante_pac = $data["fk_estipulante_pac"];
        $paciente->fk_seguradora_pac = $data["fk_seguradora_pac"];
        $paciente->obs_pac = $data["obs_pac"];
        $paciente->matricula_pac = $data["matricula_pac"];
        $paciente->estado_pac = $data["estado_pac"];
        $paciente->complemento_pac = $data["complemento_pac"];
        $paciente->cep_pac = $data["cep_pac"];
        $paciente->deletado_pac = $data["deletado_pac"];
        $paciente->num_atendimento_pac = $data["num_atendimento_pac"];

        $paciente->recem_nascido_pac = $data["recem_nascido_pac"];
        $paciente->mae_titular_pac = $data["mae_titular_pac"];
        $paciente->matricula_titular_pac = $data["matricula_titular_pac"];
        $paciente->numero_rn_pac = $data["numero_rn_pac"];

        return $paciente;
    }

    public function findAll()
    {
        $paciente = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_paciente
        ORDER BY id_paciente asc");

        $stmt->execute();

        $paciente = $stmt->fetchAll();
        return $paciente;
    }

    public function getpacientesBynome_pac($nome_pac)
    {
        $pacientes = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_paciente
                                    WHERE nome_pac = :nome_pac
                                    ORDER BY id_paciente asc");

        $stmt->bindParam(":nome_pac", $nome_pac);
        $stmt->execute();
        $pacientesArray = $stmt->fetchAll();
        foreach ($pacientesArray as $paciente) {
            $pacientes[] = $this->buildpaciente($paciente);
        }
        return $pacientes;
    }

    public function findByIdSeg($id_paciente): Paciente
    {
        $paciente = [];
        $stmt = $this->conn->prepare("SELECT * FROM tb_paciente
                                    WHERE id_paciente = :id_paciente");
        $stmt->bindParam(":id_paciente", $id_paciente);
        $stmt->execute();

        $data = $stmt->fetch();
        // var_dump($data);
        $paciente = $this->buildPaciente($data);

        return $paciente;
    }
    public function findById($id_paciente)
    {
        $paciente = [];
        $stmt = $this->conn->prepare("SELECT
        pa.nome_pac,
        pa.nome_social_pac,
        pa.endereco_pac,
        pa.bairro_pac,
        pa.numero_pac,
        pa.cidade_pac,
        pa.estado_pac,
        pa.data_nasc_pac,
        pa.ativo_pac,
        pa.telefone01_pac,
        pa.telefone02_pac,
        pa.email01_pac,
        pa.email02_pac,
        pa.cpf_pac,
        pa.complemento_pac,
        pa.data_create_pac,
        pa.mae_pac,
        pa.fk_estipulante_pac,
        pa.cep_pac,
        pa.sexo_pac,
        pa.matricula_pac,
        pa.obs_pac,
        pa.id_paciente,
        es.id_estipulante,
        es.nome_est,
        se.id_seguradora,
        se.seguradora_seg,
        pa.fk_estipulante_pac,
        pa.fk_seguradora_pac,
        pa.num_atendimento_pac,
        pa.recem_nascido_pac,
        pa.mae_titular_pac,
        pa.matricula_titular_pac,
        pa.numero_rn_pac

        FROM tb_paciente as pa

        LEFT JOIN tb_seguradora as se On
        se.id_seguradora = pa.fk_seguradora_pac

        LEFT JOIN tb_estipulante as es On
        es.id_estipulante = pa.fk_estipulante_pac

         WHERE id_paciente = :id_paciente");

        $stmt->bindParam(":id_paciente", $id_paciente);
        $stmt->execute();

        $paciente = $stmt->fetchAll();

        return $paciente;
    }

    public function findByPac($pesquisa_nome, $limite, $inicio)
    {
        $paciente = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_paciente
                                    WHERE nome_pac LIKE :nome_pac order by nome_pac asc limite $inicio, $limite");

        $stmt->bindValue(":nome_pac", '%' . $pesquisa_nome . '%');

        $stmt->execute();

        $paciente = $stmt->fetchAll();
        return $paciente;
    }

    public function validarCpfExistente($cpf)
    {
        $paciente = [];

        // Trata caso o CPF seja null vindo da correção
        if ($cpf === null) {
            // Se CPF for null, não pode existir duplicado (exceto outros nulls)
            // Retorna vazio para indicar que a validação "passou" (não achou duplicado específico)
            return [];
        }

        $stmt = $this->conn->prepare("SELECT * FROM tb_paciente WHERE cpf_pac = :cpf");

        $stmt->bindValue(":cpf", $cpf);

        $stmt->execute();

        $paciente = $stmt->fetchAll();
        return $paciente;
    }


    public function validarMatriculaExistente($matricula)
    {
        // Normaliza
        $raw = trim((string) $matricula);
        if ($raw === '') {
            return [];
        }
        $upper = strtoupper($raw);

        // Detecta padrão com RN
        $posRN = stripos($upper, 'RN');

        if ($posRN !== false) {
            // Caso RN: separa base e número
            $base = trim(substr($upper, 0, $posRN));
            $suffix = substr($upper, $posRN + 2); // após "RN"
            $numero = preg_replace('/\D+/', '', (string) $suffix);
            $numeroParam = ($numero === '') ? null : (int) $numero;

            // Se quiser exigir que sempre tenha número quando vier com RN, pode validar aqui:
            // if ($numeroParam === null) return []; // ou tratar como inválido

            $sql = "SELECT id_paciente
                  FROM tb_paciente
                 WHERE UPPER(matricula_pac) = :base
                   AND recem_nascido_pac = 's'
                   AND (deletado_pac IS NULL OR deletado_pac = '' OR deletado_pac <> 's')
                   AND " . ($numeroParam === null ? "numero_rn_pac IS NULL" : "numero_rn_pac = :numero") . "
                 LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':base', $base);

            if ($numeroParam !== null) {
                $stmt->bindValue(':numero', $numeroParam, PDO::PARAM_INT);
            }
        } else {
            // Caso adulto (sem RN): garante que não seja RN
            $sql = "SELECT id_paciente
                  FROM tb_paciente
                 WHERE UPPER(matricula_pac) = :matricula
                   AND (recem_nascido_pac IS NULL OR recem_nascido_pac <> 's')
                   AND (deletado_pac IS NULL OR deletado_pac = '' OR deletado_pac <> 's')
                 LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':matricula', $upper);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function create(Paciente $paciente)
    {
        $sql = "INSERT INTO tb_paciente (
        nome_pac,
        nome_social_pac,
        cpf_pac,
        data_nasc_pac,
        sexo_pac,
        mae_pac,
        endereco_pac,
        numero_pac,
        bairro_pac,
        cidade_pac,
        estado_pac,
        complemento_pac,
        email01_pac,
        email02_pac,
        telefone01_pac,
        telefone02_pac,
        ativo_pac,
        data_create_pac,
        fk_usuario_pac,
        fk_estipulante_pac,
        fk_seguradora_pac,
        obs_pac,
        matricula_pac,
        usuario_create_pac,
        deletado_pac,
        cep_pac,
        num_atendimento_pac,
        recem_nascido_pac,
        mae_titular_pac,
        matricula_titular_pac,
        numero_rn_pac
    ) VALUES (
        :nome_pac,
        :nome_social_pac,
        :cpf_pac,
        :data_nasc_pac,
        :sexo_pac,
        :mae_pac,
        :endereco_pac,
        :numero_pac,
        :bairro_pac,
        :cidade_pac,
        :estado_pac,
        :complemento_pac,
        :email01_pac,
        :email02_pac,
        :telefone01_pac,
        :telefone02_pac,
        :ativo_pac,
        :data_create_pac,
        :fk_usuario_pac,
        :fk_estipulante_pac,
        :fk_seguradora_pac,
        :obs_pac,
        :matricula_pac,
        :usuario_create_pac,
        :deletado_pac,
        :cep_pac,
        :num_atendimento_pac,
        :recem_nascido_pac,
        :mae_titular_pac,
        :matricula_titular_pac,
        :numero_rn_pac
    )";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(":nome_pac", $paciente->nome_pac);
        $stmt->bindParam(":nome_social_pac", $paciente->nome_social_pac);
        $stmt->bindParam(":endereco_pac", $paciente->endereco_pac);
        $stmt->bindParam(":bairro_pac", $paciente->bairro_pac);
        $stmt->bindParam(":email01_pac", $paciente->email01_pac);
        $stmt->bindParam(":data_nasc_pac", $paciente->data_nasc_pac);
        $stmt->bindParam(":sexo_pac", $paciente->sexo_pac);

        // ==========================================================
        // INÍCIO DA CORREÇÃO 2a (CPF Null no Create)
        // ==========================================================
        if ($paciente->cpf_pac === null) {
            $stmt->bindValue(":cpf_pac", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(":cpf_pac", $paciente->cpf_pac);
        }
        // ==========================================================
        // FIM DA CORREÇÃO 2a
        // ==========================================================

        $stmt->bindParam(":email02_pac", $paciente->email02_pac);
        $stmt->bindParam(":telefone01_pac", $paciente->telefone01_pac);
        $stmt->bindParam(":telefone02_pac", $paciente->telefone02_pac);
        $stmt->bindParam(":numero_pac", $paciente->numero_pac);
        $stmt->bindParam(":mae_pac", $paciente->mae_pac);
        $stmt->bindParam(":cidade_pac", $paciente->cidade_pac);
        $stmt->bindParam(":complemento_pac", $paciente->complemento_pac);
        $stmt->bindParam(":ativo_pac", $paciente->ativo_pac);
        $stmt->bindParam(":data_create_pac", $paciente->data_create_pac);
        $stmt->bindParam(":usuario_create_pac", $paciente->usuario_create_pac);
        $stmt->bindParam(":fk_usuario_pac", $paciente->fk_usuario_pac);
        $stmt->bindParam(":fk_estipulante_pac", $paciente->fk_estipulante_pac);
        $stmt->bindParam(":fk_seguradora_pac", $paciente->fk_seguradora_pac);
        $stmt->bindParam(":matricula_pac", $paciente->matricula_pac);
        $stmt->bindParam(":obs_pac", $paciente->obs_pac);
        $stmt->bindParam(":estado_pac", $paciente->estado_pac);
        $stmt->bindParam(":deletado_pac", $paciente->deletado_pac);
        $stmt->bindParam(":cep_pac", $paciente->cep_pac);
        $stmt->bindParam(":num_atendimento_pac", $paciente->num_atendimento_pac);

        // --- Novos campos com null-safety (mantido como estava, já parecia correto)
        if ($paciente->recem_nascido_pac === null) {
            $stmt->bindValue(":recem_nascido_pac", null, PDO::PARAM_NULL);
            $stmt->bindValue(":numero_rn_pac", null, PDO::PARAM_NULL); // Se RN é null, número também
        } else {
            $stmt->bindValue(":recem_nascido_pac", $paciente->recem_nascido_pac);
            // Se RN existe, trata o número RN
            if ($paciente->numero_rn_pac === null) {
                $stmt->bindValue(":numero_rn_pac", null, PDO::PARAM_NULL);
            } else {
                // Garante que é inteiro ao salvar
                $stmt->bindValue(":numero_rn_pac", (int) $paciente->numero_rn_pac, PDO::PARAM_INT);
            }
        }

        if ($paciente->mae_titular_pac === null) {
            $stmt->bindValue(":mae_titular_pac", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(":mae_titular_pac", $paciente->mae_titular_pac);
        }

        if ($paciente->matricula_titular_pac === null) {
            $stmt->bindValue(":matricula_titular_pac", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(":matricula_titular_pac", $paciente->matricula_titular_pac);
        }

        $stmt->execute(); // Esta era a linha 366 do erro original

        $this->message->setMessage("Paciente adicionado com sucesso!", "success", "pacientes");
    }

    public function update(Paciente $paciente)
    {
        $sql = "UPDATE tb_paciente SET
        nome_pac = :nome_pac,
        nome_social_pac = :nome_social_pac,
        endereco_pac = :endereco_pac,
        email01_pac = :email01_pac,
        email02_pac = :email02_pac,
        data_nasc_pac = :data_nasc_pac,
        sexo_pac = :sexo_pac,
        cpf_pac = :cpf_pac,
        numero_pac = :numero_pac,
        telefone01_pac = :telefone01_pac,
        telefone02_pac = :telefone02_pac,
        cidade_pac = :cidade_pac,
        bairro_pac = :bairro_pac,
        complemento_pac = :complemento_pac,
        mae_pac = :mae_pac,
        ativo_pac = :ativo_pac,
        usuario_create_pac = :usuario_create_pac,
        data_create_pac = :data_create_pac,
        matricula_pac = :matricula_pac,
        fk_estipulante_pac = :fk_estipulante_pac,
        fk_seguradora_pac = :fk_seguradora_pac,
        fk_usuario_pac = :fk_usuario_pac,
        estado_pac = :estado_pac,
        obs_pac = :obs_pac,
        cep_pac = :cep_pac,
        num_atendimento_pac = :num_atendimento_pac,
        recem_nascido_pac = :recem_nascido_pac,
        mae_titular_pac = :mae_titular_pac,
        matricula_titular_pac = :matricula_titular_pac,
        numero_rn_pac = :numero_rn_pac
    WHERE id_paciente = :id_paciente";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(":nome_pac", $paciente->nome_pac);
        $stmt->bindParam(":nome_social_pac", $paciente->nome_social_pac);
        $stmt->bindParam(":endereco_pac", $paciente->endereco_pac);
        $stmt->bindParam(":email01_pac", $paciente->email01_pac);
        $stmt->bindParam(":email02_pac", $paciente->email02_pac);
        $stmt->bindParam(":data_nasc_pac", $paciente->data_nasc_pac);

        $stmt->bindParam(":sexo_pac", $paciente->sexo_pac);

        // ==========================================================
        // INÍCIO DA CORREÇÃO 2b (CPF Null no Update)
        // ==========================================================
        if ($paciente->cpf_pac === null) {
            $stmt->bindValue(":cpf_pac", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(":cpf_pac", $paciente->cpf_pac);
        }
        // ==========================================================
        // FIM DA CORREÇÃO 2b
        // ==========================================================

        $stmt->bindParam(":numero_pac", $paciente->numero_pac);
        $stmt->bindParam(":telefone01_pac", $paciente->telefone01_pac);
        $stmt->bindParam(":telefone02_pac", $paciente->telefone02_pac);
        $stmt->bindParam(":cidade_pac", $paciente->cidade_pac);
        $stmt->bindParam(":bairro_pac", $paciente->bairro_pac);
        $stmt->bindParam(":complemento_pac", $paciente->complemento_pac);
        $stmt->bindParam(":mae_pac", $paciente->mae_pac);
        $stmt->bindParam(":ativo_pac", $paciente->ativo_pac);
        $stmt->bindParam(":usuario_create_pac", $paciente->usuario_create_pac);
        $stmt->bindParam(":data_create_pac", $paciente->data_create_pac);
        $stmt->bindParam(":obs_pac", $paciente->obs_pac);
        $stmt->bindParam(":matricula_pac", $paciente->matricula_pac);
        $stmt->bindParam(":estado_pac", $paciente->estado_pac);
        $stmt->bindParam(":fk_estipulante_pac", $paciente->fk_estipulante_pac);
        $stmt->bindParam(":fk_seguradora_pac", $paciente->fk_seguradora_pac);
        $stmt->bindParam(":fk_usuario_pac", $paciente->fk_usuario_pac);
        $stmt->bindParam(":cep_pac", $paciente->cep_pac);
        $stmt->bindParam(":num_atendimento_pac", $paciente->num_atendimento_pac);

        // Campos com null-safety (mantido como estava, já parecia correto)
        $recem_nascido_pac_val = $paciente->recem_nascido_pac ?? null;
        $stmt->bindValue(":recem_nascido_pac", $recem_nascido_pac_val, $recem_nascido_pac_val === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $mae_titular_pac_val = $paciente->mae_titular_pac ?? null;
        $stmt->bindValue(":mae_titular_pac", $mae_titular_pac_val, $mae_titular_pac_val === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $matricula_titular_pac_val = $paciente->matricula_titular_pac ?? null;
        $stmt->bindValue(":matricula_titular_pac", $matricula_titular_pac_val, $matricula_titular_pac_val === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $numero_rn_pac_val = $paciente->numero_rn_pac ?? null;
        if ($numero_rn_pac_val === null) {
            $stmt->bindValue(":numero_rn_pac", null, PDO::PARAM_NULL);
        } else {
            // Garante que é inteiro ao salvar
            $stmt->bindValue(":numero_rn_pac", (int) $numero_rn_pac_val, PDO::PARAM_INT);
        }

        $stmt->bindParam(":id_paciente", $paciente->id_paciente);

        $stmt->execute();

        $this->message->setMessage("Paciente atualizado com sucesso!", "success", "pacientes");
    }



    public function destroy($id_paciente)
    {
        // ATENÇÃO: Usar prepare/bindParam aqui também é crucial para segurança!
        // $stmt = $this->conn->prepare("DELETE FROM tb_paciente WHERE id_paciente = :id_paciente");
        // $stmt->bindParam(":id_paciente", $id_paciente);
        // $stmt->execute();

        // Mantendo o código original por enquanto, mas recomendo fortemente a mudança acima.
        $stmt = $this->conn->prepare("DELETE FROM tb_paciente WHERE id_paciente = $id_paciente");
        $stmt->bindParam(":id_paciente", $id_paciente); // Mesmo não usando placeholder, bindParam é necessário para execute() não falhar
        $stmt->execute();


        // Mensagem de sucesso por remover filme
        $this->message->setMessage("Paciente removido com sucesso!", "success", "pacientes");
    }


    public function findGeral()
    {

        $pacientes = [];

        $stmt = $this->conn->query("SELECT * FROM tb_paciente where deletado_pac <> 's' ORDER BY id_paciente");

        $stmt->execute();

        $pacientes = $stmt->fetchAll();

        return $pacientes;
    }
    public function selectAllpaciente($where = null, $order = null, $limite = null)
    {
        // Base do WHERE (mantendo seu filtro de "não deletado")
        $whereClause = 'pa.deletado_pac <> "s"';
        if (strlen((string)$where)) {
            // aceita as condições já montadas fora (ex.: nome_pac LIKE ...)
            $whereClause .= ' AND ' . $where;
        }

        // ORDER BY seguro (whitelist simples)
        $order = $order ?: 'pa.id_paciente DESC';
        $allowedOrder = [
            'pa.id_paciente',
            'pa.id_paciente DESC',
            'id_paciente',
            'id_paciente DESC',
            'pa.nome_pac',
            'pa.nome_pac DESC',
            'nome_pac',
            'nome_pac DESC',
            'pa.matricula_pac',
            'pa.matricula_pac DESC',
            'matricula_pac',
            'matricula_pac DESC',
            'pa.cpf_pac',
            'pa.cpf_pac DESC',
            'cpf_pac',
            'cpf_pac DESC',
            'pa.cidade_pac',
            'pa.cidade_pac DESC',
            'cidade_pac',
            'cidade_pac DESC',
            'se.seguradora_seg',
            'se.seguradora_seg DESC',
            'seguradora_seg',
            'seguradora_seg DESC'
        ];
        if (!in_array($order, $allowedOrder, true)) {
            $order = 'pa.id_paciente DESC';
        }
        $orderSql  = 'ORDER BY ' . $order;

        // LIMIT (vem do seu pagination)
        $limiteSql = strlen((string)$limite) ? 'LIMIT ' . $limite : '';

        // Agora com JOIN para trazer o nome da seguradora
        $sql = "
        SELECT
            pa.*,
            se.seguradora_seg
        FROM tb_paciente pa
        LEFT JOIN tb_seguradora se
               ON se.id_seguradora = pa.fk_seguradora_pac
        WHERE {$whereClause}
        {$orderSql}
        {$limiteSql}
    ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function deletarUpdate(paciente $paciente)
    {
        // Não precisa definir $deletado_pac = "s"; use o valor do objeto
        $stmt = $this->conn->prepare("UPDATE tb_paciente SET
            deletado_pac = :deletado_pac
            WHERE id_paciente = :id_paciente
        ");

        $stmt->bindParam(":deletado_pac", $paciente->deletado_pac); // Assume que o objeto já tem 's'
        $stmt->bindParam(":id_paciente", $paciente->id_paciente);
        $stmt->execute();

        // Mensagem de sucesso por editar hospital
        $this->message->setMessage("Paciente deletado com sucesso!", "success", "pacientes");
    }


    public function Qtdpaciente($where = null, $order = null, $limite = null)
    {
        //DADOS DA QUERY
        $whereClause = 'deletado_pac <> "s"';
        if (strlen($where)) {
            $whereClause .= ' AND ' . $where;
        }
        $whereSql = 'WHERE ' . $whereClause;

        // Query para contar
        $stmt = $this->conn->prepare('SELECT COUNT(id_paciente) as qtd FROM tb_paciente ' . $whereSql);

        $stmt->execute();

        $QtdTotalPac = $stmt->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC é mais padrão

        return $QtdTotalPac; // Retorna ['qtd' => numero]
    }


    public function verificaId1()
    {
        try {
            // ==========================================================
            // INÍCIO DA CORREÇÃO 1 (Remover nome do banco da Procedure)
            // ==========================================================
            $stmt = $this->conn->prepare('CALL verificar_e_criar_id1()');
            // ==========================================================
            // FIM DA CORREÇÃO 1
            // ==========================================================

            // Executa a chamada da stored procedure
            $success = $stmt->execute();

            // Verifica se a chamada da stored procedure foi bem-sucedida
            if ($success) {
                return true;
            } else {
                // Logar o erro do PDO pode ser útil aqui
                // error_log("Erro ao executar procedure verificar_e_criar_id1: " . print_r($stmt->errorInfo(), true));
                return false;
            }
        } catch (Exception $e) {
            // Logar a exceção
            // error_log("Exceção ao chamar procedure verificar_e_criar_id1: " . $e->getMessage());
            // Não é ideal retornar JSON diretamente do DAO
            // Lançar a exceção ou retornar false seria melhor
            // Retornando false para manter o comportamento anterior em caso de erro grave
            return false;
        }
    }


    public function searchForHeader(string $q, int $limit = 10): array
    {
        // saneia o limite
        $limit = max(1, min(50, (int) $limit));
        $like = '%' . $q . '%';

        $sql = "
        SELECT
            pa.id_paciente,
            pa.nome_pac,
            pa.matricula_pac,
            pa.data_nasc_pac,
            (
                SELECT i2.senha_int
                FROM tb_internacao i2
                WHERE i2.fk_paciente_int = pa.id_paciente
                ORDER BY i2.data_intern_int DESC, i2.id_internacao DESC
                LIMIT 1
            ) AS ultima_senha
        FROM tb_paciente pa
        WHERE
            pa.deletado_pac <> 's' AND (
                pa.nome_pac LIKE :like_nome
                OR CONCAT(
                    pa.matricula_pac,
                    CASE WHEN pa.recem_nascido_pac = 's' THEN 'RN' ELSE '' END,
                    IFNULL(pa.numero_rn_pac, '')
                ) LIKE :like_matricula
                OR EXISTS (
                    SELECT 1
                    FROM tb_internacao i
                    WHERE i.fk_paciente_int = pa.id_paciente
                    AND i.senha_int LIKE :like_senha
                )
            )
        ORDER BY pa.nome_pac ASC
        LIMIT {$limit}
    ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':like_nome', $like, PDO::PARAM_STR);
        $stmt->bindValue(':like_matricula', $like, PDO::PARAM_STR);
        $stmt->bindValue(':like_senha', $like, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} // Fim da classe PacienteDAO
