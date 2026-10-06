# Fluxa BPMS

Sistema de gestão de processos (BPMS) em **HTML + CSS + JavaScript + PHP + MySQL**, feito para hospedagem compartilhada (Hostinger). A modelagem usa o **bpmn.io (bpmn-js)** e um motor de execução próprio em PHP.

## Áreas

| Área | Quem usa | O que faz |
|---|---|---|
| **Tarefas** (`tarefas.php`) | Todos | Caixa de entrada em quadro kanban. Você **arrasta** um cartão de "Disponíveis" para "Comigo" para assumir a tarefa e arrasta de volta para devolver ao grupo. Ao abrir a tarefa, preenche o formulário e conclui (ou escolhe uma decisão, como Aprovar ou Reprovar). |
| **Iniciar** (`iniciar.php`) | Todos | Catálogo dos processos publicados e formulário inicial. |
| **Processos** (`processos.php`) | Todos | Processos que você iniciou ou dos quais participou. Mostra o diagrama com o andamento pintado, a linha do tempo, os dados, os anexos e os comentários. |
| **Painel** (`painel.php`) | Admin | Indicadores, volume por mês, gargalos por etapa, carga por pessoa e tarefas atrasadas. |
| **Fluxos** (`fluxos.php` → `modelador.php`) | Admin | Modelador BPMN com painel de propriedades: responsável, prazo, formulário (**construtor de formulários com arrastar e soltar**), botões de decisão, condições nas setas e automações. Tem versionamento: rascunho → publicar. |
| **Config.** (`configuracoes.php`) | Admin | Usuários (perfil e gestor), grupos (**arraste as pessoas para os grupos**), e-mail e anexos. |

## O que o motor executa

- **Eventos:** início (com formulário), fim, fim que encerra tudo (terminate) e temporizador intermediário (esperar X minutos, horas ou dias).
- **Tarefas humanas** (Tarefa, Tarefa humana, Manual, Aguardar confirmação). O responsável pode ser:
  - um usuário específico;
  - um grupo (o primeiro que assumir fica com a tarefa);
  - quem iniciou o processo;
  - o gestor de quem iniciou;
  - quem executou outra etapa.

  Cada tarefa pode ter prazo, prioridade, instruções com `{{variaveis}}`, formulário e botões de decisão.
- **Automações** (Serviço, Envio, Script, Regra de negócio):
  - enviar e-mail;
  - notificar no sistema;
  - definir variáveis (aceita contas simples, como `{{valor}} * 1.1`);
  - chamar uma API externa (webhook) e guardar a resposta.
- **Gateways:**
  - **exclusivo:** segue pela primeira condição verdadeira ou pelo caminho padrão;
  - **paralelo:** abre todos os caminhos e, na junção, espera todos;
  - **inclusivo:** segue por todas as condições verdadeiras.
- **Condições nas setas:** variável + operador (=, ≠, >, <, ≥, ≤, contém, vazio, preenchido), combinadas com E ou OU.
- **Versões:** quem já está em andamento continua na versão em que começou. Erros de automação param a etapa, avisam os administradores e podem ser reexecutados pela tela do processo.

## Instalação na Hostinger

1. Crie um banco MySQL no hPanel (**Bancos de dados › MySQL**) e anote o nome, o usuário e a senha.
2. Envie o conteúdo de `public_html/` para o `public_html` do domínio.
3. Envie `database/` e `cron/` para a pasta **acima** do `public_html` (ex.: `~/domains/seudominio.com.br/`).
4. Copie `config.exemplo.php` para `~/domains/seudominio.com.br/config.php` e preencha os dados do banco e a `url_publica`.
5. Pelo SSH, rode:

   ```bash
   cd ~/domains/seudominio.com.br && /opt/alt/php83/usr/bin/php database/instalacao.php --email=voce@empresa.com --nome="Seu Nome" --senha="SenhaForte123"
   ```

   Use o PHP 8 (o `php` padrão do terminal da Hostinger é o 7.4). Rodar de novo só redefine a senha do administrador.

   **Sem SSH:** importe `database/schema.sql` no phpMyAdmin. Depois crie o administrador com um `INSERT` na tabela `usuarios`, usando um hash gerado por `password_hash()`.
6. **Cron** (opcional, deixa os temporizadores precisos). Em **Avançado › Cron Jobs**, configure a cada 5 minutos:

   ```
   /usr/bin/php /home/SEU-USUARIO/domains/seudominio.com.br/cron/processar.php
   ```

   Sem o cron, os temporizadores andam quando alguém usa o sistema, no máximo uma vez por minuto.
7. **E-mail:** crie uma conta de e-mail no mesmo domínio (ex.: `nao-responda@seudominio.com.br`) e informe-a em **Config. › Geral**. Use o botão "Enviar e-mail de teste" para conferir.

A pasta `storage/` (anexos) é criada automaticamente fora do `public_html`. O `.htaccess` já força HTTPS, bloqueia `app/` e define o CSP.

## Atualizando um banco existente

Ao publicar uma versão nova, aplique os arquivos `database/migracao-*.sql` que ainda não rodaram (em ordem), pelo phpMyAdmin ou com `mysql --default-character-set=utf8mb4`.

## Usuário master

O usuário master (definido em `config.php` com `'usuario_master'`) é o único que exclui administradores e concede ou retira o perfil de administrador.

## Rodando localmente

```bash
php -S localhost:9013 -t public_html
```

A configuração local fica em `config.local.php`, na raiz do projeto, e já vem com `email_simulado => true`: os e-mails vão para `storage/emails.log` em vez de serem enviados.

## Estrutura

```
public_html/
  app/            núcleo PHP (bloqueado para a web)
    motor.php       motor BPMN: tokens, gateways, tarefas, timers
    formularios.php formulários dinâmicos e validação
    acoes.php       automações (e-mail, notificação, variáveis, webhook)
    fluxos.php      rascunho, validação e publicação de versões
  api/            endpoints JSON (CSRF em todas as escritas)
  assets/
    js/modelador.js, construtor-formulario.js, tarefas.js, ...
    vendor/bpmn-js/ bpmn-js 18 (licença bpmn.io; manter o logo no canto)
database/         schema.sql, migracao-*.sql e instalacao.php
cron/             processar.php (temporizadores)
```

## Segurança

- Senhas com `password_hash`, limite de tentativas de login, sessão com cookie `__Host-` em HTTPS, troca de senha derruba as outras sessões.
- CSRF em todas as APIs e formulários; consultas sempre com prepared statements; saída sempre escapada.
- Anexos fora do `public_html`, com nome aleatório e extensão validada, servidos só para quem participa do processo.
- Condições e cálculos avaliados sem `eval`.
