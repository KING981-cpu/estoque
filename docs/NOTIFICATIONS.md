# Notificações de Estoque

Este documento descreve o comportamento de notificações por e-mail relacionadas aos níveis de estoque.

Regras:

- Enviar e-mail quando o estoque cair para o nível desejável (<= `quantidade_desejavel`).
- Enviar e-mail quando o estoque cair para o nível mínimo (<= `quantidade_minima`).
- Enviar e-mail quando o item ficar esgotado (<= 0).

Comportamento de envio:

- Notificações são enviadas apenas quando o estoque cruza o limiar na direção decrescente (ex.: de 6 para 4, cruzando `quantidade_desejavel = 5`).
- Para cada item, as notificações são enviadas para todos os e-mails cadastrados em `item_notificacao_email`.
- A entrega principal é efetuada via SMTP usando as configurações em `.env` (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`).
- Em ambiente de desenvolvimento, o sistema também pode enviar uma cópia para MailHog usando `SMTP_MONITOR_HOST` e `SMTP_MONITOR_PORT`.
- O carregamento de `.env` respeita variáveis já definidas no ambiente, permitindo que `docker compose` ou o shell sobrescrevam valores do arquivo.

Ambiente de desenvolvimento com MailHog:

1. Levante os containers com Docker Compose:
   ```bash
   cd /opt/projetos/estoque
   docker compose up -d --build
   ```
2. Verifique o MailHog local em `http://localhost:8025`.
3. Configure um e-mail para o item desejado na interface de itens.
4. Registre uma saída que reduza o estoque abaixo de `quantidade_desejavel`, `quantidade_minima` ou 0.
5. Verifique as mensagens no MailHog.

Arquivos alterados:

- `app/InventoryApp.php`: detecta transições de estoque e envia notificações através de novos métodos:
  - `notifyThresholdTransitions()` — compara estoques anterior e atual e chama os métodos abaixo quando necessário.
  - `sendDesiredReachedNotification()` — envia alerta quando o nível desejável for atingido.
  - `sendLowStockNotification()` — já existente; envia alerta quando atingir o nível mínimo.
  - `sendOutOfStockNotification()` — envia alerta crítico quando o item se esgota.

Testes sugeridos:

1. Configure `SMTP_MONITOR_HOST=mailhog` e `SMTP_MONITOR_PORT=1025` no `.env` e execute MailHog (ou verifique o container MailHog). Registre um e-mail de teste no item e gere movimentos que atravessem os limiares.
2. Enviar movimentos reduzindo estoque de forma a cruzar `quantidade_desejavel`, `quantidade_minima` e 0, verificando mensagens no MailHog.

Observações:

- Para evitar spams, o envio é acionado apenas na transição descendente do limite.
- Se quiser diferentes regras (por exemplo, notificações quando o estoque sobe acima do desejável), eu posso adicionar.
