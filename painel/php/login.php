<?php
// Impede acesso direto via navegador (GET)
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    header("Location: /");
    exit;
}

session_name('adm_session');
session_start();

// === CONFIGURAÇÃO DE CONEXÃO COM O BANCO ===
include '../../includes/db.php';

// Função para validar CSRF dinamicamente
function valida_token_csrf($form_name) {
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION["csrf_token_$form_name"]) && $token === $_SESSION["csrf_token_$form_name"];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = array();

    // Sanitiza e valida os dados de entrada para evitar ataques como XSS
    $data = array(
        "email" => filter_input(INPUT_POST, "email", FILTER_SANITIZE_EMAIL), 
        "senha" => trim(filter_input(INPUT_POST, "senha", FILTER_UNSAFE_RAW))
    );

    // Validações
    if (!valida_token_csrf('login')) {
        $errors[] = "Falha. Por favor, tente novamente.";
    } elseif (empty($data["email"])) {
        $errors[] = "O campo email é obrigatório!";
    } elseif (!filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido!";
    } 
    if (empty($errors) && empty($data["senha"])) {
        $errors[] = "O campo senha é obrigatório!";
    }

    if (!empty($errors)) {
        $response = array(
            "status" => "alertanao",
            "message" => "<p class='alertanao'>" . implode("<br>", $errors) . " <span><i class='fas fa-times'></i></span></p>"
        );
    } else {
        $sql = "SELECT id, adm_senha, adm_status FROM bet_adm WHERE adm_email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $data['email']]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            if ($usuario['adm_status'] != 1) {
                $errors[] = "Acesso negado!";
            } elseif (password_verify($data['senha'], $usuario['adm_senha'])) {
                $auth_token = bin2hex(random_bytes(32));

                $updateSql = "UPDATE bet_adm SET adm_token = :auth_token WHERE id = :id";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':auth_token' => $auth_token,
                    ':id' => $usuario['id']
                ]);

                // Setar cookie com token (1 ano)
                $cookie_options = [
                    'expires' => time() + 31536000, // 1 ano
                    'path' => '/painel/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ];

                setcookie('auth_token_adm', $auth_token, $cookie_options);

                $_SESSION['adm_id'] = $usuario['id'];

                $successMessage = "Login realizado com sucesso! Aguardem...";
                $response = array(
                    "status" => "alertasim",
                    "message" => "<p class='alertasim'>{$successMessage} <span><i class='fas fa-check'></i></span></p>"
                );

                $_SESSION['csrf_token_login'] = bin2hex(random_bytes(32));
            } else {
                $errors[] = "Email ou senha incorretos!";
            }
        } else {
            $errors[] = "Usuário não encontrado!";
        }
    }

    if (!empty($errors)) {
        $response = array(
            "status" => "alertanao",
            "message" => "<p class='alertanao'>" . implode("<br>", $errors) . " <span><i class='fas fa-times'></i></span></p>"
        );
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}