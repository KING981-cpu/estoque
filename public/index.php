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
        $category = trim($_POST['categoria'] ?? '');
        $itemName = trim($_POST['item_name'] ?? '');
        $condition = trim($_POST['condicao'] ?? 'novo');
        if (!in_array($condition, ['novo', 'usado'], true)) {
            $condition = 'novo';
        }
        $quantity = isset($_POST['item_quantity']) ? (int)$_POST['item_quantity'] : 0;
        $tipoRegistro = isset($_POST['tipo_registro']) ? (int)$_POST['tipo_registro'] : 1;
        $tipoItem = trim($_POST['tipo_item'] ?? 'temporario');
        $synonyms = trim($_POST['sinonimos'] ?? '');
        $itemId = $app->addItem($category, $itemName, $condition, $quantity, $tipoRegistro, $synonyms, $tipoItem);
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
            'condicao_item' => trim($_POST['condicao_item'] ?? ''),
            'tipo_registro' => isset($_POST['tipo_registro']) ? (int)$_POST['tipo_registro'] : null,
            'observacao' => trim($_POST['observacao'] ?? ''),
            'patrimonio' => trim($_POST['patrimonio'] ?? ''),
            'id_funcionario' => !empty($_POST['id_funcionario']) ? (int)$_POST['id_funcionario'] : null,
            'id_localidade' => !empty($_POST['id_localidade']) ? (int)$_POST['id_localidade'] : null,
            'localidade_path' => isset($_POST['localidade_path']) ? trim($_POST['localidade_path']) : null,
        ]);
        $alert = 'Movimentação registrada com sucesso.';
        header('Location: ?tab=movimentacoes');
        exit;
    }

    if ($action === 'update_item_settings') {
        $itemId = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $minQuantity = max(0, (int)($_POST['quantidade_minima'] ?? 0));
        $desiredQuantity = max(0, (int)($_POST['quantidade_desejavel'] ?? 0));
        $purchaseBuffer = max(0, (int)($_POST['quantidade_sobra_compra'] ?? 0));
        $synonyms = trim($_POST['sinonimos'] ?? '');
        $unidadeMedida = trim($_POST['unidade_medida'] ?? 'un');
        $requerComprimento = !empty($_POST['requer_comprimento']) ? 1 : 0;
        $tipoItem = trim($_POST['tipo_item'] ?? 'temporario');

        if ($itemId > 0) {
            $app->updateItemThresholds($itemId, $minQuantity, $desiredQuantity, $purchaseBuffer, $synonyms, $unidadeMedida, $requerComprimento, $tipoItem);

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
$popularItems = $app->getPopularItems(20);
$categories = array_unique(array_map(function ($item) {
    $category = trim($item['categoria'] ?? '');
    return $category !== '' ? $category : 'Sem categoria';
}, $items));
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
$funcionarios = $app->getFuncionarios();
$localidades = $app->getLocalidades();
$localidadeHierarchy = $app->getLocalidadeHierarchy();

$historyStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_inicio'] ?? '') ? $_GET['data_inicio'] : null;
$historyEnd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_fim'] ?? '') ? $_GET['data_fim'] : null;
$historyPatrimonio = trim($_GET['patrimonio'] ?? '');
$historyView = $_GET['history_view'] ?? 'itens';
if (!in_array($historyView, ['itens', 'movimentacoes'], true)) {
    $historyView = 'itens';
}
$movementHistory = $app->getMovements(null, $historyStart, $historyEnd, $historyPatrimonio);
usort($movementHistory, function (array $left, array $right): int {
    return ((int)($left['id_movimentacao'] ?? 0) <=> (int)($right['id_movimentacao'] ?? 0));
});

$historyItems = $items;
usort($historyItems, function (array $left, array $right): int {
    return ((int)($left['id_item'] ?? 0) <=> (int)($right['id_item'] ?? 0));
});

if (($GLOBALS['_GET']['ajax'] ?? '') === 'history') {
    $historyRows = array_map(function (array $movimento): array {
        $assinatura = (!empty($movimento['assinatura']) && $movimento['assinatura'] !== '0') ? $movimento['assinatura'] : null;
        return [
            'data_item' => $movimento['data_item'] ?? '',
            'tipo' => $movimento['tipo'] ?? '',
            'item_name' => $movimento['item_name'] ?? '',
            'condicao_item' => $movimento['condicao_item'] ?? '---',
            'quantidade' => $movimento['quantidade'] ?? '',
            'uso' => $movimento['uso'] ?? '',
            'funcionario' => $movimento['funcionario'] ?? '---',
            'localidade' => $movimento['localidade'] ?? '---',
            'patrimonio' => $movimento['patrimonio'] ?? '---',
            'assinatura' => $assinatura,
        ];
    }, $movementHistory);

    header('Content-Type: application/json');
    echo json_encode(['items' => $historyRows]);
    exit;
}

$report = $_GET['report'] ?? 'movimentacoes';
$allowedReports = ['movimentacoes', 'consumo', 'estoque', 'mailhog'];
if (!in_array($report, $allowedReports, true)) {
    $report = 'movimentacoes';
}

$reportStartDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'] ?? '') ? $_GET['start_date'] : null;
$reportEndDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'] ?? '') ? $_GET['end_date'] : null;
$reportTipoRegistro = isset($_GET['tipo_registro']) && in_array($_GET['tipo_registro'], ['0','1','all'], true) ? $_GET['tipo_registro'] : '1';
$tipoRegistroFilter = $reportTipoRegistro === 'all' ? null : (int)$reportTipoRegistro;
$movementReport = $app->getMovementReport(null, $tipoRegistroFilter, $reportStartDate, $reportEndDate);
if (!in_array($report, $allowedReports, true)) {
    $report = 'movimentacoes';
}

$mailhogUrl = getenv('MAILHOG_WEB_URL') ?: 'http://localhost:8025';

$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 10)));
$page = max(1, (int)($_GET['page'] ?? 1));

$reportYear = preg_match('/^\d{4}$/', $_GET['year'] ?? '') ? $_GET['year'] : date('Y');
$reportMonth = preg_match('/^(0[1-9]|1[0-2])$/', $_GET['month'] ?? '') ? $_GET['month'] : date('m');
$reportTipoRegistro = isset($_GET['tipo_registro']) && in_array($_GET['tipo_registro'], ['0','1','all'], true) ? $_GET['tipo_registro'] : '1';
$tipoRegistroFilter = $reportTipoRegistro === 'all' ? null : (int)$reportTipoRegistro;
$yearMonthFilter = $report === 'consumo' ? sprintf('%s-%s', $reportYear, $reportMonth) : null;
$monthlyConsumption = $app->getMonthlyConsumptionReport($yearMonthFilter, $tipoRegistroFilter);

$movementTotal = count($movementReport);
$movementPages = (int)max(1, ceil($movementTotal / $perPage));
$movementHistoryPaged = array_slice($movementReport, ($page - 1) * $perPage, $perPage);

$historyTotal = count($movementHistory);
$historyPages = (int)max(1, ceil($historyTotal / $perPage));
$historyPaged = array_slice($movementHistory, ($page - 1) * $perPage, $perPage);

$monthlyTotal = count($monthlyConsumption);
$monthlyPages = (int)max(1, ceil($monthlyTotal / $perPage));
$monthlyConsumptionPaged = array_slice($monthlyConsumption, ($page - 1) * $perPage, $perPage);

$itemReportRows = [];
foreach ($items as $item) {
    if (isset($item['tipo_registro']) && (int)$item['tipo_registro'] === 0) {
        continue;
    }

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
        'quantidade_sobra_compra' => (int)($item['quantidade_sobra_compra'] ?? 0),
        'sinonimos' => $item['sinonimos'] ?? '',
        'saldo_atual' => $stock,
        'estimativa' => $estimateDays,
        'recomendacao' => $recommendedQuantity,
        'notification_emails' => $notificationEmails,
        'tipo_item' => ($item['tipo_item'] ?? 'temporario') === 'permanente' ? 'permanente' : 'temporario',
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css">
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
                <form method="post" class="small-form" id="add-item-form">
                    <input type="hidden" name="action" value="add_item">
                    <label>Categoria<br><input name="categoria" required placeholder="Ex: Cabos, Periféricos"></label>
                    <label>Nome<br><input name="item_name" required placeholder="Ex: Cabo de Rede"></label>
                    <label>Quantidade<br><input type="number" name="item_quantity" min="0" value="0" required></label>
                    <label>Tipo de cadastro<br>
                        <select name="tipo_registro" id="add-item-tipo_registro" required>
                            <option value="1">Novo</option>
                            <option value="0">Existente</option>
                        </select>
                    </label>
                    <div id="field-add-item-condicao" style="display:none;">
                        <label>Condição<br>
                            <select name="condicao" id="add-item-condicao">
                                <option value="novo">Novo</option>
                                <option value="usado">Usado</option>
                            </select>
                        </label>
                    </div>
                    <label>Classificação do item<br>
                        <select name="tipo_item" required>
                            <option value="temporario">Item temporário</option>
                            <option value="permanente">Item permanente</option>
                        </select>
                    </label>
                    <label>Sinônimos (vírgula separado)<br><input name="sinonimos" required placeholder="Cabo de Rede, Patch Cord, RJ45"></label>
                    <button type="submit">Cadastrar Item</button>
                </form>

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
                    <label>Categoria<br>
                        <select name="item_category" id="item_category" required>
                            <option value="" disabled selected>Selecione a categoria</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category) ?>"><?= e($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Item<br>
                        <select name="id_item" id="item_search" disabled required>
                            <option value="" disabled selected>Selecione a categoria primeiro</option>
                        </select>
                    </label>
                    <label>Patrimônio (opcional)<br><input type="text" name="patrimonio" placeholder="Ex: 1234, 5678"></label>
                    <label>Quantidade<br><input type="number" name="quantidade" min="1" value="1" required></label>
                    <small id="saldo-disponivel" style="display:none; color:#334155; margin-top:6px; display:block;">Disponível: 0 un</small>
                    <div id="field-comprimento" style="display:none; margin-top:10px;">
                        <label>Metragem/Comprimento<br><input type="number" step="0.01" name="comprimento" min="0" value=""></label>
                    </div>
                    <small id="quantidade-erro" style="display:none; color:#b91c1c; margin-top:6px; display:block;">Quantidade superior ao saldo disponível!</small>
                    <div id="field-item-condition" class="form-field">
                        <label>Condição do item<br>
                            <select name="condicao_item">
                                <option value="" disabled selected>Selecione a condição</option>
                                <option value="novo">Novo</option>
                                <option value="usado">Usado</option>
                            </select>
                        </label>
                    </div>
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
                    <div id="submit-undo-wrapper" class="full-row" style="display:none; align-items:center; gap:12px; margin-top:16px;">
                        <div id="undo-message">
                            Movimentação agendada. Desfazer em <span id="undo-timer">5</span>s...
                        </div>
                        <button type="button" id="undo-button">Desfazer</button>
                    </div>
                </form>
            </section>
        <?php elseif ($activeTab === 'historico'): ?>
            <section>
                <h2><?= $historyView === 'itens' ? 'Itens' : 'Histórico de Movimentações' ?></h2>
                <div class="report-buttons">
                    <a class="<?= $historyView === 'itens' ? 'report-button active' : 'report-button' ?>" href="?tab=historico&history_view=itens&per_page=<?= e((string)$perPage) ?><?php if ($historyStart !== null): ?>&data_inicio=<?= e(urlencode($historyStart)) ?><?php endif; ?><?php if ($historyEnd !== null): ?>&data_fim=<?= e(urlencode($historyEnd)) ?><?php endif; ?><?php if ($historyPatrimonio !== ''): ?>&patrimonio=<?= e(urlencode($historyPatrimonio)) ?><?php endif; ?>">Itens</a>
                    <a class="<?= $historyView === 'movimentacoes' ? 'report-button active' : 'report-button' ?>" href="?tab=historico&history_view=movimentacoes&per_page=<?= e((string)$perPage) ?><?php if ($historyStart !== null): ?>&data_inicio=<?= e(urlencode($historyStart)) ?><?php endif; ?><?php if ($historyEnd !== null): ?>&data_fim=<?= e(urlencode($historyEnd)) ?><?php endif; ?><?php if ($historyPatrimonio !== ''): ?>&patrimonio=<?= e(urlencode($historyPatrimonio)) ?><?php endif; ?>">Movimentações</a>
                </div>
                <?php $movimentacoes = $historyPaged; ?>
                <div class="table-actions">
                    <form method="get" class="historico-filter-form">
                        <input type="hidden" name="tab" value="historico">
                        <input type="hidden" name="history_view" value="<?= e($historyView) ?>">
                        <?php if ($historyView === 'movimentacoes'): ?>
                            <label>Data inicial<br>
                                <input type="date" name="data_inicio" value="<?= e($historyStart ?? '') ?>">
                            </label>
                            <label>Data final<br>
                                <input type="date" name="data_fim" value="<?= e($historyEnd ?? '') ?>">
                            </label>
                            <label>Patrimônio<br>
                                <input type="text" name="patrimonio" value="<?= e($historyPatrimonio ?? '') ?>" placeholder="Pesquisar patrimônio">
                            </label>
                        <?php endif; ?>
                        <label>Registros por página<br>
                            <select name="per_page">
                                <?php foreach ([5, 10, 20, 50] as $option): ?>
                                    <option value="<?= e((string)$option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e((string)$option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit">Filtrar</button>
                    </form>
                </div>
                <div id="history-results">
                    <?php if ($historyView === 'itens'): ?>
                        <?php if (empty($items)): ?>
                            <p>Nenhum item cadastrado.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Categoria</th>
                                        <th>Nome</th>
                                        <th>Condição</th>
                                        <th>Quantidade</th>
                                        <th>Tipo de cadastro</th>
                                        <th>Classificação</th>
                                        <th>Sinônimos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historyItems as $item): ?>
                                        <tr>
                                            <td><?= e((string)$item['id_item']) ?></td>
                                            <td><?= e(trim($item['categoria'] ?? '') !== '' ? $item['categoria'] : 'Sem categoria') ?></td>
                                            <td><?= e($item['item']) ?></td>
                                            <td><?= e($item['condicao'] ?? 'novo') ?></td>
                                            <td><?= e((string)($item['saldo_atual'] ?? 0)) ?></td>
                                            <td><?= e((int)($item['tipo_registro'] ?? 1) === 1 ? 'Novo' : 'Existente') ?></td>
                                            <td><?= e(($item['tipo_item'] ?? 'temporario') === 'permanente' ? 'Item permanente' : 'Item temporário') ?></td>
                                            <td><?= e($item['sinonimos'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (empty($movimentacoes)): ?>
                            <p>Nenhuma movimentação registrada.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Item</th>
                                        <th>Condição</th>
                                        <th>Quantidade</th>
                                        <th>Uso</th>
                                        <th>Funcionário</th>
                                        <th>Localidade</th>
                                        <th>Patrimônio</th>
                                        <th>Assinatura</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimentacoes as $movimento): ?>
                                        <?php $assinatura = (!empty($movimento['assinatura']) && $movimento['assinatura'] !== '0') ? ('<img src="' . e($movimento['assinatura']) . '" class="img-assinatura-thumbnail" onclick="showSignature(this.src)">') : '---'; ?>
                                        <tr>
                                            <td><?= e($movimento['data_item']) ?></td>
                                            <td><?= e($movimento['tipo']) ?></td>
                                            <td><?= e($movimento['item_name']) ?></td>
                                            <td><?= e($movimento['condicao_item'] ?? '---') ?></td>
                                            <td><?= e($movimento['quantidade']) ?></td>
                                            <td><?= e($movimento['uso']) ?></td>
                                            <td><?= e($movimento['funcionario'] ?? '---') ?></td>
                                            <td><?= e($movimento['localidade'] ?? '---') ?></td>
                                            <td><?= e($movimento['patrimonio'] ?? '---') ?></td>
                                            <td class="signature-cell"><?= $assinatura ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($historyPages > 1): ?>
                                <div class="pagination">
                                    <?php for ($i = 1; $i <= $historyPages; $i++): ?>
                                        <a class="<?= $i === $page ? 'page-link active' : 'page-link' ?>" href="?tab=historico&page=<?= e((string)$i) ?>&history_view=movimentacoes&per_page=<?= e((string)$perPage) ?><?php if ($historyStart !== null): ?>&data_inicio=<?= e(urlencode($historyStart)) ?><?php endif; ?><?php if ($historyEnd !== null): ?>&data_fim=<?= e(urlencode($historyEnd)) ?><?php endif; ?><?php if ($historyPatrimonio !== ''): ?>&patrimonio=<?= e(urlencode($historyPatrimonio)) ?><?php endif; ?>"><?= e((string)$i) ?></a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($activeTab === 'relatorio'): ?>
            <section>
                <h2>Dashboard Relatórios</h2>
                <div class="report-buttons">
                    <a class="<?= $report === 'movimentacoes' ? 'report-button active' : 'report-button' ?>" href="?tab=relatorio&report=movimentacoes&per_page=<?= e((string)$perPage) ?>">Relatório de Movimentações</a>
                    <a class="<?= $report === 'consumo' ? 'report-button active' : 'report-button' ?>" href="?tab=relatorio&report=consumo&per_page=<?= e((string)$perPage) ?>">Relatório de Consumo Mensal</a>
                    <a class="<?= $report === 'estoque' ? 'report-button active' : 'report-button' ?>" href="?tab=relatorio&report=estoque&per_page=<?= e((string)$perPage) ?>">Relatório de Estoque e Estimativa</a>
                    <a class="<?= $report === 'mailhog' ? 'report-button active' : 'report-button' ?>" href="?tab=relatorio&report=mailhog">MailHog</a>
                </div>
            </section>

            <?php if ($report === 'movimentacoes'): ?>
                <section>
                    <h2>Relatório de Movimentações</h2>
                        <form method="get" class="report-filter-form">
                            <input type="hidden" name="tab" value="relatorio">
                            <input type="hidden" name="report" value="movimentacoes">
                            <input type="hidden" name="page" value="1">
                            <div class="table-actions">
                                <label>Data início<br>
                                    <input type="date" name="start_date" value="<?= e($_GET['start_date'] ?? '') ?>">
                                </label>
                                <label>Data fim<br>
                                    <input type="date" name="end_date" value="<?= e($_GET['end_date'] ?? '') ?>">
                                </label>
                                <label>Tipo de registro<br>
                                    <select name="tipo_registro">
                                        <option value="all" <?= $reportTipoRegistro === 'all' ? 'selected' : '' ?>>Todos</option>
                                        <option value="1" <?= $reportTipoRegistro === '1' ? 'selected' : '' ?>>Apenas Itens Permanentes</option>
                                        <option value="0" <?= $reportTipoRegistro === '0' ? 'selected' : '' ?>>Apenas Itens Temporários</option>
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
                            </div>
                        </form>
                    <?php if (empty($movementReport)): ?>
                        <p>Nenhuma movimentação registrada.</p>
                    <?php else: ?>
                        <?php $exportMovementBaseUrl = 'exportar.php?' . http_build_query(array_filter([
                            'tab' => 'relatorio',
                            'report' => 'movimentacoes',
                            'tipo_registro' => $_GET['tipo_registro'] ?? null,
                            'start_date' => $_GET['start_date'] ?? null,
                            'end_date' => $_GET['end_date'] ?? null,
                        ], function ($value) { return $value !== null && $value !== ''; })); ?>
                        <div class="table-actions">
                            <div class="export-buttons">
                                <a class="report-button" href="<?= e($exportMovementBaseUrl) ?>&format=csv">Exportar para CSV</a>
                                <a class="report-button" href="<?= e($exportMovementBaseUrl) ?>&format=pdf">Exportar para PDF</a>
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Item</th>
                                    <th>Condição</th>
                                    <th>Quantidade</th>
                                    <th>Uso</th>
                                    <th>Funcionário</th>
                                    <th>Localidade</th>
                                    <th>Patrimônio</th>
                                    <th>Assinatura</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movementHistoryPaged as $movimento): ?>
                                    <?php $assinatura = (!empty($movimento['assinatura']) && $movimento['assinatura'] !== '0') ? ('<img src="' . e($movimento['assinatura']) . '" class="img-assinatura-thumbnail" onclick="showSignature(this.src)">') : '---'; ?>
                                    <tr>
                                        <td><?= e($movimento['data_item']) ?></td>
                                        <td><?= e($movimento['tipo']) ?></td>
                                        <td><?= e($movimento['item_name']) ?></td>
                                        <td><?= e($movimento['condicao_item'] ?? '---') ?></td>
                                        <td><?= e((string)$movimento['quantidade']) ?></td>
                                        <td><?= e($movimento['uso']) ?></td>
                                        <td><?= e($movimento['funcionario'] ?? '---') ?></td>
                                        <td><?= e($movimento['localidade'] ?? '---') ?></td>
                                        <td><?= e($movimento['patrimonio'] ?? '---') ?></td>
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
                        <label>Tipo de registro<br>
                            <select name="tipo_registro">
                                <option value="1" <?= $reportTipoRegistro === '1' ? 'selected' : '' ?>>Apenas Itens Permanentes</option>
                                <option value="0" <?= $reportTipoRegistro === '0' ? 'selected' : '' ?>>Apenas Itens Temporários</option>
                                <option value="all" <?= $reportTipoRegistro === 'all' ? 'selected' : '' ?>>Todos</option>
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
                                    <a class="<?= $i === $page ? 'page-link active' : 'page-link' ?>" href="?tab=relatorio&report=consumo&page=<?= e((string)$i) ?>&per_page=<?= e((string)$perPage) ?>&year=<?= e($reportYear) ?>&month=<?= e($reportMonth) ?>&tipo_registro=<?= e($reportTipoRegistro) ?>"><?= e((string)$i) ?></a>
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
                                    <th>Sobra Compra</th>
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
                                        <td><?= e((string)($row['quantidade_sobra_compra'] ?? 0)) ?></td>
                                        <td><?= e($row['estimativa'] === null ? 'sem consumo suficiente' : sprintf('%.1f dias', $row['estimativa'])) ?></td>
                                        <td><?= e((string)$row['recomendacao']) ?></td>
                                        <td><?= e(implode(', ', $row['notification_emails'])) ?></td>
                                        <td>
                                            <form method="post" class="item-settings-form">
                                                <input type="hidden" name="action" value="update_item_settings">
                                                <input type="hidden" name="item_id" value="<?= e((string)$row['id_item']) ?>">
                                                <label>Min<br><input type="number" name="quantidade_minima" min="0" value="<?= e((string)$row['quantidade_minima']) ?>" style="width:70px"></label>
                                                <label>Desejável<br><input type="number" name="quantidade_desejavel" min="0" value="<?= e((string)$row['quantidade_desejavel']) ?>" style="width:70px"></label>
                                                <label>Sobra acima do desejável<br><input type="number" name="quantidade_sobra_compra" min="0" value="<?= e((string)($row['quantidade_sobra_compra'] ?? 0)) ?>" style="width:70px"></label>
                                                <label>Classificação<br>
                                                    <select name="tipo_item" style="width:140px">
                                                        <option value="temporario" <?= (($row['tipo_item'] ?? 'temporario') === 'temporario') ? 'selected' : '' ?>>Item temporário</option>
                                                        <option value="permanente" <?= (($row['tipo_item'] ?? 'temporario') === 'permanente') ? 'selected' : '' ?>>Item permanente</option>
                                                    </select>
                                                </label>
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
            <?php elseif ($report === 'mailhog'): ?>
                <section>
                    <h2>MailHog</h2>
                    <p>Visualize as mensagens de e-mail geradas pelo sistema diretamente dentro do site.</p>
                    <div class="mailhog-frame-wrapper">
                        <iframe class="mailhog-frame" src="<?= e($mailhogUrl) ?>" title="MailHog"></iframe>
                    </div>
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
    <script>
        window.ITEMS = <?= json_encode(array_map(function ($item) use ($app) {
            $category = trim($item['categoria'] ?? '');
            return [
                'value' => (string)(int)$item['id_item'],
                'text' => $item['item'] . ' (' . ($item['condicao'] ?? 'novo') . ')',
                'category' => $category !== '' ? $category : 'Sem categoria',
                'tokens' => $item['item'] . ' ' . ($item['condicao'] ?? 'novo') . ($item['sinonimos'] ? ', ' . $item['sinonimos'] : ''),
                'synonyms' => $item['sinonimos'] ?? '',
                'stock' => $app->getStock((int)$item['id_item']),
                'tipo_registro' => (int)($item['tipo_registro'] ?? 1),
                'tipo_item' => ($item['tipo_item'] ?? 'temporario') === 'permanente' ? 'permanente' : 'temporario',
                'unidade' => $item['unidade_medida'] ?? 'un',
                'requer_comprimento' => (int)($item['requer_comprimento'] ?? 0),
                'condicao' => $item['condicao'] ?? 'novo',
            ];
        }, $items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function showSignature(src) {
            const overlay = document.querySelector('.signature-modal-overlay');
            const image = document.querySelector('.signature-modal-img');
            if (!overlay || !image) {
                return;
            }
            image.src = src;
            overlay.classList.add('active');
        }

        function hideSignature() {
            const overlay = document.querySelector('.signature-modal-overlay');
            if (!overlay) {
                return;
            }
            overlay.classList.remove('active');
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideSignature();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <div class="signature-modal-overlay" onclick="hideSignature()">
        <div class="signature-modal-content" onclick="event.stopPropagation();">
            <button type="button" class="signature-modal-close" onclick="hideSignature()" aria-label="Fechar">×</button>
            <img src="" alt="Assinatura ampliada" class="signature-modal-img">
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js?v=1"></script>
    <script src="form.js?v=10"></script>
</body>
</html>
