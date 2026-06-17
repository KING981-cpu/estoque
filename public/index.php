<?php

require_once __DIR__ . '/../app/EnvLoader.php';
require_once __DIR__ . '/../app/InventoryApp.php';

EnvLoader::load(__DIR__ . '/../.env');

$app = new InventoryApp();
$alert = null;
$activeTab = $_GET['tab'] ?? 'items';
$allowedTabs = ['items', 'movimentacoes', 'historico', 'relatorio'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'items';
}
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

    if ($action === 'update_item_settings') {
        $itemId = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $minQuantity = max(0, (int)($_POST['quantidade_minima'] ?? 0));
        $desiredQuantity = max(0, (int)($_POST['quantidade_desejavel'] ?? 0));

        if ($itemId > 0) {
            $app->updateItemThresholds($itemId, $minQuantity, $desiredQuantity);

            $notificationEmail = trim($_POST['notification_email'] ?? '');
            if ($notificationEmail !== '' && filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
                $app->addItemNotificationEmail($itemId, $notificationEmail);
            }
        }

        $alert = 'Configurações do item atualizadas com sucesso.';
        header('Location: ?tab=items');
        exit;
    }
} catch (\Throwable $error) {
    $alert = 'Erro: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
}

$items = $app->getItems();
$funcionarios = $app->getFuncionarios();
$localidades = $app->getLocalidades();
$localidadeHierarchy = $app->getLocalidadeHierarchy();
$movementHistory = $app->getMovements();

$report = $_GET['report'] ?? 'movimentacoes';
$allowedReports = ['movimentacoes', 'consumo', 'estoque'];
if (!in_array($report, $allowedReports, true)) {
    $report = 'movimentacoes';
}

$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 10)));
$page = max(1, (int)($_GET['page'] ?? 1));

$reportYear = preg_match('/^\d{4}$/', $_GET['year'] ?? '') ? $_GET['year'] : date('Y');
$reportMonth = preg_match('/^(0[1-9]|1[0-2])$/', $_GET['month'] ?? '') ? $_GET['month'] : date('m');
$yearMonthFilter = $report === 'consumo' ? sprintf('%s-%s', $reportYear, $reportMonth) : null;
$monthlyConsumption = $app->getMonthlyConsumptionReport($yearMonthFilter);

$movementTotal = count($movementHistory);
$movementPages = (int)max(1, ceil($movementTotal / $perPage));
$movementHistoryPaged = array_slice($movementHistory, ($page - 1) * $perPage, $perPage);

$historyTotal = $movementTotal;
$historyPages = $movementPages;
$historyPaged = $movementHistoryPaged;

$monthlyTotal = count($monthlyConsumption);
$monthlyPages = (int)max(1, ceil($monthlyTotal / $perPage));
$monthlyConsumptionPaged = array_slice($monthlyConsumption, ($page - 1) * $perPage, $perPage);

$itemReportRows = [];
foreach ($items as $item) {
    $itemId = (int)$item['id_item'];
    $stock = $app->getStock($itemId);
    $estimateDays = $app->estimateDaysUntilMinimum($itemId);
    $recommendedQuantity = $app->recommendedPurchaseQuantity($itemId);
    $notificationEmails = $app->getItemNotificationEmails($itemId);

    $itemReportRows[] = [
        'id_item' => $itemId,
        'item_name' => $item['item'],
        'quantidade_minima' => (int)($item['quantidade_minima'] ?? 0),
        'quantidade_desejavel' => (int)($item['quantidade_desejavel'] ?? 0),
        'saldo_atual' => $stock,
        'estimativa' => $estimateDays,
        'recomendacao' => $recommendedQuantity,
        'notification_emails' => $notificationEmails,
    ];
}


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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e((string)$item['id_item']) ?></td>
                                    <td><?= e($item['item']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <p class="note">As informações de quantidade mínima, desejável, e-mails de alerta, estimativa e recomendação de compra estão disponíveis no dashboard Relatórios.</p>
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
                <?php $movimentacoes = $historyPaged; ?>
                <?php if (empty($movimentacoes)): ?>
                    <p>Nenhuma movimentação registrada.</p>
                <?php else: ?>
                    <div class="table-actions">
                        <form method="get" class="inline-form">
                            <input type="hidden" name="tab" value="historico">
                            <label>Registros por página<br>
                                <select name="per_page" onchange="this.form.submit()">
                                    <?php foreach ([5, 10, 20, 50] as $option): ?>
                                        <option value="<?= e((string)$option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e((string)$option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </form>
                    </div>
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
                    <?php if ($historyPages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $historyPages; $i++): ?>
                                <a class="<?= $i === $page ? 'page-link active' : 'page-link' ?>" href="?tab=historico&page=<?= e((string)$i) ?>&per_page=<?= e((string)$perPage) ?>"><?= e((string)$i) ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php elseif ($activeTab === 'relatorio'): ?>
            <section>
                <h2>Dashboard Relatórios</h2>
                <div class="report-buttons">
                    <a class="<?= $report === 'movimentacoes' ? 'report-button active' : 'report-button' ?>" href="?tab=relatorio&report=movimentacoes&per_page=<?= e((string)$perPage) ?>">Relatório de Movimentações</a>
                    <a class="<?= $report === 'consumo' ? 'report-button active' : 'report-button' ?>" href="?tab=relatorio&report=consumo&per_page=<?= e((string)$perPage) ?>">Relatório de Consumo Mensal</a>
                    <a class="<?= $report === 'estoque' ? 'report-button active' : 'report-button' ?>" href="?tab=relatorio&report=estoque&per_page=<?= e((string)$perPage) ?>">Relatório de Estoque e Estimativa</a>
                </div>
            </section>

            <?php if ($report === 'movimentacoes'): ?>
                <section>
                    <h2>Relatório de Movimentações</h2>
                    <?php if (empty($movementHistory)): ?>
                        <p>Nenhuma movimentação registrada.</p>
                    <?php else: ?>
                        <div class="table-actions">
                            <form method="get" class="inline-form">
                                <input type="hidden" name="tab" value="relatorio">
                                <input type="hidden" name="report" value="movimentacoes">
                                <label>Registros por página<br>
                                    <select name="per_page" onchange="this.form.submit()">
                                        <?php foreach ([5, 10, 20, 50] as $option): ?>
                                            <option value="<?= e((string)$option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e((string)$option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </form>
                        </div>
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
                                <?php foreach ($movementHistoryPaged as $movimento): ?>
                                    <?php $assinatura = (!empty($movimento['assinatura']) && $movimento['assinatura'] !== '0') ? ('<img src="' . e($movimento['assinatura']) . '" class="img-assinatura-thumbnail" onclick="showSignature(this.src)">') : '---'; ?>
                                    <tr>
                                        <td><?= e((string)$movimento['id_movimentacao']) ?></td>
                                        <td><?= e($movimento['data_item']) ?></td>
                                        <td><?= e($movimento['tipo']) ?></td>
                                        <td><?= e($movimento['item_name']) ?></td>
                                        <td><?= e((string)$movimento['quantidade']) ?></td>
                                        <td><?= e($movimento['uso']) ?></td>
                                        <td><?= e($movimento['funcionario'] ?? '---') ?></td>
                                        <td><?= e($movimento['localidade'] ?? '---') ?></td>
                                        <td class="signature-cell"><?= $assinatura ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($movementPages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $movementPages; $i++): ?>
                                    <a class="<?= $i === $page ? 'page-link active' : 'page-link' ?>" href="?tab=relatorio&report=movimentacoes&page=<?= e((string)$i) ?>&per_page=<?= e((string)$perPage) ?>"><?= e((string)$i) ?></a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php elseif ($report === 'consumo'): ?>
                <section>
                    <h2>Relatório de Consumo Mensal</h2>
                    <form method="get" class="report-filter-form">
                        <input type="hidden" name="tab" value="relatorio">
                        <input type="hidden" name="report" value="consumo">
                        <label>Ano<br>
                            <select name="year">
                                <?php for ($year = date('Y'); $year >= date('Y') - 3; $year--): ?>
                                    <option value="<?= e((string)$year) ?>" <?= $reportYear === (string)$year ? 'selected' : '' ?>><?= e((string)$year) ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label>Mês<br>
                            <select name="month">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <?php $value = sprintf('%02d', $m); ?>
                                    <option value="<?= e($value) ?>" <?= $reportMonth === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label>Registros por página<br>
                            <select name="per_page">
                                <?php foreach ([5, 10, 20, 50] as $option): ?>
                                    <option value="<?= e((string)$option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e((string)$option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit">Filtrar</button>
                    </form>
                    <?php if (empty($monthlyConsumptionPaged)): ?>
                        <p>Nenhum consumo registrado para o mês selecionado.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Mês</th>
                                    <th>Item</th>
                                    <th>Consumo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlyConsumptionPaged as $row): ?>
                                    <tr>
                                        <td><?= e($row['mes']) ?></td>
                                        <td><?= e($row['item_name']) ?></td>
                                        <td><?= e((string)$row['consumo_mensal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </table>
                        <?php if ($monthlyPages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $monthlyPages; $i++): ?>
                                    <a class="<?= $i === $page ? 'page-link active' : 'page-link' ?>" href="?tab=relatorio&report=consumo&page=<?= e((string)$i) ?>&per_page=<?= e((string)$perPage) ?>&year=<?= e($reportYear) ?>&month=<?= e($reportMonth) ?>"><?= e((string)$i) ?></a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php elseif ($report === 'estoque'): ?>
                <section>
                    <h2>Relatório de Estoque e Estimativa</h2>
                    <?php if (empty($itemReportRows)): ?>
                        <p>Nenhum item cadastrado.</p>
                    <?php else: ?>
                        <?php $itemTotal = count($itemReportRows); ?>
                        <?php $itemPages = (int)max(1, ceil($itemTotal / $perPage)); ?>
                        <?php $itemReportRowsPaged = array_slice($itemReportRows, ($page - 1) * $perPage, $perPage); ?>
                        <div class="table-actions">
                            <form method="get" class="inline-form">
                                <input type="hidden" name="tab" value="relatorio">
                                <input type="hidden" name="report" value="estoque">
                                <label>Registros por página<br>
                                    <select name="per_page" onchange="this.form.submit()">
                                        <?php foreach ([5, 10, 20, 50] as $option): ?>
                                            <option value="<?= e((string)$option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e((string)$option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </form>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Saldo Atual</th>
                                    <th>Quantidade Mínima</th>
                                    <th>Quantidade Desejável</th>
                                    <th>Estimativa até o mínimo</th>
                                    <th>Recomendação de compra</th>
                                    <th>Destinatários</th>
                                    <th>Configurar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itemReportRowsPaged as $row): ?>
                                    <tr>
                                        <td><?= e($row['item_name']) ?></td>
                                        <td><?= e((string)$row['saldo_atual']) ?></td>
                                        <td><?= e((string)$row['quantidade_minima']) ?></td>
                                        <td><?= e((string)$row['quantidade_desejavel']) ?></td>
                                        <td><?= e($row['estimativa'] === null ? 'sem consumo suficiente' : sprintf('%.1f dias', $row['estimativa'])) ?></td>
                                        <td><?= e((string)$row['recomendacao']) ?></td>
                                        <td><?= e(implode(', ', $row['notification_emails'])) ?></td>
                                        <td>
                                            <form method="post" class="item-settings-form">
                                                <input type="hidden" name="action" value="update_item_settings">
                                                <input type="hidden" name="item_id" value="<?= e((string)$row['id_item']) ?>">
                                                <label>Min<br><input type="number" name="quantidade_minima" min="0" value="<?= e((string)$row['quantidade_minima']) ?>" style="width:70px"></label>
                                                <label>Desejável<br><input type="number" name="quantidade_desejavel" min="0" value="<?= e((string)$row['quantidade_desejavel']) ?>" style="width:70px"></label>
                                                <label>Email<br><input type="email" name="notification_email" placeholder="destinatário" style="width:155px"></label>
                                                <button type="submit">Salvar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($itemPages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $itemPages; $i++): ?>
                                    <a class="<?= $i === $page ? 'page-link active' : 'page-link' ?>" href="?tab=relatorio&report=estoque&page=<?= e((string)$i) ?>&per_page=<?= e((string)$perPage) ?>"><?= e((string)$i) ?></a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
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
