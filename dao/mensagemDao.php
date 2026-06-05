<?php

require_once("./models/message.php");

class mensagemDAO implements mensagemDAOInterface
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

    // Method to build the message object from data
    public function buildMensagem($data)
    {
        $mensagem = new Mensagem();

        $mensagem->id_mensagem = $data["id_mensagem"];
        $mensagem->de_usuario = $data["de_usuario"];
        $mensagem->para_usuario = $data["para_usuario"];
        $mensagem->mensagem = $data["mensagem"];
        $mensagem->data_mensagem = $data["data_mensagem"];
        $mensagem->vista = $data["vista"];

        return $mensagem;
    }

    // Get all messages
    public function findAll()
    {
        $mensagens = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_mensagem ORDER BY data_mensagem ASC");
        $stmt->execute();

        $mensagens = $stmt->fetchAll();
        return $mensagens;
    }

    // Get messages between two users
    public function getMensagemsBetweenUsers($de_usuario, $para_usuario, $ultima_msg)
    {
        $mensagens = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_mensagem 
                                      WHERE ((de_usuario = :de_usuario_1 AND para_usuario = :para_usuario_1)
                                      OR (de_usuario = :para_usuario_2 AND para_usuario = :de_usuario_2))
                                      and id_mensagem > :ultima_msg
                                      ORDER BY data_mensagem ASC");

        $deUsuario = (int)$de_usuario;
        $paraUsuario = (int)$para_usuario;
        $ultimaMsg = (int)$ultima_msg;

        $stmt->bindValue(":de_usuario_1", $deUsuario, PDO::PARAM_INT);
        $stmt->bindValue(":para_usuario_1", $paraUsuario, PDO::PARAM_INT);
        $stmt->bindValue(":para_usuario_2", $paraUsuario, PDO::PARAM_INT);
        $stmt->bindValue(":de_usuario_2", $deUsuario, PDO::PARAM_INT);
        $stmt->bindValue(":ultima_msg", $ultimaMsg, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $mensagemArray = $stmt->fetchAll();

            foreach ($mensagemArray as $mensagem) {
                $mensagens[] = $this->buildMensagem($mensagem);
            }
        }

        return $mensagens;
    }

    public function findById($id_mensagem)
    {
        $mensagem = [];

        $stmt = $this->conn->prepare("SELECT * FROM tb_mensagem WHERE id_mensagem = :id_mensagem");
        $stmt->bindParam(":id_mensagem", $id_mensagem);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            $mensagem = $this->buildMensagem($data);
        }

        return $mensagem;
    }

    public function create(Mensagem $mensagem, bool $flashFeedback = true)
    {
        $stmt = $this->conn->prepare("INSERT INTO tb_mensagem (
            de_usuario, 
            para_usuario, 
            mensagem, 
            data_mensagem, 
            vista
        ) VALUES (
            :de_usuario, 
            :para_usuario, 
            :mensagem, 
            :data_mensagem, 
            :vista
        )");

        $stmt->bindParam(":de_usuario", $mensagem->de_usuario);
        $stmt->bindParam(":para_usuario", $mensagem->para_usuario);
        $stmt->bindParam(":mensagem", $mensagem->mensagem);
        $stmt->bindParam(":data_mensagem", $mensagem->data_mensagem);
        $stmt->bindParam(":vista", $mensagem->vista);

        $stmt->execute();

        if ($flashFeedback) {
            $this->message->setMessage("Mensagem enviada com sucesso!", "success", "list_mensagens.php");
        }
    }

    public function update(Mensagem $mensagem)
    {
        $stmt = $this->conn->prepare("UPDATE tb_mensagem SET
        mensagem = :mensagem,
        vista = :vista,
        data_mensagem = :data_mensagem
        WHERE id_mensagem = :id_mensagem");

        // Binding parameters
        $stmt->bindParam(":mensagem", $mensagem->mensagem);
        $stmt->bindParam(":vista", $mensagem->vista);
        $stmt->bindParam(":data_mensagem", $mensagem->data_mensagem);
        $stmt->bindParam(":id_mensagem", $mensagem->id_mensagem);

        // Executing the query
        $stmt->execute();

        // Success message after updating the message
        $this->message->setMessage("Mensagem atualizada com sucesso!", "success", "list_mensagens.php");
    }


    // Mark a message as read
    public function markAsRead($id_mensagem)
    {
        $stmt = $this->conn->prepare("UPDATE tb_mensagem SET vista = 1 WHERE id_mensagem = :id_mensagem");
        $stmt->bindParam(":id_mensagem", $id_mensagem);
        $stmt->execute();

        $this->message->setMessage("Mensagem marcada como vista.", "success", "list_mensagens.php");
    }

    // Função para marcar mensagens como lidas
    function marcarMensagensComoLidas($de_usuario, $para_usuario)
    {
        $sql = "UPDATE tb_mensagem 
            SET vista = 1 
            WHERE de_usuario = :para_usuario 
            AND para_usuario = :de_usuario 
            AND vista = 0 ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':de_usuario', $de_usuario);  // Quem enviou a mensagem
        $stmt->bindParam(':para_usuario', $para_usuario);  // Quem está recebendo (usuário logado)
        $stmt->execute();
    }

    // Delete a message by its ID
    public function destroy($id_mensagem)
    {
        $stmt = $this->conn->prepare("DELETE FROM tb_mensagem WHERE id_mensagem = :id_mensagem");
        $stmt->bindParam(":id_mensagem", $id_mensagem);
        $stmt->execute();

        $this->message->setMessage("Mensagem removida com sucesso!", "success", "list_mensagens.php");
    }

    // Get a list of messages with optional filters (like pagination)
    public function selectAllMensagems($where = null, $order = null, $limit = null)
    {
        // Building query conditions
        $where = strlen($where) ? 'WHERE ' . $where : ' ';
        $order = strlen($order) ? 'ORDER BY ' . $order : '';
        $limit = strlen($limit) ? 'LIMIT ' . $limit : '';

        // Query to get all messages based on conditions
        $query = $this->conn->query('SELECT * FROM tb_mensagem ' . $where . ' ' . $order . ' ' . $limit);
        $query->execute();

        $mensagens = $query->fetchAll();

        return $mensagens;
    }

    // Count total number of messages for pagination
    public function QtdMensagens($where = null)
    {
        $where = strlen($where) ? 'WHERE ' . $where : '';
        $stmt = $this->conn->query('SELECT COUNT(id_mensagem) as qtd FROM tb_mensagem ' . $where);
        $stmt->execute();

        $QtdTotalMensagens = $stmt->fetch();
        return $QtdTotalMensagens;
    }

    public function getMensagensNaoLidas($usuario)
    {
        // Prepare a consulta
        $stmt = $this->conn->prepare('SELECT COUNT(id_mensagem) as qtd FROM tb_mensagem WHERE para_usuario = :usuario AND vista = 0');

        // Bind do parâmetro :usuario
        $stmt->bindParam(":usuario", $usuario);

        // Executa a consulta
        $stmt->execute();

        // Retorna o número de mensagens não lidas
        $QtdTotalMensagens = $stmt->fetch(PDO::FETCH_ASSOC);

        // Retorna apenas o número (qtd)
        return $QtdTotalMensagens['qtd'];
    }
}
