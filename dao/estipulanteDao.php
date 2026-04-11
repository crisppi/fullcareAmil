<?php

require_once("./models/estipulante.php");
require_once("./models/message.php");

// Review DAO
require_once("dao/estipulanteDao.php");

class EstipulanteDAO implements EstipulanteDAOInterface
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

    public function buildEstipulante($data)
    {
        $estipulante = new Estipulante();

        $estipulante->id_estipulante = $data["id_estipulante"];
        $estipulante->nome_est = $data["nome_est"];
        $estipulante->endereco_est = $data["endereco_est"];
        $estipulante->cidade_est = $data["cidade_est"];
        $estipulante->estado_est = $data["estado_est"];
        $estipulante->cnpj_est = $data["cnpj_est"];
        $estipulante->telefone01_est = $data["telefone01_est"];
        $estipulante->telefone02_est = $data["telefone02_est"];
        $estipulante->email01_est = $data["email01_est"];
        $estipulante->email02_est = $data["email02_est"];
        $estipulante->numero_est = $data["numero_est"];
        $estipulante->bairro_est = $data["bairro_est"];
        $estipulante->data_create_est = $data["data_create_est"];
        $estipulante->usuario_create_est = $data["usuario_create_est"];
        $estipulante->fk_usuario_est = $data["fk_usuario_est"];
        $estipulante->logo_est = $data["logo_est"];
        $estipulante->deletado_est = $data["deletado_est"];
        $estipulante->nome_contato_est = $data["nome_contato_est"];
        $estipulante->nome_responsavel_est = $data["nome_responsavel_est"];
        $estipulante->email_contato_est = $data["email_contato_est"];
        $estipulante->email_responsavel_est = $data["email_responsavel_est"];
        $estipulante->telefone_contato_est = $data["telefone_contato_est"];
        $estipulante->telefone_responsavel_est = $data["telefone_responsavel_est"];
        $estipulante->cep_est = $data["cep_est"];
        return $estipulante;
    }

    public function findAll()
    {
        $estipulante = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_estipulante
        ORDER BY id_estipulante DESC");

        $stmt->execute();

        $estipulante = $stmt->fetchAll();
        return $estipulante;
    }

    public function findByEstipulante($pesquisa_nome)
    {

        $usuario = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_estipulante
                                    WHERE nome_est LIKE :nome_est ");

        $stmt->bindValue(":nome_est", '%' . $pesquisa_nome . '%');

        $stmt->execute();

        $usuario = $stmt->fetchAll();
        return $usuario;
    }
    public function getestipulante()
    {

        $estipulante = [];

        $stmt = $this->conn->query("SELECT * FROM tb_estipulante ORDER BY id_estipulante DESC");

        $stmt->execute();

        if ($stmt->rowCount() > 0) {

            $estipulanteArray = $stmt->fetchAll();

            foreach ($estipulanteArray as $estipulante) {
                $estipulante[] = $this->buildEstipulante($estipulante);
            }
        }

        return $estipulante;
    }

    public function getestipulanteByNome($nome)
    {

        $estipulante = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_estipulante
                                    WHERE nome_est = :nome_est
                                    ORDER BY id_estipulante DESC");

        $stmt->bindParam(":nome_est", $nome_est);

        $stmt->execute();

        if ($stmt->rowCount() > 0) {

            $estipulanteArray = $stmt->fetchAll();

            foreach ($estipulanteArray as $estipulante) {
                $estipulante[] = $this->buildEstipulante($estipulante);
            }
        }

        return $estipulante;
    }

    public function findById($id_estipulante)
    {
        $estipulante = [];
        $stmt = $this->conn->prepare("SELECT * FROM tb_estipulante
                                    WHERE id_estipulante = :id_estipulante");

        $stmt->bindParam(":id_estipulante", $id_estipulante);
        $stmt->execute();

        $data = $stmt->fetch();
        //var_dump($data);
        $estipulante = $this->buildEstipulante($data);

        return $estipulante;
    }

    public function findEnderecosByEstipulante($id_estipulante)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tb_estipulante_endereco WHERE fk_estipulante = :id ORDER BY principal_endereco DESC, id_estipulante_endereco ASC");
            $stmt->bindValue(":id", (int) $id_estipulante, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function findTelefonesByEstipulante($id_estipulante)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tb_estipulante_telefone WHERE fk_estipulante = :id ORDER BY principal_telefone DESC, id_estipulante_telefone ASC");
            $stmt->bindValue(":id", (int) $id_estipulante, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function findContatosByEstipulante($id_estipulante)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tb_estipulante_contato WHERE fk_estipulante = :id ORDER BY principal_contato DESC, id_estipulante_contato ASC");
            $stmt->bindValue(":id", (int) $id_estipulante, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function findByTitle($title)
    {

        $estipulante = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_estipulante
                                    WHERE title LIKE :nome");

        $stmt->bindValue(":title", '%' . $title . '%');

        $stmt->execute();

        if ($stmt->rowCount() > 0) {

            $estipulanteArray = $stmt->fetchAll();

            foreach ($estipulanteArray as $estipulante) {
                $estipulante[] = $this->buildestipulante($estipulante);
            }
        }

        return $estipulante;
    }

    public function create(Estipulante $estipulante)
    {

        $stmt = $this->conn->prepare("INSERT INTO tb_estipulante (
        nome_est, 
        endereco_est, 
        bairro_est, 
        email01_est, 
        cnpj_est, 
        email02_est, 
        telefone01_est, 
        telefone02_est, 
        numero_est, 
        cidade_est, 
        estado_est, 
        data_create_est, 
        fk_usuario_est,
        logo_est,
        deletado_est,
        usuario_create_est,
        nome_contato_est,
        nome_responsavel_est,
        email_contato_est,
        email_responsavel_est,
        telefone_contato_est,
        telefone_responsavel_est,
        cep_est

      ) VALUES (
        :nome_est, 
        :endereco_est, 
        :bairro_est, 
        :email01_est, 
        :cnpj_est, 
        :email02_est, 
        :telefone01_est, 
        :telefone02_est, 
        :numero_est, 
        :cidade_est, 
        :estado_est, 
        :data_create_est,
        :fk_usuario_est,
        :logo_est,
        :deletado_est,
        :usuario_create_est,
        :nome_contato_est,
        :nome_responsavel_est,
        :email_contato_est,
        :email_responsavel_est,
        :telefone_contato_est,
        :telefone_responsavel_est,
        :cep_est

     )");

        $stmt->bindParam(":nome_est", $estipulante->nome_est);
        $stmt->bindParam(":endereco_est", $estipulante->endereco_est);
        $stmt->bindParam(":bairro_est", $estipulante->bairro_est);
        $stmt->bindParam(":email01_est", $estipulante->email01_est);
        $stmt->bindParam(":cnpj_est", $estipulante->cnpj_est);
        $stmt->bindParam(":email02_est", $estipulante->email02_est);
        $stmt->bindParam(":telefone01_est", $estipulante->telefone01_est);
        $stmt->bindParam(":telefone02_est", $estipulante->telefone02_est);
        $stmt->bindParam(":numero_est", $estipulante->numero_est);
        $stmt->bindParam(":cidade_est", $estipulante->cidade_est);
        $stmt->bindParam(":estado_est", $estipulante->estado_est);
        $stmt->bindParam(":data_create_est", $estipulante->data_create_est);
        $stmt->bindParam(":usuario_create_est", $estipulante->usuario_create_est);
        $stmt->bindParam(":fk_usuario_est", $estipulante->fk_usuario_est);
        $stmt->bindParam(":logo_est", $estipulante->logo_est);
        $stmt->bindParam(":deletado_est", $estipulante->deletado_est);
        $stmt->bindParam(":nome_contato_est", $estipulante->nome_contato_est);
        $stmt->bindParam(":nome_responsavel_est", $estipulante->nome_responsavel_est);
        $stmt->bindParam(":email_contato_est", $estipulante->email_contato_est);
        $stmt->bindParam(":email_responsavel_est", $estipulante->email_responsavel_est);
        $stmt->bindParam(":telefone_contato_est", $estipulante->telefone_contato_est);
        $stmt->bindParam(":telefone_responsavel_est", $estipulante->telefone_responsavel_est);
        $stmt->bindParam(":cep_est", $estipulante->cep_est);
        $stmt->execute();

        // Mensagem de sucesso por adicionar filme
        $this->message->setMessage("estipulante adicionado com sucesso!", "success", "estipulantes");
    }

    public function update(Estipulante $estipulante)
    {

        $stmt = $this->conn->prepare("UPDATE tb_estipulante SET
        nome_est = :nome_est,
        endereco_est = :endereco_est,
        email01_est = :email01_est,
        email02_est = :email02_est,
        cnpj_est = :cnpj_est,
        numero_est = :numero_est,
        telefone01_est = :telefone01_est,
        telefone02_est = :telefone02_est,
        cidade_est = :cidade_est,
        estado_est = :estado_est,
        bairro_est = :bairro_est,
        logo_est = :logo_est,
        nome_contato_est = :nome_contato_est,
        nome_responsavel_est = :nome_responsavel_est,
        email_contato_est = :email_contato_est,
        email_responsavel_est = :email_responsavel_est,
        telefone_contato_est = :telefone_contato_est,
        telefone_responsavel_est = :telefone_responsavel_est,
        cep_est = :cep_est

        WHERE id_estipulante = :id_estipulante 
      ");

        $stmt->bindParam(":nome_est", $estipulante->nome_est);
        $stmt->bindParam(":endereco_est", $estipulante->endereco_est);
        $stmt->bindParam(":email01_est", $estipulante->email01_est);
        $stmt->bindParam(":email02_est", $estipulante->email02_est);
        $stmt->bindParam(":cnpj_est", $estipulante->cnpj_est);
        $stmt->bindParam(":numero_est", $estipulante->numero_est);
        $stmt->bindParam(":telefone01_est", $estipulante->telefone01_est);
        $stmt->bindParam(":telefone02_est", $estipulante->telefone02_est);
        $stmt->bindParam(":cidade_est", $estipulante->cidade_est);
        $stmt->bindParam(":estado_est", $estipulante->estado_est);
        $stmt->bindParam(":bairro_est", $estipulante->bairro_est);
        $stmt->bindParam(":logo_est", $estipulante->logo_est);
        $stmt->bindParam(":nome_contato_est", $estipulante->nome_contato_est);
        $stmt->bindParam(":nome_responsavel_est", $estipulante->nome_responsavel_est);
        $stmt->bindParam(":email_contato_est", $estipulante->email_contato_est);
        $stmt->bindParam(":email_responsavel_est", $estipulante->email_responsavel_est);
        $stmt->bindParam(":telefone_contato_est", $estipulante->telefone_contato_est);
        $stmt->bindParam(":telefone_responsavel_est", $estipulante->telefone_responsavel_est);
        $stmt->bindParam(":cep_est", $estipulante->cep_est);
        $stmt->bindParam(":id_estipulante", $estipulante->id_estipulante);
        $stmt->execute();

        // Mensagem de sucesso por editar estipulante
        $this->message->setMessage("estipulante atualizado com sucesso!", "success", "estipulantes");
    }

    public function destroy($id_estipulante)
    {
        $stmt = $this->conn->prepare("DELETE FROM tb_estipulante WHERE id_estipulante = :id_estipulante");

        $stmt->bindParam(":id_estipulante", $id_estipulante);

        $stmt->execute();

        // Mensagem de sucesso por remover filme
        $this->message->setMessage("estipulante removido com sucesso!", "success", "estipulantes");
    }

    public function deletarUpdate(estipulante $estipulante)
    {
        $deletado_est = "s";
        $stmt = $this->conn->prepare("UPDATE tb_estipulante SET
        
        deletado_est = :deletado_est

        WHERE id_estipulante = :id_estipulante 
      ");

        $stmt->bindParam(":deletado_est", $estipulante->deletado_est);

        $stmt->bindParam(":id_estipulante", $estipulante->id_estipulante);
        $stmt->execute();

        // Mensagem de sucesso por editar hospital
        $this->message->setMessage("estipulante deletado com sucesso!", "success", "estipulantes");
    }


    public function findGeral()
    {

        $estipulante = [];

        $stmt = $this->conn->query("SELECT * FROM tb_estipulante ORDER BY id_estipulante DESC");

        $stmt->execute();

        $estipulante = $stmt->fetchAll();

        return $estipulante;
    }

    public function selectAllestipulante($where = null, $order = null, $limit = null) // funcao filtrar apenas estipulantes que nao foram deletados
    {
        // DADOS DA QUERY
        $condicoes = [];

        if (strlen((string) $where)) {
            $condicoes[] = $where;
        }

        // Filtra apenas estipulantes que nao foram deletados.
        $condicoes[] = '(deletado_est <> "s" OR deletado_est IS NULL OR deletado_est = "")';

        $where = 'WHERE ' . implode(' AND ', $condicoes);

        $order = strlen($order) ? 'ORDER BY ' . $order : '';
        $limit = strlen($limit) ? 'LIMIT ' . $limit : '';

        //MONTA A QUERY
        $query = $this->conn->query('SELECT * FROM tb_estipulante ' . $where . ' ' . $order . ' ' . $limit);

        $query->execute();

        $estipulante = $query->fetchAll();

        return $estipulante;
    }

    public function Qtdestipulante($where = null, $order = null, $limite = null)
    {
        $estipulante = [];
        //DADOS DA QUERY
        $where = strlen($where) ? 'WHERE ' . $where : '';
        $order = strlen($order) ? 'ORDER BY ' . $order : '';
        $limite = strlen($limite) ? 'LIMIT ' . $limite : '';

        $stmt = $this->conn->query('SELECT * ,COUNT(id_estipulante) as qtd FROM tb_estipulante ' . $where . ' ' . $order . ' ' . $limite);

        $stmt->execute();

        $QtdTotalEst = $stmt->fetch();

        return $QtdTotalEst;
    }
}
