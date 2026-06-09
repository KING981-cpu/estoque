<?php

class Database
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = $this->connect();
        $this->initSchema();
    }

    private function env(string $name, string $default): string
    {
        $value = getenv($name);
        return $value !== false ? $value : $default;
    }

    private function connect(): \PDO
    {
        $host = $this->env('DB_HOST', 'db');
        $port = $this->env('DB_PORT', '3306');
        $database = $this->env('DB_NAME', 'estoque');
        $user = $this->env('DB_USER', 'estoque');
        $password = $this->env('DB_PASS', 'estoque_pass');

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $lastException = null;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                $pdo = new \PDO($dsn, $user, $password, $options);
                $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $database));
                $pdo->exec(sprintf('USE `%s`', $database));
                return $pdo;
            } catch (\PDOException $exception) {
                $lastException = $exception;
                sleep(2);
            }
        }

        throw new \RuntimeException('Não foi possível conectar ao banco de dados MySQL: ' . $lastException?->getMessage());
    }

    private function initSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS funcionario (
            id_funcionario INT PRIMARY KEY AUTO_INCREMENT,
            nome VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS localidade (
    id_localidade INT PRIMARY KEY AUTO_INCREMENT,
    secretaria ENUM('GABINETE DO PREFEITO DECRETO N.º 9.778/2025','FUNDO SOCIAL DE SOLIDARIEDADE','SECRETARIA MUNICIPAL DA CASA CIVIL DECRETO N.º 9.748/2025','SECRETARIA MUNICIPAL DE GOVERNO E RELAÇÕES INSTITUCIONAIS DECRETO N.º 9.967/2026','SECRETARIA MUNICIPAL DE ASSISTÊNCIA E DESENVOLVIMENTO SOCIAL DECRETO N.º 9.781/2025','SECRETARIA MUNICIPAL DE ESPORTE, LAZER E JUVENTUDE DECRETO N.º 9.766/2025','SECRETARIA MUNICIPAL DE TURISMO DECRETO N.º 9.823/2025','SECRETARIA MUNICIPAL DE CULTURA E DEFESA DO FOLCLORE DECRETO N.º 9.822/2025','SEC. MUN. INOVAÇÃO, TECNOLOGIA E DESENVOLVIMENTO ECON. SUSTENTÁVEL DECRETO N.º 9.857/2026','SECRETARIA MUNICIPAL DE SAÚDE DECRETO N.º 9.844/2025','SECRETARIA MUNICIPAL DE EDUCAÇÃO DECRETO N.º 9.838/2025','SECRETARIA MUNICIPAL DE PLANEJAMENTO E FINANÇAS DECRETO N.º 9.835/2025','SECRETARIA MUNICIPAL DE GESTÃO E CIDADE INTELIGENTE DECRETO N.º 9.843/2025','SECRETARIA MUNICIPAL DE OBRAS, ENGENHARIA E INFRAESTRUTURA DECRETO N.º 9.966/2026','SECRETARIA MUNICIPAL DE ZELADORIA E MEIO AMBIENTE DECRETO N.º 9.839/2025','SECRETARIA MUNICIPAL DE SEGURANÇA, TRÂNSITO E MOBILIDADE URBANA DECRETO N.º 9.836/2025') NOT NULL,
    divisao ENUM('Diretor de Divisão Administrativa','Diretor da Divisão Administrativa, Convênios e Captação','Diretor da Divisão de Projetos e Ações Sociais','Diretor da Divisão de Planejamento Estratégico','Diretor da Divisão de Análise de Dados e Indicadores','Diretor da Divisão Conecta+Olímpia','Diretor da Divisão de Governança, Processos e Projetos Estratégicos','Diretor da Divisão de Normas e Atos Oficiais','Diretor da Divisão de Assuntos Jurídicos','Diretor da Divisão de Apoio ao Terceiro Setor e Organizações Comunitárias','Diretor da Divisão de Captação Estratégica','Diretor da Divisão de Parcerias Público- Privadas e Concessões','Diretor da Divisão de Ouvidoria, Análise e Resolução de Demandas','Diretor da Divisão de Comunicação, Imprensa, Cerimonial e Eventos','Diretor da Divisão de Proteção Social Básica','Diretor da Divisão de Proteção Social Especial','Diretor da Divisão de Interesse Habitacional, Regularização Fundiária e Melhorias Habitacionais','Diretor da Divisão de Gestão Administrativa e Financiamento do SUAS','Diretor da Divisão de Vigilância Socioassistencial, Cadastro Único e Gestão do Conecta+Olímpia','Diretor da Divisão de Captação de Recursos e Fomento ao Terceiro Setor','Diretor da Divisão Administrativa','Diretor da Divisão de Esporte','Diretor da Divisão de Lazer e Juventude','Diretor da Divisão Administrativa, Planejamento, Desenvolvimento e Infraestrutura Turística','Diretor da Divisão de Eventos, Parcerias, Desenvolvimento e Suporte aos Atrativos Turísticos','Diretor da Divisão de Patrimônio Histórico Cultural','Diretor da Divisão de Festivais e Eventos','Diretor da Divisão de Programas e Projetos Culturais','Diretor da Divisão de Agricultura, Inspeção de Produtos de Origem Animal e Patrulha Agrícola Mecanizada','Diretor da Divisão de Inovação Tecnológica, Estudos Econômicos e Pesquisas','Diretor da Divisão de Desenvolvimento Econômico Sustentável','Diretor da Divisão de Atendimento e Capacitação ao Cidadão','Diretor da Divisão de Planejamento, Avaliação e Desenvolvimento','Diretor da Divisão de Educação Permanente e Humanização','Diretor da Divisão de Vigilância em Saúde','Diretor da Divisão de Média e Alta Complexidade','Diretor da Divisão de Atenção Primária','Diretor da Divisão de Assistência Farmacêutica e Insumos Estratégicos','Diretor da Divisão de Unidade de Pronto Atendimento UPA 24h','Diretor da Divisão Administrativa, Controle e Execução Orçamentária','Diretor da Divisão de Planejamento','Diretor da Divisão de Oficinas Pedagógicas','Diretor da Divisão de Supervisão Escolar','Diretor da Divisão de Implantação Tecnológica e Controle Educacional','Diretor da Divisão de Planejamento e Execução Orçamentária','Diretor da Divisão de Cadastro e Gestão da Dívida Ativa','Diretor da Divisão de Fiscalização Tributária','Diretor da Divisão de Fiscalização de Posturas','Diretor da Divisão de Gestão de Recursos Humanos','Diretor da Divisão de Gestão e Planejamento de Compras','Diretor da Divisão de Gestão Contratual','Diretor da Divisão de Gestão Logística','Diretor da Divisão de Gestão Operacional','Diretor da Divisão de Tecnologia da Informação','Diretor da Divisão de Arquivo Público Municipal','Diretor da Divisão de Planejamento Urbano','Diretor da Divisão de Manutenção','Diretor da Divisão de Projetos e Obras','Diretor da Divisão de Licenciamento e Regularização','Diretor da Divisão de Convênios e Operações de Recursos Externos','Diretor da Divisão de Serviços de Zeladoria','Diretor da Divisão de Meio Ambiente','Diretor da Divisão de Proteção e Bem-Estar Animal','Diretor da Divisão de Trânsito e Mobilidade Urbana','Diretor da Divisão de Coordenação da Defesa Civil','Diretor da Divisão Administrativa da Controladoria Geral do Município','Diretor da Divisão de Controle Interno e Transparência') NOT NULL,
    setor ENUM('Chefe do Setor de Acolhimento e Atendimento Social','Chefe do Setor de Estratégia e Gestão de Prazos','Chefe do Setor de Metas e Resultados','Chefe do Setor de Monitoramento e Avaliação de Indicadores','Chefe do Setor Desenvolvimento','Chefe do Setor de Implantação e Melhorias','Chefe do Setor de Escritório de Processos e Projetos','Chefe do Setor de Estudos Estratégicos e Boas Práticas da Gestão','Chefe do Setor de Normas e Expediente','Chefe do Setor de Execuções Fiscais e Assuntos Tributários','Chefe do Setor de Atos Administrativos, Consultoria e Pareceres','Chefe do Setor de Acompanhamento de Licitações, Contratos e Convênios','Chefe do Setor de Processos Cíveis, Administrativos e Trabalhistas','Chefe do Setor Administrativo','Chefe do Setor de Relações Federativas e Investimentos','Chefe do Setor de Coordenação e Gestão de PPP e Concessões','Chefe do Setor de Atendimento, Encaminhamento e Resolução','Chefe do Setor de Cerimonial e Solenidades','Chefe do Setor de Imprensa e Marketing','Chefe do Setor de CRAS I','Chefe do Setor de CRAS II','Chefe do Setor de CRAS III','Chefe do Setor de Centro de Convivência do Idoso','Chefe do Setor do Programa Criança Feliz','Chefe do Setor de CREAS','Chefe do Setor Centro Dia do Idoso','Chefe do Setor da Casa de Passagem','Chefe do Setor de Acolhimento a Criança e Adolescente','Chefe do Setor do Programa Vida Longa','Chefe do Setor de Planejamento e Programas Habitacionais','Chefe do Setor de Compras e Controle de Estoque','Chefe do Setor de Prestação de Contas e Controle Orçamentário','Chefe do Setor de Gerenciamento, Controle e Concessão de Benefícios Eventuais','Chefe do Setor de Monitoramento e Avaliação','Chefe do Setor de Gestão Executiva dos Conselhos','Chefe do Setor de Gestão e Fomento do Terceiro Setor','Chefe do Setor de Apoio Administrativo','Chefe do Setor de Projetos e Eventos','Chefe do Setor de Manutenção de Prédios e Suprimentos','Chefe do Setor de Coordenação Esportiva','Chefe do Setor de Coordenação do Complexo Esportivo','Chefe do Setor de Coordenação, Lazer e Juventude','Chefe do Setor Administrativo e Infraestrutura Turística','Chefe do Setor de Eventos','Chefe do Setor de Suporte aos Atrativos Turísticos','Chefe do Setor de Apoio Administrativo e Operacional','Chefe do Setor de Preservação do Patrimônio Material e Imaterial e Coordenação dos Equipamentos Culturais','Chefe do Setor de Planejamento do Calendário de Eventos','Chefe do Setor de Fomento e Apoio aos Projetos','Chefe do Setor de Apoio aos Produtos de Origem Animal (SIMPOA)','Chefe do Setor de Patrulha Agrícola Mecanizada','Chefe do Setor de Apoio às Feiras','Chefe do Setor de Apoio à Integração das Secretarias, Banco de Dados Integrado (BDI) e Geoprocessamento','Chefe do Setor de Inovação Tecnológica','Chefe do Setor da Casa do Empreendedor','Chefe do Setor de Apoio a Indústria e Parceria com o Sistema “S” Sebrae/Senai/Sesi/Sest/Senat','Chefe do Setor de Apoio ao Comércio e Parceria com o Sistema “S” Senac/Sebrae/Sest/Senat/Sesc','Chefe do Setor de Estudos, Boletins e Indicadores','Chefe do Setor de Atendimento Poupatempo','Chefe do Setor de Coordenação do Procon','Chefe do Setor de Capacitação, Formação e Aperfeiçoamento','Chefe do Setor de Frotas e Remoção de Pacientes','Chefe do Setor de Apoio a Licitação','Chefe do Setor de Apoio à Informatização','Chefe do Setor de Apoio a Elaboração de Documentos Técnicos para Processos Licitatórios','Chefe do Setor de Fiscalização de Contratos e Convênios','Chefe do Setor de Regulação dos Serviços','Chefe do Setor de Núcleo de Informação, Processamento de Dados e Faturamento','Chefe do Setor de Ação Judicial','Chefe do Setor de Gestão de Contratos e Convênios','Chefe do Setor de Auditoria','Chefe do Setor de Parcerias com Instituições de Ensino, Estágios e Residência Médica','Chefe do Setor de Ouvidoria SUS','Chefe do Setor de Vigilância Epidemiológica','Chefe do Setor de Vigilância Sanitária','Chefe do Setor de Controle de Endemias e Animais Peçonhentos','Chefe do Setor de Ambulatório de Referência de Especialidades – ARE','Chefe do Setor de Diagnóstico Complementar','Chefe do Setor de Saúde Mental','Chefe do Setor de Reabilitação (EMAD/EMAP) Fisioterapia','Chefe do Setor de Distrito Sanitário I','Chefe do Setor de Distrito Sanitário II','Chefe do Setor Mãe Olimpiense','Chefe do Setor de Saúde Bucal','Chefe do Setor de Suporte Administrativo Farmacêutico','Chefe do Setor de Apoio à Logística','Chefe do Setor de Suporte Administrativo UPA/SAMU','Chefe do Setor de Protocolo','Chefe do Setor de Apoio Administrativo e Monitoramento de Contratos','Chefe do Setor de Demanda Escolar','Chefe do Setor de Transporte Escolar','Chefe do Setor de Alimentação Escolar','Chefe do Setor de Ensino Infantil','Chefe do Setor de Ensino Fundamental','Chefe do Setor de Educação Especial e Inclusiva','Chefe do Setor de Supervisão de Ensino I','Chefe do Setor de Supervisão de Ensino II','Chefe do Setor de Supervisão de Ensino III','Chefe do Setor de Supervisão de Ensino IV','Chefe do Setor de Supervisão de Ensino V','Chefe do Setor de Supervisão de Ensino VI','Chefe do Setor de Implementação de Soluções Tecnológicas e Gestão do Conecta+Olímpia','Chefe do Setor de Formação e Capacitação Digital','Chefe do Setor de Planejamento e Orçamento','Chefe do Setor de Execução Orçamentária','Chefe do Setor de Tesouraria','Chefe do Setor de Custos','Chefe do Setor de Cadastro Imobiliário','Chefe do Setor de Cadastro Mobiliário','Chefe do Setor de Patrimônio Imobiliário','Chefe do Setor de Dívida Ativa','Chefe do Setor de Tributos','Chefe do Setor de Posturas','Chefe do Setor de Folha de Pagamento','Chefe do Setor de Treinamento e Desenvolvimento Humano','Chefe do Setor de Serviço Especializado em Segurança e Medicina do Trabalho','Chefe do Setor de Gestão de Vínculos, Atos e Sistemas de Escrituração Digital','Chefe do Setor de Planejamento e Formalização das Contratações','Chefe do Setor de Compras','Chefe do Setor de Licitações','Chefe do Setor de Gestão de Contratos','Chefe do Setor de Fiscalização de Contratos','Chefe do Setor de Contratos','Chefe do Setor de Almoxarifado e Distribuição','Chefe do Setor de Patrimônio Mobiliário','Chefe do Setor de Controle e Manutenção de Frotas','Chefe do Setor de Apoio e Atendimento','Chefe do Setor de Infraestrutura de Rede e Servidores','Chefe do Setor de Fábrica de Software e Sistemas','Chefe do Setor de Suporte e Manutenção','Chefe do Setor de Inteligência de Negócios, Integração e Desenvolvimento','Chefe do Setor de Acervo Histórico','Chefe do Setor de Gestão Documental','Chefe do Setor de Gestão de Concessão dos Serviços de Água e Esgoto','Chefe do Setor de Planejamento, Desenvolvimento Urbano e Gestão de Contrapartidas','Chefe do Setor de Manutenção de Vias Urbanas e Rurais','Chefe do Setor de Manutenção de Edificações Públicas','Chefe do Setor de Manutenção de Iluminação Pública','Chefe do Setor de Projetos','Chefe do Setor de Fiscalização de Obras Públicas','Chefe do Setor de Gerenciamento de Contratos de Obras Públicas','Chefe do Setor de Drenagem Urbana','Chefe do Setor de Licenciamento de Obras','Chefe do Setor de Fiscalização e Regularização','Chefe do Setor de Topografia e Georreferenciamento','Chefe do Setor de Regularização Fundiária de Interesse Específico','Chefe do Setor de Operacionalização de Convênios','Chefe do Setor de Prestação de Contas e Monitoramento','Chefe do Setor de Conservação Urbana','Chefe do Setor de Conservação de Áreas Verdes','Chefe do Setor de Arborização e Paisagismo','Chefe do Setor de Manutenção, Operação e Instalações Ambientais','Chefe do Setor de Fiscalização e Licenciamento','Chefe do Setor de Educação Ambiental','Chefe do Setor de Acolhimento e Proteção Animal','Chefe do Setor de Sinalização Viária','Chefe do Setor de Transporte Público e Privado','Chefe do Setor de Infração, Controle e Fiscalização da Área Azul','Chefe do Setor de Apoio ao Sistema de Controle Interno','Chefe do Setor de Auditoria Interna e Análise de Contas','Chefe do Setor de Auditoria das Parcerias com o Terceiro Setor','Sem setor disponível') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

        $this->ensureLocalidadeSchema();

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item (
            id_item INT PRIMARY KEY AUTO_INCREMENT,
            item VARCHAR(50) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS movimentacao (
            id_movimentacao INT PRIMARY KEY AUTO_INCREMENT,
            tipo ENUM(\'entrada\', \'saída\') NOT NULL,
            data_item DATE NOT NULL,
            quantidade INT NOT NULL,
            assinatura LONGTEXT NOT NULL,
            uso ENUM(\'Consumo\', \'Empréstimo\') NOT NULL,
            `observação` VARCHAR(200) NOT NULL,
            id_funcionario INT,
            id_localidade INT,
            id_item INT,
            FOREIGN KEY (id_funcionario) REFERENCES funcionario(id_funcionario),
            FOREIGN KEY (id_localidade) REFERENCES localidade(id_localidade),
            FOREIGN KEY (id_item) REFERENCES item(id_item)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    private function ensureLocalidadeSchema(): void
    {
        $columns = $this->getTableColumns('localidade');
        if (empty($columns) || (isset($columns['secretaria']) && isset($columns['divisao']) && isset($columns['setor']) && !isset($columns['local']))) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            if (!isset($columns['secretaria'])) {
                $this->pdo->exec('ALTER TABLE localidade ADD COLUMN secretaria VARCHAR(255) NOT NULL DEFAULT ""');
            }
            if (!isset($columns['divisao'])) {
                $this->pdo->exec('ALTER TABLE localidade ADD COLUMN divisao VARCHAR(255) NOT NULL DEFAULT ""');
            }
            if (!isset($columns['setor'])) {
                $this->pdo->exec('ALTER TABLE localidade ADD COLUMN setor VARCHAR(255) NOT NULL DEFAULT ""');
            }

            if (isset($columns['local'])) {
                $statement = $this->pdo->query('SELECT id_localidade, local FROM localidade');
                foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    [$secretaria, $divisao, $setor] = $this->parseLocalidadePath($row['local']);
                    $update = $this->pdo->prepare('UPDATE localidade SET secretaria = ?, divisao = ?, setor = ? WHERE id_localidade = ?');
                    $update->execute([$secretaria, $divisao, $setor, $row['id_localidade']]);
                }
            }

            $this->pdo->exec(<<<'SQL'
ALTER TABLE localidade
    MODIFY COLUMN secretaria ENUM('GABINETE DO PREFEITO DECRETO N.º 9.778/2025','FUNDO SOCIAL DE SOLIDARIEDADE','SECRETARIA MUNICIPAL DA CASA CIVIL DECRETO N.º 9.748/2025','SECRETARIA MUNICIPAL DE GOVERNO E RELAÇÕES INSTITUCIONAIS DECRETO N.º 9.967/2026','SECRETARIA MUNICIPAL DE ASSISTÊNCIA E DESENVOLVIMENTO SOCIAL DECRETO N.º 9.781/2025','SECRETARIA MUNICIPAL DE ESPORTE, LAZER E JUVENTUDE DECRETO N.º 9.766/2025','SECRETARIA MUNICIPAL DE TURISMO DECRETO N.º 9.823/2025','SECRETARIA MUNICIPAL DE CULTURA E DEFESA DO FOLCLORE DECRETO N.º 9.822/2025','SEC. MUN. INOVAÇÃO, TECNOLOGIA E DESENVOLVIMENTO ECON. SUSTENTÁVEL DECRETO N.º 9.857/2026','SECRETARIA MUNICIPAL DE SAÚDE DECRETO N.º 9.844/2025','SECRETARIA MUNICIPAL DE EDUCAÇÃO DECRETO N.º 9.838/2025','SECRETARIA MUNICIPAL DE PLANEJAMENTO E FINANÇAS DECRETO N.º 9.835/2025','SECRETARIA MUNICIPAL DE GESTÃO E CIDADE INTELIGENTE DECRETO N.º 9.843/2025','SECRETARIA MUNICIPAL DE OBRAS, ENGENHARIA E INFRAESTRUTURA DECRETO N.º 9.966/2026','SECRETARIA MUNICIPAL DE ZELADORIA E MEIO AMBIENTE DECRETO N.º 9.839/2025','SECRETARIA MUNICIPAL DE SEGURANÇA, TRÂNSITO E MOBILIDADE URBANA DECRETO N.º 9.836/2025') NOT NULL,
    MODIFY COLUMN divisao ENUM('Diretor de Divisão Administrativa','Diretor da Divisão Administrativa, Convênios e Captação','Diretor da Divisão de Projetos e Ações Sociais','Diretor da Divisão de Planejamento Estratégico','Diretor da Divisão de Análise de Dados e Indicadores','Diretor da Divisão Conecta+Olímpia','Diretor da Divisão de Governança, Processos e Projetos Estratégicos','Diretor da Divisão de Normas e Atos Oficiais','Diretor da Divisão de Assuntos Jurídicos','Diretor da Divisão de Apoio ao Terceiro Setor e Organizações Comunitárias','Diretor da Divisão de Captação Estratégica','Diretor da Divisão de Parcerias Público- Privadas e Concessões','Diretor da Divisão de Ouvidoria, Análise e Resolução de Demandas','Diretor da Divisão de Comunicação, Imprensa, Cerimonial e Eventos','Diretor da Divisão de Proteção Social Básica','Diretor da Divisão de Proteção Social Especial','Diretor da Divisão de Interesse Habitacional, Regularização Fundiária e Melhorias Habitacionais','Diretor da Divisão de Gestão Administrativa e Financiamento do SUAS','Diretor da Divisão de Vigilância Socioassistencial, Cadastro Único e Gestão do Conecta+Olímpia','Diretor da Divisão de Captação de Recursos e Fomento ao Terceiro Setor','Diretor da Divisão Administrativa','Diretor da Divisão de Esporte','Diretor da Divisão de Lazer e Juventude','Diretor da Divisão Administrativa, Planejamento, Desenvolvimento e Infraestrutura Turística','Diretor da Divisão de Eventos, Parcerias, Desenvolvimento e Suporte aos Atrativos Turísticos','Diretor da Divisão de Patrimônio Histórico Cultural','Diretor da Divisão de Festivais e Eventos','Diretor da Divisão de Programas e Projetos Culturais','Diretor da Divisão de Agricultura, Inspeção de Produtos de Origem Animal e Patrulha Agrícola Mecanizada','Diretor da Divisão de Inovação Tecnológica, Estudos Econômicos e Pesquisas','Diretor da Divisão de Desenvolvimento Econômico Sustentável','Diretor da Divisão de Atendimento e Capacitação ao Cidadão','Diretor da Divisão de Planejamento, Avaliação e Desenvolvimento','Diretor da Divisão de Educação Permanente e Humanização','Diretor da Divisão de Vigilância em Saúde','Diretor da Divisão de Média e Alta Complexidade','Diretor da Divisão de Atenção Primária','Diretor da Divisão de Assistência Farmacêutica e Insumos Estratégicos','Diretor da Divisão de Unidade de Pronto Atendimento UPA 24h','Diretor da Divisão Administrativa, Controle e Execução Orçamentária','Diretor da Divisão de Planejamento','Diretor da Divisão de Oficinas Pedagógicas','Diretor da Divisão de Supervisão Escolar','Diretor da Divisão de Implantação Tecnológica e Controle Educacional','Diretor da Divisão de Planejamento e Execução Orçamentária','Diretor da Divisão de Cadastro e Gestão da Dívida Ativa','Diretor da Divisão de Fiscalização Tributária','Diretor da Divisão de Fiscalização de Posturas','Diretor da Divisão de Gestão de Recursos Humanos','Diretor da Divisão de Gestão e Planejamento de Compras','Diretor da Divisão de Gestão Contratual','Diretor da Divisão de Gestão Logística','Diretor da Divisão de Gestão Operacional','Diretor da Divisão de Tecnologia da Informação','Diretor da Divisão de Arquivo Público Municipal','Diretor da Divisão de Planejamento Urbano','Diretor da Divisão de Manutenção','Diretor da Divisão de Projetos e Obras','Diretor da Divisão de Licenciamento e Regularização','Diretor da Divisão de Convênios e Operações de Recursos Externos','Diretor da Divisão de Serviços de Zeladoria','Diretor da Divisão de Meio Ambiente','Diretor da Divisão de Proteção e Bem-Estar Animal','Diretor da Divisão de Trânsito e Mobilidade Urbana','Diretor da Divisão de Coordenação da Defesa Civil','Diretor da Divisão Administrativa da Controladoria Geral do Município','Diretor da Divisão de Controle Interno e Transparência') NOT NULL,
    MODIFY COLUMN setor ENUM('Chefe do Setor de Acolhimento e Atendimento Social','Chefe do Setor de Estratégia e Gestão de Prazos','Chefe do Setor de Metas e Resultados','Chefe do Setor de Monitoramento e Avaliação de Indicadores','Chefe do Setor Desenvolvimento','Chefe do Setor de Implantação e Melhorias','Chefe do Setor de Escritório de Processos e Projetos','Chefe do Setor de Estudos Estratégicos e Boas Práticas da Gestão','Chefe do Setor de Normas e Expediente','Chefe do Setor de Execuções Fiscais e Assuntos Tributários','Chefe do Setor de Atos Administrativos, Consultoria e Pareceres','Chefe do Setor de Acompanhamento de Licitações, Contratos e Convênios','Chefe do Setor de Processos Cíveis, Administrativos e Trabalhistas','Chefe do Setor Administrativo','Chefe do Setor de Relações Federativas e Investimentos','Chefe do Setor de Coordenação e Gestão de PPP e Concessões','Chefe do Setor de Atendimento, Encaminhamento e Resolução','Chefe do Setor de Cerimonial e Solenidades','Chefe do Setor de Imprensa e Marketing','Chefe do Setor de CRAS I','Chefe do Setor de CRAS II','Chefe do Setor de CRAS III','Chefe do Setor de Centro de Convivência do Idoso','Chefe do Setor do Programa Criança Feliz','Chefe do Setor de CREAS','Chefe do Setor Centro Dia do Idoso','Chefe do Setor da Casa de Passagem','Chefe do Setor de Acolhimento a Criança e Adolescente','Chefe do Setor do Programa Vida Longa','Chefe do Setor de Planejamento e Programas Habitacionais','Chefe do Setor de Compras e Controle de Estoque','Chefe do Setor de Prestação de Contas e Controle Orçamentário','Chefe do Setor de Gerenciamento, Controle e Concessão de Benefícios Eventuais','Chefe do Setor de Monitoramento e Avaliação','Chefe do Setor de Gestão Executiva dos Conselhos','Chefe do Setor de Gestão e Fomento do Terceiro Setor','Chefe do Setor de Apoio Administrativo','Chefe do Setor de Projetos e Eventos','Chefe do Setor de Manutenção de Prédios e Suprimentos','Chefe do Setor de Coordenação Esportiva','Chefe do Setor de Coordenação do Complexo Esportivo','Chefe do Setor de Coordenação, Lazer e Juventude','Chefe do Setor Administrativo e Infraestrutura Turística','Chefe do Setor de Eventos','Chefe do Setor de Suporte aos Atrativos Turísticos','Chefe do Setor de Apoio Administrativo e Operacional','Chefe do Setor de Preservação do Patrimônio Material e Imaterial e Coordenação dos Equipamentos Culturais','Chefe do Setor de Planejamento do Calendário de Eventos','Chefe do Setor de Fomento e Apoio aos Projetos','Chefe do Setor de Apoio aos Produtos de Origem Animal (SIMPOA)','Chefe do Setor de Patrulha Agrícola Mecanizada','Chefe do Setor de Apoio às Feiras','Chefe do Setor de Apoio à Integração das Secretarias, Banco de Dados Integrado (BDI) e Geoprocessamento','Chefe do Setor de Inovação Tecnológica','Chefe do Setor da Casa do Empreendedor','Chefe do Setor de Apoio a Indústria e Parceria com o Sistema “S” Sebrae/Senai/Sesi/Sest/Senat','Chefe do Setor de Apoio ao Comércio e Parceria com o Sistema “S” Senac/Sebrae/Sest/Senat/Sesc','Chefe do Setor de Estudos, Boletins e Indicadores','Chefe do Setor de Atendimento Poupatempo','Chefe do Setor de Coordenação do Procon','Chefe do Setor de Capacitação, Formação e Aperfeiçoamento','Chefe do Setor de Frotas e Remoção de Pacientes','Chefe do Setor de Apoio a Licitação','Chefe do Setor de Apoio à Informatização','Chefe do Setor de Apoio a Elaboração de Documentos Técnicos para Processos Licitatórios','Chefe do Setor de Fiscalização de Contratos e Convênios','Chefe do Setor de Regulação dos Serviços','Chefe do Setor de Núcleo de Informação, Processamento de Dados e Faturamento','Chefe do Setor de Ação Judicial','Chefe do Setor de Gestão de Contratos e Convênios','Chefe do Setor de Auditoria','Chefe do Setor de Parcerias com Instituições de Ensino, Estágios e Residência Médica','Chefe do Setor de Ouvidoria SUS','Chefe do Setor de Vigilância Epidemiológica','Chefe do Setor de Vigilância Sanitária','Chefe do Setor de Controle de Endemias e Animais Peçonhentos','Chefe do Setor de Ambulatório de Referência de Especialidades – ARE','Chefe do Setor de Diagnóstico Complementar','Chefe do Setor de Saúde Mental','Chefe do Setor de Reabilitação (EMAD/EMAP) Fisioterapia','Chefe do Setor de Distrito Sanitário I','Chefe do Setor de Distrito Sanitário II','Chefe do Setor Mãe Olimpiense','Chefe do Setor de Saúde Bucal','Chefe do Setor de Suporte Administrativo Farmacêutico','Chefe do Setor de Apoio à Logística','Chefe do Setor de Suporte Administrativo UPA/SAMU','Chefe do Setor de Protocolo','Chefe do Setor de Apoio Administrativo e Monitoramento de Contratos','Chefe do Setor de Demanda Escolar','Chefe do Setor de Transporte Escolar','Chefe do Setor de Alimentação Escolar','Chefe do Setor de Ensino Infantil','Chefe do Setor de Ensino Fundamental','Chefe do Setor de Educação Especial e Inclusiva','Chefe do Setor de Supervisão de Ensino I','Chefe do Setor de Supervisão de Ensino II','Chefe do Setor de Supervisão de Ensino III','Chefe do Setor de Supervisão de Ensino IV','Chefe do Setor de Supervisão de Ensino V','Chefe do Setor de Supervisão de Ensino VI','Chefe do Setor de Implementação de Soluções Tecnológicas e Gestão do Conecta+Olímpia','Chefe do Setor de Formação e Capacitação Digital','Chefe do Setor de Planejamento e Orçamento','Chefe do Setor de Execução Orçamentária','Chefe do Setor de Tesouraria','Chefe do Setor de Custos','Chefe do Setor de Cadastro Imobiliário','Chefe do Setor de Cadastro Mobiliário','Chefe do Setor de Patrimônio Imobiliário','Chefe do Setor de Dívida Ativa','Chefe do Setor de Tributos','Chefe do Setor de Posturas','Chefe do Setor de Folha de Pagamento','Chefe do Setor de Treinamento e Desenvolvimento Humano','Chefe do Setor de Serviço Especializado em Segurança e Medicina do Trabalho','Chefe do Setor de Gestão de Vínculos, Atos e Sistemas de Escrituração Digital','Chefe do Setor de Planejamento e Formalização das Contratações','Chefe do Setor de Compras','Chefe do Setor de Licitações','Chefe do Setor de Gestão de Contratos','Chefe do Setor de Fiscalização de Contratos','Chefe do Setor de Contratos','Chefe do Setor de Almoxarifado e Distribuição','Chefe do Setor de Patrimônio Mobiliário','Chefe do Setor de Controle e Manutenção de Frotas','Chefe do Setor de Apoio e Atendimento','Chefe do Setor de Infraestrutura de Rede e Servidores','Chefe do Setor de Fábrica de Software e Sistemas','Chefe do Setor de Suporte e Manutenção','Chefe do Setor de Inteligência de Negócios, Integração e Desenvolvimento','Chefe do Setor de Acervo Histórico','Chefe do Setor de Gestão Documental','Chefe do Setor de Gestão de Concessão dos Serviços de Água e Esgoto','Chefe do Setor de Planejamento, Desenvolvimento Urbano e Gestão de Contrapartidas','Chefe do Setor de Manutenção de Vias Urbanas e Rurais','Chefe do Setor de Manutenção de Edificações Públicas','Chefe do Setor de Manutenção de Iluminação Pública','Chefe do Setor de Projetos','Chefe do Setor de Fiscalização de Obras Públicas','Chefe do Setor de Gerenciamento de Contratos de Obras Públicas','Chefe do Setor de Drenagem Urbana','Chefe do Setor de Licenciamento de Obras','Chefe do Setor de Fiscalização e Regularização','Chefe do Setor de Topografia e Georreferenciamento','Chefe do Setor de Regularização Fundiária de Interesse Específico','Chefe do Setor de Operacionalização de Convênios','Chefe do Setor de Prestação de Contas e Monitoramento','Chefe do Setor de Conservação Urbana','Chefe do Setor de Conservação de Áreas Verdes','Chefe do Setor de Arborização e Paisagismo','Chefe do Setor de Manutenção, Operação e Instalações Ambientais','Chefe do Setor de Fiscalização e Licenciamento','Chefe do Setor de Educação Ambiental','Chefe do Setor de Acolhimento e Proteção Animal','Chefe do Setor de Sinalização Viária','Chefe do Setor de Transporte Público e Privado','Chefe do Setor de Infração, Controle e Fiscalização da Área Azul','Chefe do Setor de Apoio ao Sistema de Controle Interno','Chefe do Setor de Auditoria Interna e Análise de Contas','Chefe do Setor de Auditoria das Parcerias com o Terceiro Setor','Sem setor disponível') NOT NULL
SQL);

            if (isset($columns['local'])) {
                $this->pdo->exec('ALTER TABLE localidade DROP COLUMN local');
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function getTableColumns(string $table): array
    {
        $statement = $this->pdo->prepare('SHOW COLUMNS FROM ' . $table);
        $statement->execute();
        $columns = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {
            $columns[$column['Field']] = $column['Type'];
        }

        return $columns;
    }

    private function parseLocalidadePath(string $localidadePath): array
    {
        $parts = array_filter(array_map('trim', explode('>', $localidadePath)), fn ($part) => $part !== '');
        $parts = array_values($parts);

        while (count($parts) < 3) {
            $parts[] = 'Sem setor disponível';
        }

        return [$parts[0], $parts[1], $parts[2]];
    }
}
