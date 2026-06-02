<?php

class ApiClient
{
    private const DEFAULT_API_BASE_URL = '';
    private const DEFAULT_APP_TOKEN = '';
    private const DEFAULT_USER_TOKEN = '';

    private string $baseUrl;
    private string $appToken;
    private string $userToken;
    private ?string $sessionToken = null;

    public function __construct(?string $baseUrl = null, ?string $appToken = null, ?string $userToken = null)
    {
        $glpiUrl = $this->getEnv('GLPI_URL', $baseUrl ?? self::DEFAULT_API_BASE_URL);
        $this->baseUrl = $this->normalizeBaseUrl($glpiUrl);
        $this->appToken = $this->getEnv('GLPI_APP_TOKEN', $appToken ?? self::DEFAULT_APP_TOKEN);
        $this->userToken = $this->getEnv('GLPI_USER_TOKEN', $userToken ?? self::DEFAULT_USER_TOKEN);

        if ($this->baseUrl === '') {
            throw new \RuntimeException('GLPI_URL não está definido em .env');
        }
        if ($this->appToken === '') {
            throw new \RuntimeException('GLPI_APP_TOKEN não está definido em .env');
        }
        if ($this->userToken === '') {
            throw new \RuntimeException('GLPI_USER_TOKEN não está definido em .env');
        }
    }

    private function getEnv(string $key, string $default): string
    {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }

    private function initSession(): string
    {
        $url = $this->baseUrl . '/initSession';
        $headers = [
            'App-Token: ' . $this->appToken,
            'Content-Type: application/x-www-form-urlencoded',
        ];
        $body = http_build_query(['user_token' => $this->userToken]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Falha na inicialização da sessão GLPI: ' . $error);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null || !isset($decoded['session_token'])) {
            throw new RuntimeException('Falha ao obter session_token do GLPI: ' . $response);
        }

        $this->sessionToken = $decoded['session_token'];
        return $this->sessionToken;
    }

    private function getSessionToken(): string
    {
        if ($this->sessionToken === null) {
            return $this->initSession();
        }

        return $this->sessionToken;
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        if (str_ends_with($baseUrl, '/apirest.php')) {
            return $baseUrl;
        }

        if (str_contains($baseUrl, 'apirest.php')) {
            return preg_replace('#/apirest\.php.*$#', '/apirest.php', $baseUrl);
        }

        return $baseUrl . '/apirest.php';
    }

    public function request(string $path, array $queryParams = [], string $method = 'GET', array $body = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $headers = [
            'Accept: application/json',
            'App-Token: ' . $this->appToken,
            'Session-Token: ' . $this->getSessionToken(),
        ];

        if (!empty($body)) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Falha na requisição GLPI: ' . $error);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Resposta inválida do GLPI: ' . json_last_error_msg());
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($decoded) ? json_encode($decoded) : $response;
            throw new RuntimeException(sprintf('Erro GLPI (%d): %s', $statusCode, $message));
        }

        return $decoded;
    }

    public function fetchLocations(): array
    {
        return $this->request('Location');
    }
}
