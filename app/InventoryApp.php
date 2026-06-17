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

    public function getMovements(int $itemId = null): array
    {
        return $this->movements->history($itemId);
    }

    public function getMovementSummary(): array
    {
        return $this->movements->summary();
    }

    public function addItem(string $name, int $quantity = 0): int
    {
        $itemId = $this->items->getOrCreateByName($name);

        if ($quantity > 0) {
            $this->movements->record(
                $itemId,
                'entrada',
                date('Y-m-d'),
                $quantity,
                '',
                'Consumo',
                '',
                null,
                null
            );
        }

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

    public function updateItemThresholds(int $itemId, int $minQuantity, int $desiredQuantity): bool
    {
        return $this->items->updateThresholds($itemId, $minQuantity, $desiredQuantity);
    }

    /**
     * Retorna o relatório de consumo mensal de cada item.
     */
    public function getMonthlyConsumptionReport(string $yearMonth = null): array
    {
        return $this->movements->monthlyConsumptionReport($yearMonth);
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

        $movementId = $this->movements->record(
            $itemId,
            $data['tipo'],
            $data['data_item'],
            (int)$data['quantidade'],
            $data['assinatura_data'] ?? '',
            $data['uso'],
            $data['observacao'] ?? '',
            !empty($data['id_funcionario']) ? (int)$data['id_funcionario'] : null,
            $localidadeId
        );

        $stock = $this->getStock($itemId);
        $item = $this->getItemWithThresholds($itemId);

        if ($item !== null && $stock <= (int)$item['quantidade_minima']) {
            $this->sendLowStockNotification($item, $stock);
        }

        return $movementId;
    }

    public function estimateDaysUntilMinimum(int $itemId, int $windowDays = 30): ?float
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

        $consumption = $this->movements->totalConsumptionLastDays($itemId, $windowDays);
        if ($consumption <= 0) {
            return null;
        }

        $dailyAverage = $consumption / $windowDays;
        return max(0.0, ($stock - $minimum) / $dailyAverage);
    }

    public function recommendedPurchaseQuantity(int $itemId): ?int
    {
        $item = $this->getItemWithThresholds($itemId);
        if ($item === null) {
            return null;
        }

        $stock = $this->getStock($itemId);
        $target = max((int)$item['quantidade_minima'], (int)$item['quantidade_desejavel']);

        if ($stock >= $target) {
            return 0;
        }

        $additionalBuffer = (int)ceil($this->movements->totalConsumptionLastDays($itemId, 30) / 30.0);
        return max(0, $target - $stock + $additionalBuffer);
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

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        $smtpHost = getenv('SMTP_HOST') ?: 'mailhog';
        $smtpPort = (int)(getenv('SMTP_PORT') ?: 1025);
        $smtpUser = getenv('SMTP_USER') ?: '';
        $smtpPass = getenv('SMTP_PASS') ?: '';
        $smtpSecure = strtolower((string)(getenv('SMTP_SECURE') ?: ''));
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'alerta@estoque.local';
        $sent = $this->sendSmtpMessage($smtpHost, $smtpPort, $fromEmail, $to, $subject, $body, $smtpUser, $smtpPass, $smtpSecure);

        $monitorHost = getenv('SMTP_MONITOR_HOST');
        if ($monitorHost !== false && $monitorHost !== '') {
            $monitorPort = (int)(getenv('SMTP_MONITOR_PORT') ?: 1025);
            $monitorFromEmail = getenv('SMTP_MONITOR_FROM_EMAIL') ?: $fromEmail;
            $this->sendSmtpMessage($monitorHost, $monitorPort, $monitorFromEmail, $to, $subject, $body, '', '', strtolower((string)(getenv('SMTP_MONITOR_SECURE') ?: '')));
        }

        return $sent;
    }

    private function sendSmtpMessage(string $smtpHost, int $smtpPort, string $fromEmail, string $to, string $subject, string $body, string $smtpUser, string $smtpPass, string $smtpSecure): bool
    {
        $hostname = gethostname() ?: 'localhost';
        $timeout = 10;

        $transport = $smtpSecure === 'ssl' ? 'ssl' : 'tcp';
        $connection = stream_socket_client("{$transport}://{$smtpHost}:{$smtpPort}", $errno, $errstr, $timeout);
        if ($connection === false) {
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
