<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ItemModel.php';
require_once __DIR__ . '/MovementModel.php';
require_once __DIR__ . '/FuncionarioModel.php';
require_once __DIR__ . '/LocalidadeModel.php';
require_once __DIR__ . '/SpreadsheetLocationLoader.php';
require_once __DIR__ . '/EnvLoader.php';

class InventoryApp
{
    private Database $database;
    private ItemModel $items;
    private MovementModel $movements;
    private FuncionarioModel $funcionarios;
    private LocalidadeModel $localidades;
    private SpreadsheetLocationLoader $spreadsheetLocationLoader;

    public function __construct()
    {
        EnvLoader::load(__DIR__ . '/../.env');
        $this->database = new Database();
        $this->items = new ItemModel($this->database);
        $this->movements = new MovementModel($this->database);
        $this->funcionarios = new FuncionarioModel($this->database);
        $this->localidades = new LocalidadeModel($this->database);
        $this->spreadsheetLocationLoader = new SpreadsheetLocationLoader(__DIR__ . '/../ORGANOGRAMA DAS SECRETARIAS CHEFES E DIRETORES - ATUALIZADO 2026-04-20.xlsx');
    }

    public function getItems(): array
    {
        return $this->items->all();
    }

    public function getPopularItems(int $limit = 20): array
    {
        return $this->items->popular($limit);
    }

    public function getFuncionarios(): array
    {
        return $this->funcionarios->all();
    }

    public function getLocalidades(): array
    {
        return $this->localidades->all();
    }

    public function getLocalidadeHierarchy(): array
    {
        return $this->spreadsheetLocationLoader->loadHierarchy();
    }

    public function getMovements(int $itemId = null, ?string $startDate = null, ?string $endDate = null, string $patrimonio = ''): array
    {
        return $this->movements->history($itemId, $startDate, $endDate, $patrimonio);
    }

    public function getMovementReport(int $itemId = null, ?int $tipoRegistro = 1, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->movements->movementReport($itemId, $tipoRegistro, $startDate, $endDate);
    }

    public function getMovementSummary(): array
    {
        return $this->movements->summary();
    }

    public function addItem(string $category, string $name, string $condition = 'novo', int $quantity = 0, int $tipoRegistro = 1, string $synonyms = '', string $tipoItem = 'temporario'): int
    {
        if ($category === '') {
            throw new \InvalidArgumentException('A categoria do item é obrigatória.');
        }
        if ($name === '') {
            throw new \InvalidArgumentException('O nome do item é obrigatório.');
        }
        if ($quantity < 0) {
            throw new \InvalidArgumentException('A quantidade deve ser zero ou maior.');
        }
        if ($synonyms === '') {
            throw new \InvalidArgumentException('Os sinônimos são obrigatórios.');
        }

        $tipoItem = $this->normalizeTipoItem($tipoItem);
        $itemId = $this->items->getOrCreateByName($name, $condition, $tipoRegistro, $synonyms, 'un', 0, $quantity, $category, $tipoItem);
        return $itemId;
    }

    public function addFuncionario(string $nome): int
    {
        return $this->funcionarios->save($nome);
    }

    public function addLocalidade(string $local): int
    {
        return $this->localidades->save($local);
    }

    public function getStock(int $itemId): int
    {
        return $this->items->getStock($itemId);
    }

    /**
     * Retorna o item com os campos de limite mínimo e desejável.
     */
    public function getItemWithThresholds(int $itemId): ?array
    {
        return $this->items->findWithThresholds($itemId);
    }

    /**
     * Busca os e-mails cadastrados para notificações deste item.
     */
    public function getItemNotificationEmails(int $itemId): array
    {
        return $this->items->listNotificationEmails($itemId);
    }

    public function addItemNotificationEmail(int $itemId, string $email): int
    {
        return $this->items->addNotificationEmail($itemId, $email);
    }

    public function updateItemThresholds(int $itemId, int $minQuantity, int $desiredQuantity, int $purchaseBuffer = 0, string $synonyms = '', string $unidadeMedida = 'un', int $requerComprimento = 0, ?string $tipoItem = null): bool
    {
        return $this->items->updateThresholds($itemId, $minQuantity, $desiredQuantity, $purchaseBuffer, $synonyms, $unidadeMedida, $requerComprimento, $tipoItem);
    }

    /**
     * Retorna o relatório de consumo mensal de cada item.
     */
    public function getMonthlyConsumptionReport(string $yearMonth = null, ?int $tipoRegistro = null): array
    {
        return $this->movements->monthlyConsumptionReport($yearMonth, $tipoRegistro);
    }

    public function recordMovimentacao(array $data): int
    {
        $itemId = !empty($data['id_item']) ? (int)$data['id_item'] : null;
        $itemName = trim($data['item_name'] ?? '');

        if ($itemId === null) {
            if ($itemName === '') {
                throw new \InvalidArgumentException('O nome do item é obrigatório.');
            }
            $itemId = $this->items->getOrCreateByName($itemName);
        }

        $localidadeId = null;
        if (!empty($data['id_localidade'])) {
            $localidadeId = (int)$data['id_localidade'];
        } elseif (!empty($data['localidade_path'])) {
            $localidadeId = $this->localidades->ensureExists(trim($data['localidade_path']));
        }

        $itemTipo = $this->items->getTipoItem($itemId);
        $movementTipoRegistro = $this->isItemPermanent($itemTipo) ? 1 : 0;

        $quantidade = max(0, (int)$data['quantidade']);
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('A quantidade movimentada deve ser maior que zero.');
        }

        if ($this->isItemPermanent($itemTipo) && $quantidade !== 1) {
            throw new \InvalidArgumentException('Para itens permanentes, a quantidade deve ser igual a 1.');
        }

        $patrimonio = trim($data['patrimonio'] ?? '');
        if ($this->isItemPermanent($itemTipo) && $patrimonio === '') {
            throw new \InvalidArgumentException('Patrimônio é obrigatório para itens permanentes.');
        }

        if ($data['tipo'] === 'saída') {
            $currentStock = $this->getStock($itemId);
            if ($quantidade > $currentStock) {
                throw new \InvalidArgumentException('Quantidade superior ao saldo disponível.');
            }
        }

        // capture previous stock to detect threshold crossings
        $oldStock = $this->getStock($itemId);

        $this->database->pdo()->beginTransaction();
        try {
            $movementId = $this->movements->record(
                $itemId,
                $data['tipo'],
                $data['data_item'],
                $quantidade,
                $data['assinatura_data'] ?? '',
                $data['uso'],
                $movementTipoRegistro,
                $data['condicao_item'] ?? '',
                $data['observacao'] ?? '',
                trim($data['patrimonio'] ?? ''),
                !empty($data['id_funcionario']) ? (int)$data['id_funcionario'] : null,
                $localidadeId
            );

            $delta = $data['tipo'] === 'entrada' ? $quantidade : -$quantidade;
            $this->items->adjustStock($itemId, $delta);
                $this->database->pdo()->commit();
        } catch (\Throwable $exception) {
            if ($this->database->pdo()->inTransaction()) {
                $this->database->pdo()->rollBack();
            }
            throw $exception;
        }

        $stock = $this->getStock($itemId);
        $item = $this->getItemWithThresholds($itemId);

        if ($item !== null) {
            $this->notifyThresholdTransitions($item, $oldStock, $stock);
        }

        return $movementId;
    }

    private function normalizeTipoItem(string $tipoItem): string
    {
        $normalized = strtolower(trim($tipoItem));
        return $normalized === 'permanente' ? 'permanente' : 'temporario';
    }

    private function isItemPermanent(string $tipoItem): bool
    {
        return $this->normalizeTipoItem($tipoItem) === 'permanente';
    }

    public function estimateDaysUntilMinimum(int $itemId): ?float
    {
        $item = $this->getItemWithThresholds($itemId);
        if ($item === null) {
            return null;
        }

        $stock = $this->getStock($itemId);
        $minimum = (int)$item['quantidade_minima'];
        if ($stock <= $minimum) {
            return 0.0;
        }

        $firstConsumptionDate = $this->movements->getFirstConsumptionDate($itemId);
        if ($firstConsumptionDate === null) {
            return null;
        }

        $windowDays = $this->calculateWorkingDays($firstConsumptionDate, date('Y-m-d'));
        if ($windowDays <= 0) {
            $windowDays = 1;
        }

        $consumption = $this->movements->totalConsumptionSinceDate($itemId, $firstConsumptionDate);
        if ($consumption <= 0) {
            return null;
        }

        $dailyAverage = $consumption / $windowDays;
        if ($dailyAverage <= 0) {
            return null;
        }

        return max(0.0, ($stock - $minimum) / $dailyAverage);
    }

    private function calculateWorkingDays(string $startDate, string $endDate): int
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        if ($start > $end) {
            return 0;
        }

        $workingDays = 0;
        while ($start <= $end) {
            $weekday = (int)$start->format('N');
            if ($weekday >= 1 && $weekday <= 5) {
                $workingDays++;
            }
            $start->modify('+1 day');
        }

        return $workingDays;
    }

    public function recommendedPurchaseQuantity(int $itemId): ?int
    {
        $item = $this->getItemWithThresholds($itemId);
        if ($item === null) {
            return null;
        }

        $stock = $this->getStock($itemId);
        $desired = (int)$item['quantidade_desejavel'];
        $purchaseBuffer = max(0, (int)($item['quantidade_sobra_compra'] ?? 0));

        if ($stock >= $desired) {
            return 0;
        }

        return max(0, $desired - $stock + $purchaseBuffer);
    }

    /**
     * Envia notificação de estoque baixo para os destinatários do item.
     */
    private function sendLowStockNotification(array $item, int $stock): void
    {
        $emails = $this->getItemNotificationEmails((int)$item['id_item']);
        if (empty($emails)) {
            return;
        }

        $subject = sprintf('Alerta de estoque baixo: %s', $item['item']);
        $message = sprintf(
            "O item '%s' atingiu o nível mínimo ou está abaixo dele.\n\nQuantidade atual: %d\nQuantidade mínima: %d\nQuantidade desejável: %d\n\nEstimativa de dias até o mínimo: %s\n",
            $item['item'],
            $stock,
            (int)$item['quantidade_minima'],
            (int)$item['quantidade_desejavel'],
            $this->formatEstimatedDays($this->estimateDaysUntilMinimum((int)$item['id_item']))
        );

        $message .= sprintf("Recomendação de compra: comprar %d unidade(s) para voltar acima do desejável.\n", $this->recommendedPurchaseQuantity((int)$item['id_item']) ?? 0);

        foreach ($emails as $email) {
            $this->sendEmail($email, $subject, $message);
        }
    }

    private function sendDesiredReachedNotification(array $item, int $stock): void
    {
        $emails = $this->getItemNotificationEmails((int)$item['id_item']);
        if (empty($emails)) {
            return;
        }

        $subject = sprintf('Alerta: estoque atingiu nível desejável: %s', $item['item']);
        $message = sprintf("O item '%s' atingiu o nível desejável.\n\nQuantidade atual: %d\nQuantidade desejável: %d\n", $item['item'], $stock, (int)$item['quantidade_desejavel']);

        foreach ($emails as $email) {
            $this->sendEmail($email, $subject, $message);
        }
    }

    private function sendOutOfStockNotification(array $item, int $stock): void
    {
        $emails = $this->getItemNotificationEmails((int)$item['id_item']);
        if (empty($emails)) {
            return;
        }

        $subject = sprintf('Alerta crítico: item esgotado: %s', $item['item']);
        $message = sprintf("O item '%s' está esgotado.\n\nQuantidade atual: %d\nPor favor, providencie reposição imediata.\n", $item['item'], $stock);

        foreach ($emails as $email) {
            $this->sendEmail($email, $subject, $message);
        }
    }

    private function notifyThresholdTransitions(array $item, int $previousStock, int $currentStock): void
    {
        $desired = (int)($item['quantidade_desejavel'] ?? 0);
        $minimum = (int)($item['quantidade_minima'] ?? 0);

        // Out of stock: crossed to <= 0
        if ($previousStock > 0 && $currentStock <= 0) {
            $this->sendOutOfStockNotification($item, $currentStock);
        }

        // Crossed minimum downward
        if ($previousStock > $minimum && $currentStock <= $minimum) {
            $this->sendLowStockNotification($item, $currentStock);
        }

        // Crossed desired downward
        if ($previousStock > $desired && $currentStock <= $desired) {
            $this->sendDesiredReachedNotification($item, $currentStock);
        }
    }

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        $smtpHost = getenv('SMTP_HOST') ?: 'mail.olimpia.sp.gov.br';
        $smtpPort = (int)(getenv('SMTP_PORT') ?: 465);
        $smtpUser = getenv('SMTP_USER') ?: 'alertas@olimpia.sp.gov.br';
        $smtpPass = getenv('SMTP_PASS') ?: 'Mudar1402*';
        $smtpSecure = strtolower((string)(getenv('SMTP_SECURE') ?: ''));
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'alertas@olimpia.sp.gov.br';
        // First, send a monitor copy (usually MailHog) over plain TCP so
        // development environments always get a copy even if the primary
        // SMTP server is unreachable or requires SSL.
        $monitorHost = getenv('SMTP_MONITOR_HOST');
        if ($monitorHost !== false && $monitorHost !== '') {
            $monitorPort = (int)(getenv('SMTP_MONITOR_PORT') ?: 1025);
            $monitorFromEmail = getenv('SMTP_MONITOR_FROM_EMAIL') ?: $fromEmail;
            // Force plain TCP for monitor deliveries to MailHog
            try {
                $this->sendSmtpMessage($monitorHost, $monitorPort, $monitorFromEmail, $to, $subject, $body, '', '', '');
            } catch (\Throwable $e) {
                error_log('Monitor delivery failed: ' . $e->getMessage());
            }
        }

        // Then attempt the primary delivery (may use SSL/TLS)
        $sent = $this->sendSmtpMessage($smtpHost, $smtpPort, $fromEmail, $to, $subject, $body, $smtpUser, $smtpPass, $smtpSecure);

        return $sent;
    }

    private function sendSmtpMessage(string $smtpHost, int $smtpPort, string $fromEmail, string $to, string $subject, string $body, string $smtpUser, string $smtpPass, string $smtpSecure): bool
    {
        $hostname = gethostname() ?: 'localhost';
        $timeout = 10;
        $transport = $smtpSecure === 'ssl' ? 'ssl' : 'tcp';
        $connection = @stream_socket_client("{$transport}://{$smtpHost}:{$smtpPort}", $errno, $errstr, $timeout);

        // If SSL transport fails (often when attempting SSL to a plain TCP port),
        // fallback to plain TCP once.
        if ($connection === false && $transport === 'ssl') {
            error_log("SSL connect to {$smtpHost}:{$smtpPort} failed: {$errstr} ({$errno}), falling back to tcp");
            $transport = 'tcp';
            $connection = @stream_socket_client("{$transport}://{$smtpHost}:{$smtpPort}", $errno, $errstr, $timeout);
        }

        if ($connection === false) {
            error_log("Could not connect to SMTP {$smtpHost}:{$smtpPort} - {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($connection, $timeout);
        $this->smtpRead($connection);
        $this->smtpWrite($connection, "EHLO {$hostname}");

        if ($smtpSecure === 'tls') {
            $this->smtpWrite($connection, 'STARTTLS');
            if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($connection);
                return false;
            }
            $this->smtpWrite($connection, "EHLO {$hostname}");
        }

        if ($smtpUser !== '' && $smtpPass !== '') {
            $this->smtpWrite($connection, 'AUTH LOGIN');
            $this->smtpWrite($connection, base64_encode($smtpUser));
            $this->smtpWrite($connection, base64_encode($smtpPass));
        }

        $this->smtpWrite($connection, "MAIL FROM:<{$fromEmail}>");
        $this->smtpWrite($connection, "RCPT TO:<{$to}>");
        $this->smtpWrite($connection, "DATA");

        $headers = "From: {$fromEmail}\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";

        $body = str_replace("\n.", "\n..", $body);
        $message = $headers . $body . "\r\n.";
        fwrite($connection, $message . "\r\n");
        $this->smtpRead($connection);
        $this->smtpWrite($connection, "QUIT");
        fclose($connection);

        return true;
    }

    private function smtpWrite($connection, string $command): void
    {
        fwrite($connection, $command . "\r\n");
        $this->smtpRead($connection);
    }

    private function smtpRead($connection): void
    {
        while (($line = fgets($connection)) !== false) {
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
    }

    private function formatEstimatedDays(?float $days): string
    {
        if ($days === null) {
            return 'não há consumo registrado suficiente para estimativa';
        }

        if ($days === 0.0) {
            return 'já atingido';
        }

        return sprintf('%.1f dias', $days);
    }
}
