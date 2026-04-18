<?php

require_once("./models/prorrogacao.php");
require_once("./models/hospital.php");
require_once("./models/message.php");

// Review DAO
require_once("dao/prorrogacaoDao.php");

class prorrogacaoDAO implements prorrogacaoDAOInterface
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

    public function buildprorrogacao($data)
    {
        $prorrogacao = new prorrogacao();

        if (!is_array($data) || empty($data)) {
            return $prorrogacao;
        }

        $prorrogacao->id_prorrogacao = $data["id_prorrogacao"] ?? null;

        $prorrogacao->acomod1_pror = $data["acomod1_pror"] ?? null;
        $prorrogacao->isol_1_pror = $data["isol_1_pror"] ?? null;
        $prorrogacao->prorrog1_ini_pror = $data["prorrog1_ini_pror"] ?? null;
        $prorrogacao->prorrog1_fim_pror = $data["prorrog1_fim_pror"] ?? null;
        $prorrogacao->diarias_1 = $data["diarias_1"] ?? null;

        $prorrogacao->fk_internacao_pror = $data["fk_internacao_pror"] ?? null;
        $prorrogacao->fk_usuario_pror = $data["fk_usuario_pror"] ?? null;
        $prorrogacao->fk_visita_pror = $data["fk_visita_pror"] ?? null;

        return $prorrogacao;
    }
    public function create(prorrogacao $prorrogacao)
    {
        if (($prorrogacao->fk_usuario_pror ?? null) !== null && (int)$prorrogacao->fk_usuario_pror > 0) {
            $stmtUser = $this->conn->prepare("SELECT 1 FROM tb_user WHERE id_usuario = :id LIMIT 1");
            $stmtUser->bindValue(':id', (int)$prorrogacao->fk_usuario_pror, PDO::PARAM_INT);
            $stmtUser->execute();
            if (!(bool)$stmtUser->fetchColumn()) {
                $prorrogacao->fk_usuario_pror = null;
            }
        }

        $stmt = $this->conn->prepare("INSERT INTO tb_prorrogacao (
            fk_internacao_pror,
            fk_visita_pror,
            acomod1_pror, 
            isol_1_pror, 
            prorrog1_fim_pror,
            prorrog1_ini_pror,
            fk_usuario_pror,
            diarias_1
        ) VALUES (
            :fk_internacao_pror,
            :fk_visita_pror,
            :acomod1_pror, 
            :isol_1_pror, 
            :prorrog1_fim_pror, 
            :prorrog1_ini_pror,
            :fk_usuario_pror,
            :diarias_1
        )");

        $stmt->bindParam(":acomod1_pror", $prorrogacao->acomod1_pror);
        $stmt->bindParam(":isol_1_pror", $prorrogacao->isol_1_pror);
        $stmt->bindParam(":prorrog1_ini_pror", $prorrogacao->prorrog1_ini_pror);
        $stmt->bindParam(":prorrog1_fim_pror", $prorrogacao->prorrog1_fim_pror);
        $stmt->bindParam(":fk_internacao_pror", $prorrogacao->fk_internacao_pror);
        $stmt->bindParam(":fk_visita_pror", $prorrogacao->fk_visita_pror);
        $stmt->bindParam(":fk_usuario_pror", $prorrogacao->fk_usuario_pror);
        $stmt->bindParam(":diarias_1", $prorrogacao->diarias_1);

        $stmt->execute();
    }
    public function joinprorrogacaoHospital()
    {

        $prorrogacao = [];

        $stmt = $this->conn->query("SELECT ac.id_prorrogacao, 
        ac.valor_aco, 
        ac.prorrogacao_aco, 
        ho.id_hospital, 
        ho.nome_hosp
         FROM tb_prorrogacao ac 
         iNNER JOIN tb_hospital as ho On  
         ac.fk_hospital = ho.id_hospital
         ORDER BY ac.id_prorrogacao DESC");
        $stmt->execute();
        $prorrogacao = $stmt->fetchAll();
        return $prorrogacao;
    }

    // mostrar acomocacao por id_prorrogacao
    public function joinprorrogacaoHospitalshow($id_prorrogacao)
    {
        $stmt = $this->conn->query("SELECT ac.id_prorrogacao, ac.fk_hospital, ac.valor_aco, ac.prorrogacao_aco, ho.id_hospital, ho.nome_hosp
         FROM tb_prorrogacao ac          
         iNNER JOIN tb_hospital as ho On  
         ac.fk_hospital = ho.id_hospital
         where id_prorrogacao = $id_prorrogacao   
         ");

        $stmt->execute();

        $prorrogacao = $stmt->fetch();
        return $prorrogacao;
    }
    public function findAll()
    {
    }


    public function findById($id_prorrogacao)
    {
        $prorrogacao = [];
        $stmt = $this->conn->prepare("SELECT * FROM tb_prorrogacao
                                    WHERE id_prorrogacao = :id_prorrogacao");

        $stmt->bindParam(":id_prorrogacao", $id_prorrogacao);
        $stmt->execute();

        $data = $stmt->fetch();
        $prorrogacao = $this->buildprorrogacao($data);

        return $prorrogacao;
    }

    /**
     * Recupera todas as prorrogações ligadas à internação (fallback).
     */
    public function selectRawByInternacao(int $id_internacao): array
    {
        if ($id_internacao <= 0) {
            return [];
        }
        $stmt = $this->conn->prepare("SELECT * FROM tb_prorrogacao WHERE fk_internacao_pror = :id ORDER BY id_prorrogacao DESC");
        $stmt->bindValue(':id', $id_internacao, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Recupera todas as prorrogações ligadas à visita.
     */
    public function selectByVisita(int $visitaId): array
    {
        if ($visitaId <= 0) {
            return [];
        }
        $stmt = $this->conn->prepare("SELECT * FROM tb_prorrogacao WHERE fk_visita_pror = :visita ORDER BY id_prorrogacao DESC");
        $stmt->bindValue(':visita', $visitaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Remove prorrogações associadas à visita.
     */
    public function deleteByVisita(int $visitaId): void
    {
        if ($visitaId <= 0) {
            return;
        }
        $stmt = $this->conn->prepare("DELETE FROM tb_prorrogacao WHERE fk_visita_pror = :visita");
        $stmt->bindValue(':visita', $visitaId, PDO::PARAM_INT);
        $stmt->execute();
    }



    public function update($prorrogacao)
    {
        if (($prorrogacao->fk_usuario_pror ?? null) !== null && (int)$prorrogacao->fk_usuario_pror > 0) {
            $stmtUser = $this->conn->prepare("SELECT 1 FROM tb_user WHERE id_usuario = :id LIMIT 1");
            $stmtUser->bindValue(':id', (int)$prorrogacao->fk_usuario_pror, PDO::PARAM_INT);
            $stmtUser->execute();
            if (!(bool)$stmtUser->fetchColumn()) {
                $prorrogacao->fk_usuario_pror = null;
            }
        }

        $stmt = $this->conn->prepare("UPDATE tb_prorrogacao SET
        fk_internacao_pror = :fk_internacao_pror,
        acomod1_pror = :acomod1_pror,
        isol_1_pror = :isol_1_pror,
        prorrog1_ini_pror = :prorrog1_ini_pror,
        prorrog1_fim_pror = :prorrog1_fim_pror,
        fk_usuario_pror = :fk_usuario_pror,
        diarias_1 = :diarias_1
        WHERE id_prorrogacao = :id_prorrogacao
      ");

        $stmt->bindParam(":fk_internacao_pror", $prorrogacao->fk_internacao_pror);
        $stmt->bindParam(":acomod1_pror", $prorrogacao->acomod1_pror);
        $stmt->bindParam(":isol_1_pror", $prorrogacao->isol_1_pror);
        $stmt->bindParam(":prorrog1_ini_pror", $prorrogacao->prorrog1_ini_pror);
        $stmt->bindParam(":prorrog1_fim_pror", $prorrogacao->prorrog1_fim_pror);
        $stmt->bindParam(":fk_usuario_pror", $prorrogacao->fk_usuario_pror);
        $stmt->bindParam(":diarias_1", $prorrogacao->diarias_1);
        $stmt->bindParam(":id_prorrogacao", $prorrogacao->id_prorrogacao);
        $stmt->execute();

    }
    public function findByIdUpdate($prorrogacao)
    {

        $stmt = $this->conn->prepare("UPDATE tb_prorrogacao SET
        fk_internacao_pror = :fk_internacao_pror,
        alto_custo_pror = :alto_custo_pror,
        rel_alto_custo_pror = :rel_alto_custo_pror,
        evento_adverso_pror = :evento_adverso_pror,
        rel_evento_adverso_pror = :rel_evento_adverso_pror,
        tipo_evento_adverso_prort = :tipo_evento_adverso_prort,
        opme_pror = :opme_pror,
        rel_opme_pror = :rel_opme_pror,
        home_care_pror = :home_care_pror,
        rel_home_care_pror, = :rel_home_care_pror,
        desospitalizacao_pror, = :desospitalizacao_pror,
        rel_desospitalizacao_pror, = :rel_desospitalizacao_pror

        WHERE id_prorrogacao = :id_prorrogacao 
      ");

        $stmt->bindParam(":fk_internacao_pror", $prorrogacao->fk_internacao_pror);
        $stmt->bindParam(":alto_custo_pror", $prorrogacao->alto_custo_pror);
        $stmt->bindParam(":rel_alto_custo_pror", $prorrogacao->rel_alto_custo_pror);
        $stmt->bindParam(":evento_adverso_pror", $prorrogacao->evento_adverso_pror);
        $stmt->bindParam(":rel_evento_adverso_pror", $prorrogacao->rel_evento_adverso_pror);
        $stmt->bindParam(":tipo_evento_adverso_prort", $prorrogacao->tipo_evento_adverso_prort);
        $stmt->bindParam(":opme_pror", $prorrogacao->opme_pror);
        $stmt->bindParam(":rel_opme_pror", $prorrogacao->rel_opme_pror);
        $stmt->bindParam(":home_care_pror", $prorrogacao->home_care_pror);
        $stmt->bindParam(":rel_home_care_pror", $prorrogacao->rel_home_care_pror);
        $stmt->bindParam(":rel_home_care_pror", $prorrogacao->rel_home_care_pror);
        $stmt->bindParam(":rel_desospitalizacao_pror", $prorrogacao->rel_desospitalizacao_pror);

        $stmt->bindParam(":id_prorrogacao", $prorrogacao->id_prorrogacao);
        $stmt->execute();

        // Mensagem de sucesso por editar prorrogacao
        $this->message->setMessage("prorrogacao atualizado com sucesso!", "success", "list_prorrogacao.php");
    }

    public function destroy($id_prorrogacao)
    {
        $stmt = $this->conn->prepare("DELETE FROM tb_prorrogacao WHERE id_prorrogacao = :id_prorrogacao");

        $stmt->bindParam(":id_prorrogacao", $id_prorrogacao);

        $stmt->execute();

    }


    public function findGeral()
    {

        $prorrogacao = [];

        $stmt = $this->conn->query("SELECT * FROM tb_prorrogacao ORDER BY id_prorrogacao DESC");

        $stmt->execute();

        $prorrogacao = $stmt->fetchAll();

        return $prorrogacao;
    }
    // pegar id max da visita
    public function findMaxPror()
    {

        $prorrogacao = [];

        $stmt = $this->conn->query("SELECT max(id_visita) as ultimoReg from tb_visita");

        $stmt->execute();

        $prorrogacaoIdMax = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $prorrogacaoIdMax;
    }

    public function findMaxProInt()
    {

        $gestao = [];

        $stmt = $this->conn->query("SELECT data_intern_int, id_internacao AS ultimoReg from tb_internacao order by id_internacao desc limit 1");

        $stmt->execute();

        $findMaxProInt = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $findMaxProInt;
    }

    public function selectAllInternacaoProrrog($id_internacao)
    {

        //MONTA A QUERY
        $query = $this->conn->query('SELECT 
        
        pr.id_prorrogacao,
        pr.acomod1_pror,
        pr.prorrog1_fim_pror,
        pr.prorrog1_ini_pror,
        pr.diarias_1
        FROM tb_prorrogacao AS pr 
        
        WHERE fk_internacao_pror = ' . $id_internacao);

        $query->execute();

        $result = $query->fetchAll();

        return $result;
    }



    public function selectInternacaoProrrog($id_internacao)
    {
        $stmt = $this->conn->prepare('
        SELECT 
            pr.id_prorrogacao,
            pr.acomod1_pror          AS acomod,      -- Acomodação
            pr.prorrog1_ini_pror     AS ini,         -- Data inicial
            pr.prorrog1_fim_pror     AS fim,         -- Data final
            pr.diarias_1             AS diarias,     -- Nº de diárias
            pr.isol_1_pror           AS isolamento   -- Isolamento (s/n)    
        FROM tb_prorrogacao AS pr 
        WHERE pr.fk_internacao_pror = :id
    ');

        $stmt->bindParam(':id', $id_internacao, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
# Limita o número de registros a serem mostrados por página
$limite = 10;

# Se pg não existe atribui 1 a variável pg
$pg = (isset($_GET['pg'])) ? (int) $_GET['pg'] : 1;

# Atribui a variável inicio o inicio de onde os registros vão ser
# mostrados por página, exemplo 0 à 10, 11 à 20 e assim por diante
$inicio = ($pg * $limite) - $limite;
$pesquisa_hosp = "";
# seleciona o total de registros  
$sql_Total = 'SELECT id_prorrogacao FROM tb_prorrogacao';
