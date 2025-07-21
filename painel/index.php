<?php
session_name('adm_session');
session_start();

// Gerar um token CSRF único para cada página
function gerar_token_csrf($form) {
    // Gerar o token baseado no nome do formulário para evitar colisão
    $token = bin2hex(random_bytes(32));
    $_SESSION["csrf_token_$form"] = $token;
    return $token;
}

// Exemplo de como gerar para diferentes formulários
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Gerando o token para cada formulário
    $token_login = gerar_token_csrf('login');
}

include '../includes/db.php';
require_once '../includes/config.php';


// Se já estiver logado via sessão
if (isset($_SESSION['adm_id'])) {
    header("Location: dashboard/");
    exit;
}

// Se tiver o token no cookie, tenta autenticar
if (isset($_COOKIE['auth_token_adm'])) {
    $token = $_COOKIE['auth_token_adm'];

    $stmt = $pdo->prepare("SELECT id, adm_status FROM bet_adm WHERE adm_token = ?");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $usuario['adm_status'] == 1) {
        $_SESSION['adm_id'] = $usuario['id'];
        header("Location: dashboard/");
        exit;
    } else {
        setcookie("auth_token_adm", "", time() - 3600, "/");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel - <?= $NomeSite ?></title>
  <link rel="icon" type="image/png" href="../imagens/<?= $Favicon ?>">
  <meta name="robots" content="noindex, nofollow">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <link rel="stylesheet" href="css/estilos.php">
   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
   <script src="js/scripts.js"></script>
</head>
<body>

<img src="../imagens/<?= $Logo ?>" class="logo">
    <div class="login-container">
        <h2><i class="fas fa-user-lock"></i> Login</h2>
        <div id="alerta-login"></div>
        <form id="formlogin" action="php/login.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token_login'] ?? '' ?>">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="text" name="email" placeholder="E-mail">
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="senha" placeholder="Senha">
            </div>
            <button type="submit" id="subLogin" class="login-btn">Entrar</button>
        </form>
    </div>

<p class="footer">Todos os direitos reservados <?= $NomeSite ?> © 2025</p>

</body>
</html>