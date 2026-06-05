<?php

require_once("./models/censo.php");
require_once("./models/hospital.php");
require_once("./models/message.php");

// Review DAO
require_once("dao/censoDao.php");

class censoDAO implements censoDAOInterface
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

    public function buildcenso($data)
    {
        $censo = new censo();

        $censo->id_censo = $data["id_censo"];
        $censo->fk_paciente_censo = $data["fk_paciente_censo"];
        $censo->fk_hospital_censo = $data["fk_hospital_censo"];
        $censo->data_censo = $data["data_censo"];
        $censo->senha_censo = $data["senha_censo"];
        $censo->acomodacao_censo = $data["acomodacao_censo"];
        $censo->tipo_admissao_censo = $data["tipo_admissao_censo"];
        $censo->modo_internacao_censo = $data["modo_internacao_censo"];
        $censo->usuario_create_censo = $data["usuario_create_censo"];
        $censo->data_create_censo = $data["data_create_censo"];
        $censo->titular_censo = $data["titular_censo"];
        return $censo;
    }

    public function findAll()
    {
    }
    public function getcenso()
    {
    }
    public function findById($id_censo)
    {
        $censo = [];
        $stmt = $this->conn->prepare("SELECT * FROM tb_censo
                                    WHERE id_censo = :id_censo");
        $stmt->bindParam(":id_censo", $id_censo);
        $stmt->execute();

        $data = $stmt->fetch();

        // var_dump($data);
        if ($data) {
            $censo = $this->buildcenso($data);
        }


        return $censo;
    }
    public function findByIdUpdate($id_censo)
    {
    }
    public function create(censo $censo)
    {
        $stmt = $this->conn->prepare("INSERT INTO tb_censo (
           
            fk_paciente_censo, 
            fk_hospital_censo, 
            data_censo, 
            senha_censo, 
            acomodacao_censo, 
            tipo_admissao_censo, 
            modo_internacao_censo, 
            usuario_create_censo, 
            data_create_censo,
            titular_censo
          ) VALUES (
            
            :fk_paciente_censo, 
            :fk_hospital_censo, 
            :data_censo, 
            :senha_censo, 
            :acomodacao_censo, 
            :tipo_admissao_censo, 
            :modo_internacao_censo, 
            :usuario_create_censo, 
            :data_create_censo,
            :titular_censo
         )");

        $stmt->bindParam(":fk_paciente_censo", $censo->fk_paciente_censo);
        $stmt->bindParam(":fk_hospital_censo", $censo->fk_hospital_censo);
        $stmt->bindParam(":data_censo", $censo->data_censo);
        $stmt->bindParam(":senha_censo", $censo->senha_censo);
        $stmt->bindParam(":acomodacao_censo", $censo->acomodacao_censo);
        $stmt->bindParam(":tipo_admissao_censo", $censo->tipo_admissao_censo);
        $stmt->bindParam(":modo_internacao_censo", $censo->modo_internacao_censo);
        $stmt->bindParam(":usuario_create_censo", $censo->usuario_create_censo);
        $stmt->bindParam(":data_create_censo", $censo->data_create_censo);
        $stmt->bindParam(":titular_censo", $censo->titular_censo);

        $stmt->execute();

        // Mensagem de sucesso por adicionar filme
        // $this->message->setMessage("censo adicionado com sucesso!", "success", "internacoes/lista");
    }

    public function selectAllCensoList($where = null, $order = null, $limit = null)
    {
        //DADOS DA QUERY
        $where = strlen($where) ? 'WHERE ' . $where : '';
        $order = strlen($order) ? 'ORDER BY ' . $order : '';
        $limit = strlen($limit) ? 'LIMIT ' . $limit : '';

        $group = ' GROUP BY ac.id_censo ';

        //MONTA A QUERY
        $query = $this->conn->query('SELECT 
        ac.id_censo, 
        ac.data_censo,
        ac.senha_censo,
        ac.acomodacao_censo,
        ac.titular_censo,
        tipo_admissao_censo,
        modo_internacao_censo,
        usuario_create_censo,
        data_create_censo,
        pa.id_paciente,
        pa.nome_pac,
        ho.id_hospital,
        ho.nome_hosp,
        hos.fk_hospital_user,
        hos.fk_usuario_hosp,
        se.id_usuario,
        se.usuario_user,
        it.id_internacao
        
        FROM tb_censo ac 
    
            LEFT JOIN tb_hospital AS ho ON  
            ac.fk_hospital_censo = ho.id_hospital
            
			LEFT JOIN tb_hospitalUser AS hos ON
            hos.fk_hospital_user = ho.id_hospital
            
			LEFT JOIN tb_user AS se ON  
            se.id_usuario = hos.fk_usuario_hosp
            
            LEFT JOIN tb_paciente AS pa ON
            ac.fk_paciente_censo = pa.id_paciente 

            LEFT JOIN tb_internacao AS it ON
            ac.fk_paciente_censo = it.fk_paciente_int 
            
             ' . $where . ' ' . $group . ' ' . $order . ' ' . $limit);

        $query->execute();

        $hospital = $query->fetchAll();

        return $hospital;
    }
    public function update(censo $censo)
    {
        $stmt = $this->conn->prepare("UPDATE tb_censo SET
            fk_paciente_censo = :fk_paciente_censo,
            fk_hospital_censo = :fk_hospital_censo,
            data_censo = :data_censo,
            senha_censo = :senha_censo,
            acomodacao_censo = :acomodacao_censo,
            tipo_admissao_censo = :tipo_admissao_censo,
            modo_internacao_censo = :modo_internacao_censo,
            usuario_create_censo = :usuario_create_censo,
            data_create_censo = :data_create_censo,
            titular_censo = :titular_censo
            WHERE id_censo = :id_censo");

        $stmt->bindParam(":fk_paciente_censo", $censo->fk_paciente_censo);
        $stmt->bindParam(":fk_hospital_censo", $censo->fk_hospital_censo);
        $stmt->bindParam(":data_censo", $censo->data_censo);
        $stmt->bindParam(":senha_censo", $censo->senha_censo);
        $stmt->bindParam(":acomodacao_censo", $censo->acomodacao_censo);
        $stmt->bindParam(":tipo_admissao_censo", $censo->tipo_admissao_censo);
        $stmt->bindParam(":modo_internacao_censo", $censo->modo_internacao_censo);
        $stmt->bindParam(":usuario_create_censo", $censo->usuario_create_censo);
        $stmt->bindParam(":data_create_censo", $censo->data_create_censo);
        $stmt->bindParam(":titular_censo", $censo->titular_censo);
        $stmt->bindParam(":id_censo", $censo->id_censo);

        $stmt->execute();
    }
    public function updateCenso(censo $censo)
    {
        $stmt = $this->conn->prepare("UPDATE tb_censo SET internado = 1 WHERE id_censo = :id_censo");

        $stmt->bindParam(":id_censo", $censo->id_censo);


        $stmt->execute();

        // Mensagem de sucesso por adicionar filme
        $this->message->setMessage("Censo internado com sucesso!", "success", "censo/lista");
    }
    public function destroy($id_censo)
    {
        $stmt = $this->conn->prepare("DELETE FROM tb_censo WHERE id_censo = :id_censo");

        $stmt->bindParam(":id_censo", $id_censo);

        $stmt->execute();

        // Mensagem de sucesso por remover filme
        $this->message->setMessage("censo removido com sucesso!", "success", "censo/lista");
    }
    public function findGeral()
    {
    }
    public function selectAllcenso($where = null, $order = null, $limit = null)
    {
    }
}
