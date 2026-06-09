<?php

require_once __DIR__ . '/../app/EnvLoader.php';
require_once __DIR__ . '/../app/InventoryApp.php';

EnvLoader::load(__DIR__ . '/../.env');

$app = new InventoryApp();
$alert = null;
$activeTab = $_GET['tab'] ?? 'items';
$action = $_POST['action'] ?? null;

try {
    if ($action === 'add_item') {
        $itemName = trim($_POST['item_name'] ?? '');
        $quantity = isset($_POST['item_quantity']) ? (int)$_POST['item_quantity'] : 0;
        $itemId = $app->addItem($itemName, $quantity);
        $alert = 'Item registrado com sucesso.';
        if ($quantity > 0) {
            $alert = 'Item registrado com quantidade inicial de ' . $quantity . '.';
        }
        header('Location: ?tab=items');
        exit;
    }

    if ($action === 'add_funcionario') {
        $app->addFuncionario(trim($_POST['funcionario_nome'] ?? ''));
        $alert = 'Funcionário cadastrado com sucesso.';
        header('Location: ?tab=funcionarios');
        exit;
    }

    if ($action === 'add_localidade') {
        $app->addLocalidade(trim($_POST['localidade_nome'] ?? ''));
        $alert = 'Localidade cadastrada com sucesso.';
        header('Location: ?tab=localidades');
        exit;
    }

    if ($action === 'record_movimentacao') {
        $app->recordMovimentacao([
            'id_item' => $_POST['id_item'] ?? null,
            'tipo' => $_POST['tipo'] ?? 'entrada',
            'data_item' => $_POST['data_item'] ?? date('Y-m-d'),
            'quantidade' => (int)($_POST['quantidade'] ?? 0),
            'assinatura_data' => trim($_POST['assinatura_data'] ?? ''),
            'uso' => $_POST['uso'] ?? 'Consumo',
            'observacao' => trim($_POST['observacao'] ?? ''),
            'id_funcionario' => !empty($_POST['id_funcionario']) ? (int)$_POST['id_funcionario'] : null,
            'id_localidade' => !empty($_POST['id_localidade']) ? (int)$_POST['id_localidade'] : null,
        ]);
        $alert = 'Movimentação registrada com sucesso.';
        header('Location: ?tab=movimentacoes');
        exit;
    }
} catch (\Throwable $error) {
    $alert = 'Erro: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
}

$items = $app->getItems();
$funcionarios = $app->getFuncionarios();
$localidades = $app->getLocalidades();
$localidadeHierarchy = $app->getLocalidadeHierarchy();
$movimentacoes = $app->getMovements();
$movementSummary = $app->getMovementSummary();


function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function tabClass(string $tab): string
{
    global $activeTab;
    return $activeTab === $tab ? 'active' : '';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque PHP</title>
    <link rel="stylesheet" href="style.css?v=4">
</head>
<body>
    <header>
        <div class="header-top">
            <div>
                <p class="eyebrow">Estoque</p>
                <h1>Controle de Estoque</h1>
            </div>
        </div>
        <nav>
            <a class="<?= tabClass('items') ?>" href="?tab=items">Itens</a>
            <a class="<?= tabClass('funcionarios') ?>" href="?tab=funcionarios">Funcionários</a>
            <a class="<?= tabClass('localidades') ?>" href="?tab=localidades">Localidades</a>
            <a class="<?= tabClass('movimentacoes') ?>" href="?tab=movimentacoes">Movimentações</a>
            <a class="<?= tabClass('historico') ?>" href="?tab=historico">Histórico</a>
            <a class="<?= tabClass('relatorio') ?>" href="?tab=relatorio">Relatório</a>
        </nav>
    </header>

    <main>
        <?php if ($alert): ?>
            <div class="alert"><?= e($alert) ?></div>
        <?php endif; ?>

        <?php if ($activeTab === 'items'): ?>
            <section>
                <h2>Itens</h2>
                <form method="post" class="small-form">
                    <input type="hidden" name="action" value="add_item">
                    <label>Nome do item<br><input name="item_name" required></label>
                    <label>Quantidade inicial<br><input type="number" name="item_quantity" min="0" value="0"></label>
                    <button type="submit">Cadastrar Item</button>
                </form>

                <?php if (empty($items)): ?>
                    <p>Nenhum item cadastrado.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Item</th>
                                <th>Saldo Atual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e($item['id_item']) ?></td>
                                    <td><?= e($item['item']) ?></td>
                                    <td><?= e($app->getStock((int)$item['id_item'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php elseif ($activeTab === 'funcionarios'): ?>
            <section>
                <h2>Funcionários</h2>
                <form method="post" class="small-form">
                    <input type="hidden" name="action" value="add_funcionario">
                    <label>Nome<br><input name="funcionario_nome" required></label>
                    <button type="submit">Cadastrar Funcionário</button>
                </form>

                <?php if (empty($funcionarios)): ?>
                    <p>Nenhum funcionário cadastrado.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($funcionarios as $funcionario): ?>
                                <tr>
                                    <td><?= e($funcionario['id_funcionario']) ?></td>
                                    <td><?= e($funcionario['nome']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php elseif ($activeTab === 'localidades'): ?>
            <section>
                <h2>Localidades</h2>
                <form method="post" class="small-form">
                    <input type="hidden" name="action" value="add_localidade">
                    <label>Localidade (formato: Secretaria > Divisão > Setor)<br><input name="localidade_nome" placeholder="Secretaria > Divisão > Setor" required></label>
                    <button type="submit">Cadastrar Localidade</button>
                </form>

                <?php if (empty($localidades)): ?>
                    <p>Nenhuma localidade cadastrada.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Secretaria</th>
                                <th>Divisão</th>
                                <th>Setor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($localidades as $localidade): ?>
                                <tr>
                                    <td><?= e($localidade['id_localidade']) ?></td>
                                    <td><?= e($localidade['secretaria']) ?></td>
                                    <td><?= e($localidade['divisao']) ?></td>
                                    <td><?= e($localidade['setor']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php elseif ($activeTab === 'movimentacoes'): ?>
            <section>
                <h2>Nova Movimentação</h2>
                <form method="post" class="movement-form">
                    <input type="hidden" name="action" value="record_movimentacao">
                    <label>Tipo<br>
                        <select name="tipo">
                            <option value="entrada">entrada</option>
                            <option value="saída">saída</option>
                        </select>
                    </label>
                    <label>Uso<br>
                        <select name="uso" id="uso">
                            <option value="Consumo">Consumo</option>
                            <option value="Empréstimo">Empréstimo</option>
                        </select>
                    </label>
                    <label>Data<br><input type="date" name="data_item" value="<?= date('Y-m-d') ?>" required></label>
                    <label>Item<br>
                        <select name="id_item" id="item_name" required>
                            <option value="">Selecione o item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= e($item['id_item']) ?>"><?= e($item['item']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Quantidade<br><input type="number" name="quantidade" min="1" value="1" required></label>
                    <div id="field-funcionario" class="form-field hidden" style="display:none;">
                        <label>Funcionário<br>
                            <select name="id_funcionario">
                                <option value="">Selecione o funcionário</option>
                                <?php foreach ($funcionarios as $funcionario): ?>
                                    <option value="<?= e($funcionario['id_funcionario']) ?>"><?= e($funcionario['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div id="field-localidade" class="form-field hidden" style="display:none;">
                        <label>Secretaria<br>
                            <select id="localidade_secretaria" name="localidade_secretaria">
                                <option value="">Selecione a secretaria</option>
                            </select>
                        </label>
                        <label>Divisão<br>
                            <select id="localidade_divisao" name="localidade_divisao" disabled>
                                <option value="">Selecione a divisão</option>
                            </select>
                        </label>
                        <label>Setor<br>
                            <select id="localidade_setor" name="localidade_setor" disabled>
                                <option value="">Selecione o setor</option>
                            </select>
                        </label>
                        <input type="hidden" name="localidade_path" id="localidade_path">
                    </div>
                    <div id="field-observacao" class="form-field hidden" style="display:none;">
                        <label>Observação<br><textarea name="observacao"></textarea></label>
                    </div>
                    <label class="full-row" style="margin-top:30px;">Assinatura:</label>
                    <div class="signature-wrapper full-row"><canvas id="signature-pad"></canvas></div>
                    <button type="button" id="signature-clear" class="full-row" style="width:100%; cursor:pointer;">Limpar Assinatura</button>
                    <input type="hidden" name="assinatura_data" id="assinatura_data">
                    <button type="submit" class="btn-salvar full-row">Gravar Movimentação</button>
                </form>
            </section>
        <?php elseif ($activeTab === 'historico'): ?>
            <section>
                <h2>Histórico de Movimentações</h2>
                <?php if (empty($movimentacoes)): ?>
                    <p>Nenhuma movimentação registrada.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Item</th>
                                <th>Quantidade</th>
                                <th>Uso</th>
                                <th>Funcionário</th>
                                <th>Localidade</th>
                                <th>Assinatura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimentacoes as $movimento): ?>
                                <?php $assinatura = (!empty($movimento['assinatura']) && $movimento['assinatura'] !== '0') ? ('<img src="' . e($movimento['assinatura']) . '" class="img-assinatura-thumbnail" onclick="showSignature(this.src)">') : '---'; ?>
                                <tr>
                                    <td><?= e($movimento['id_movimentacao']) ?></td>
                                    <td><?= e($movimento['data_item']) ?></td>
                                    <td><?= e($movimento['tipo']) ?></td>
                                    <td><?= e($movimento['item_name']) ?></td>
                                    <td><?= e($movimento['quantidade']) ?></td>
                                    <td><?= e($movimento['uso']) ?></td>
                                    <td><?= e($movimento['funcionario'] ?? '---') ?></td>
                                    <td><?= e($movimento['localidade'] ?? '---') ?></td>
                                    <td class="signature-cell"><?= $assinatura ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php elseif ($activeTab === 'relatorio'): ?>
            <section>
                <h2>Relatório de Movimentações</h2>
                <?php if (empty($movementSummary)): ?>
                    <p>Nenhuma movimentação registrada.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantidade Inicial</th>
                                <th>Quantidade Retirada</th>
                                <th>Quantidade Final</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movementSummary as $row): ?>
                                <tr>
                                    <td><?= e($row['item_name']) ?></td>
                                    <td><?= e((string)$row['quantidade_inicial']) ?></td>
                                    <td><?= e((string)$row['quantidade_retirada']) ?></td>
                                    <td><?= e((string)$row['quantidade_final']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer>
        <p>Estoque PHP | Projeto Docker + MySQL.</p>
    </footer>
    <script>
        window.LOCALIDADE_HIERARCHY = <?= json_encode($localidadeHierarchy, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="form.js?v=8"></script>
</body>
</html>
