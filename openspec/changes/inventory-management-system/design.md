## Contexto

A organização precisa de um sistema centralizado de gestão de estoque baseado em Docker para substituir o controle manual de estoque. Atualmente, os dados de estoque estão fragmentados e carecem de trilhas de auditoria. O sistema deve suportar o controle de estoque local com um registro claro de movimentações e uma hierarquia de localização simples. Os principais interessados ​​incluem a equipe do armazém (para operações diárias), os gerentes (para relatórios) e o departamento de compras (para recomendações de compras).

## Objetivos / Não Objetivos

**Objetivos:**
- Fornecer rastreamento de estoque centralizado e em tempo real com trilhas de auditoria completas
- Habilitar notificações automáticas por e-mail quando o estoque atingir os níveis mínimos
- Gerar relatórios de consumo e recomendações de compra
- Prever o esgotamento do estoque com base nos padrões de consumo
- Suportar a conteinerização com Docker para implantação
- Implementar as melhores práticas de segurança, incluindo requisitos de autenticação, autorização e assinatura

**Não Objetivos:**
- Integração com PDV/vendas (não é necessária nesta fase)
- Suporte a múltiplos armazéns/múltiplos locais além da hierarquia de localização local
- Leitura de código de barras/RFID (pode ser adicionada posteriormente)
- Aplicativo móvel (interface web como principal)
- Integração com outros sistemas ERP

## Decisões

### Decisão 1: Pilha de Tecnologia
**Escolha**: Backend em Python (FastAPI), banco de dados PostgreSQL, frontend em React, conteinerização com Docker

**Justificativa**:
- O FastAPI oferece excelente suporte assíncrono para tarefas em segundo plano (notificações, previsões)
- O PostgreSQL oferece forte conformidade com ACID para dados críticos Transações de estoque
- React permite interfaces de usuário responsivas para a equipe do armazém
- Docker possibilita a implantação consistente em diferentes ambientes

**Alternativas consideradas**:
- Node.js/Express: Boa escolha, mas o Python possui bibliotecas de aprendizado de máquina melhores para previsão
- MySQL: Adequado, mas o suporte a JSON do PostgreSQL é valioso para trilhas de auditoria
- Vue.js: Igualmente válido, optamos pelo React devido à maior comunidade e ecossistema

### Decisão 2: Arquitetura de Trilha de Auditoria
**Escolha**: Tabela de log de auditoria imutável com armazenamento de payload JSON, separada das tabelas de entidades

**Justificativa**:
- Fornece histórico completo sem modificar registros históricos
- Payloads JSON permitem armazenar o contexto completo de cada movimentação
- Atende aos requisitos de conformidade e forense
- Separa as preocupações com modificação de dados das preocupações com auditoria

**Alternativas consideradas**:
- Event Sourcing: Poderoso, mas adiciona complexidade desnecessária para esta fase
- Auditorias baseadas em gatilhos: Frágeis e mais difíceis de manter

### Decisão 3: Sistema de Notificação
**Escolha**: Fila de tarefas assíncronas (Celery) com verificações agendadas e backend de e-mail

**Justificativa**:
- Desacopla as notificações do ciclo de requisição-resposta
- Lida com processamento em lote e limitação de taxa de forma natural
- Facilita a adição de notificações por SMS/Slack posteriormente
- Celery é uma solução comprovada para tarefas em segundo plano em Python

**Alternativas consideradas**:
- Sondagem em thread em segundo plano: Menos confiável e mais difícil de escalar
- WebSockets para tempo real: Exagerado para os requisitos de notificação

### Decisão 4: Abordagem de previsão
**Escolha**: Análise de séries temporais usando suavização exponencial com ajuste sazonal

**Justificativa**:
- Mais simples do que modelos de aprendizado de máquina completos, mas lida com padrões sazonais
- Suficiente para o caso de uso de previsão de estoque
- Pode ser computado incrementalmente sem infraestrutura de aprendizado de máquina
- Fácil de ajustar e explicar às partes interessadas

**Alternativas consideradas**:
- Modelos ARIMA: Exagerados, mais difíceis de implementar e explicar
- Regressão linear simples: Ignora a sazonalidade Padrões

### Decisão 5: Hierarquia de Localização Local
**Opção**: Utilizar um modelo de localização local com importação opcional de hierarquia baseada em planilha

**Justificativa**:
- Mantém o sistema independente de APIs externas
- Garante a disponibilidade mesmo sem acesso à rede
- Simplifica a implantação e a manutenção
- Permite o controle local de locais e hierarquias de departamentos

**Alternativas Consideradas**:
- Integração com API externa: adiciona dependência e risco de falha
- Entrada manual de texto para localização: menos estruturada e mais difícil de gerar relatórios

### Decisão 6: Validação e Assinatura de Dados
**Opção**: Exigência de assinatura baseada em função (senha/PIN para saídas) com modo estrito configurável

**Justificativa**:
- Implementação simples para requisitos de segurança
- Pode ser estendida para tokens biométricos/de hardware posteriormente
- O registro de auditoria captura quem aprovou cada movimentação
- A abordagem baseada em função permite diferentes níveis de rigor por usuário

**Alternativas Consideradas**:
- Exigir assinatura sempre: Muito rigoroso para operações rotineiras
- Não exigir assinatura: Inadequado para conformidade

## Riscos/Compromissos

**Risco 1: Disponibilidade de Dados Locais** → Mitigação: Manter os dados de localização e estoque no banco de dados, permitir atualização manual da hierarquia local

**Risco 2: Precisão da Previsão com Consumo Esporádico** → Mitigação: O sistema sinaliza previsões de baixa confiança e recomenda revisão manual para itens com consumo volátil

**Risco 3: Crescimento do Armazenamento de Trilhas de Auditoria** → Mitigação: Implementar estratégia de arquivamento (mover registros antigos para uma tabela de arquivo após 2 anos), particionar por data

**Risco 4: Entregabilidade de E-mails** → Mitigação: Rastrear o status de entrega, tentar reenviar e-mails com falha