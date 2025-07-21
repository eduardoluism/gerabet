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

session_start();

require_once '../../includes/db.php';
require_once '../../includes/config.php';

// Função para validar CSRF
function valida_token_csrf($form_name) {
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION["csrf_token_$form_name"]) && $token === $_SESSION["csrf_token_$form_name"];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];

    if (!isset($_SESSION['usuario_id'])) {
        $errors[] = "Você precisa estar logado.";
    } else if (!valida_token_csrf('bonus')) {
        $errors[] = "Falha. Por favor, tente novamente.";
    }

    if (count($errors) > 0) {
        $response = [
            "status" => "alertanao",
            "message" => "<p class='alertanao'>{$errors[0]} <span><i class='fas fa-times'></i></span></p>"
        ];
    } else {
        $usuario_id = $_SESSION['usuario_id'];

        // Buscar total de depósitos aprovados
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(bet_valor),0) FROM bet_transacoes WHERE bet_usuario = ? AND bet_tipo = 'Deposito' AND bet_status = 'Aprovado'");
        $stmt->execute([$usuario_id]);
        $total_depositos = (float) $stmt->fetchColumn();

        // Buscar total de saques (pendentes ou aprovados)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(bet_valor),0) FROM bet_transacoes WHERE bet_usuario = ? AND bet_tipo = 'Retirada'");
        $stmt->execute([$usuario_id]);
        $total_saques = (float) $stmt->fetchColumn();

        // Buscar saldo atual
        $stmt = $pdo->prepare("SELECT bet_saldo FROM bet_usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $saldo_atual = (float) $stmt->fetchColumn();

        // Buscar todos os bônus pendentes
        $stmt = $pdo->prepare("SELECT id, bet_bonus_tipo, bet_bonus_valor FROM bet_bonus WHERE bet_usuario = ? AND bet_bonus_status = 0");
        $stmt->execute([$usuario_id]);
        $bonus_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$bonus_pendentes) {
            $response = [
                "status" => "alertanao",
                "message" => "<p class='alertanao'>Você não tem bônus pendentes para resgatar.<span><i class='fas fa-times'></i></span></p>"
            ];
        } else {
            $bonus_resgatados = 0;
            $mensagens_faltando = [];

            foreach ($bonus_pendentes as $bonus) {
                $id_bonus = $bonus['id'];
                $tipo = $bonus['bet_bonus_tipo'];
                $valor = (float) $bonus['bet_bonus_valor'];

                if ($tipo === 'Cadastro') {
                    // Regra: meta depósito = 2x bônus + 10
                    $meta_liberacao = ($valor * 2) + 10;

                    // Quanto o usuário efetivamente gastou (depositado - sacado - saldo)
                    $valor_gasto = $total_depositos - $total_saques - $saldo_atual;
                    if ($valor_gasto < 0) $valor_gasto = 0;

                    // Quanto ainda falta depositar para atingir a meta
                    $faltando_deposito = $meta_liberacao - $total_depositos;
                    if ($faltando_deposito < 0) $faltando_deposito = 0;

                    // Meta de gasto: o quanto precisa gastar para liberar o bônus
                    // que é o total da meta menos o valor do bônus
                    $meta_gasto = $meta_liberacao - $valor;
                    if ($meta_gasto < 0) $meta_gasto = 0;

                    // Quanto ainda falta jogar (gastar)
                    $faltando_jogar = $meta_gasto - $valor_gasto;
                    if ($faltando_jogar < 0) $faltando_jogar = 0;

                    // Verifica se condições foram cumpridas
                    if ($faltando_deposito == 0 && $faltando_jogar == 0) {
                        // Atualiza o status do bônus para liberado
                        $stmt = $pdo->prepare("UPDATE bet_bonus SET bet_bonus_status = 1 WHERE id = ?");
                        $stmt->execute([$id_bonus]);

                        // Incrementa o saldo do usuário com o valor do bônus
                        $stmt = $pdo->prepare("UPDATE bet_usuarios SET bet_saldo = bet_saldo + ? WHERE id = ?");
                        $stmt->execute([$valor, $usuario_id]);

                        $bonus_resgatados++;
                    } else {
                        // Mensagem do que falta para liberar o bônus
                        $mensagem = "
                            <div class='bonus-wrapper'>
                                <ul class='bonus-status'>
                                <li>Bônus Cadastro de R$ <strong class='valor'>" . number_format($valor,2,',','.') . "</strong>
                                <ul>";
                            if ($faltando_deposito > 0) {
                            $mensagem .= "<li>- Falta <span class='highlight'>depositar</span> R$ <strong class='valor'>" . number_format($faltando_deposito,2,',','.') . "</strong></li>";
                            }
                            if ($faltando_jogar > 0) {
                            $mensagem .= "<li>- Falta <span class='highlight'>jogar</span> R$ <strong class='valor'>" . number_format($faltando_jogar,2,',','.') . "</strong></li>";
                            }
                        $mensagem .= "
                                </ul>
                                </li>
                                </ul>
                            </div>";

                        $mensagens_faltando[] = $mensagem;
                    }
                } elseif ($tipo === 'Deposito') {
                    // Lógica para bônus de depósito (a implementar)
                } elseif ($tipo === 'Missoes') {
                    // Lógica para bônus de missões (a implementar)
                }
            }

            // Monta resposta final
            if ($bonus_resgatados > 0) {
                $response = [
                    "status" => "alertasim",
                    "message" => "<p class='alertasim'>Você resgatou {$bonus_resgatados} bônus com sucesso!<span><i class='fas fa-check'></i></span></p>"
                ];

                // Regenera token CSRF apenas em caso de sucesso
                $_SESSION['csrf_token_bonus'] = bin2hex(random_bytes(32));

            } elseif (!empty($mensagens_faltando)) {
                $response = [
                    "status" => "alertanao",
                    "message" => "<p class='alertanao'>Você ainda não cumpriu os requisitos para resgatar:<br>" . implode("", $mensagens_faltando) . "</p>"
                ];
            } else {
                $response = [
                    "status" => "alertanao",
                    "message" => "<p class='alertanao'>Nenhum bônus foi liberado. Verifique se você já cumpriu os requisitos.<span><i class='fas fa-times'></i></span></p>"
                ];
            }
        }
    }

    // JSON de resposta
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>