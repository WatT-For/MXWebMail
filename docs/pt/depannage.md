# Resolução de problemas / FAQ

*Traduzido do francês - a versão francesa é a que faz fé.*

## Instalação

**Todos os URL redirecionam para `/setup/`**
Normal: o bloqueio `app/storage/INSTALLED` está ausente. Conclua a
instalação, ou crie o ficheiro manualmente depois de ter posto `config.php`.

**O assistente mostra "config/ não gravável"**
Aviso não bloqueante: copie o conteúdo mostrado para
`app/config/config.php` à mão, depois relance a instalação. Verifique
também que `app/storage/` é gravável (bloqueante).

**O teste de ligação MariaDB falha**
Verifique host/porta/credenciais, e que o utilizador tem direitos para criar
a base (`CREATE DATABASE`). O charset deve ser `utf8mb4`.

## Ligação / login

**Nenhum utilizador consegue ligar-se após a instalação**
Com `restrict_domains = true` (predefinição), é preciso **pelo menos um
domínio nomeado** na tabela (o wildcard `*` é ignorado para esta
verificação). O instalador tenta detetá-lo; senão, crie-o em *Domínios*.

**Login recusado sem razão (conta bloqueada)**
- Demasiadas tentativas: aguarde `login_lock_seconds` (predefinição 300 s),
  ou purgue `app/storage/cache/login_attempts.json`.
- Os plugins `black-list`/`white-list` podem recusar o login (404/403 ou
  erro genérico segundo `auth_error`).
- Um utilizador `disabled` é rejeitado mesmo com uma sessão válida.

**O login admin não funciona**
- Conta local (`admin_user`): verifique `admin_password_hash` e o TOTP
  eventual.
- Fallback IMAP: o utilizador deve ter `is_admin = 1`.
- `admin_ip_allowlist` pode bloquear o seu IP.

## Servidores de correio

**Impossível ligar ao servidor IMAP/SMTP**
Use o botão **Testar a ligação** em *Domínios* (resultado por protocolo).
Causas frequentes: porta/cifra incoerente (993/ssl vs 143/tls), certificado
auto-assinado (ver `ssl.verify_certificate`/`ssl.allow_self_signed`), ou
firewall.

**Certificado auto-assinado recusado**
`ssl.verify_certificate = false` ou `ssl.allow_self_signed = true` (a usar
apenas com pleno conhecimento de causa).

**O domínio não é detetado automaticamente**
`autodetect.enabled` está desativado por defeito. Ative-o, ou use o botão
**Detetar** da página *Domínios*.

**"Assinalar como spam" não filtra os mails seguintes**
O botão move a mensagem para a pasta Correio indesejado (ou põe o keyword
IMAP `$Junk` se o servidor não tiver pasta Junk) - não ensina nada ao
servidor. Os filtros Sieve só se aplicam na entrega: o modelo *Anti-spam*
do assistente (Definições > Filtros) classifica os mails marcados
`X-Spam-Flag: YES` pelo MTA. A aprendizagem automática (spam/ham) exige
configuração do lado do servidor - Dovecot IMAPSieve (script report-spam/ham
disparado pela cópia para/de Junk, que o MOVE da app dispara) ou
rspamd/sa-learn.

## Segurança / HTTPS

**O site permanece em HTTP atrás de um proxy inverso**
Informe `trusted_proxies` (IP do proxy) para que `X-Forwarded-Proto` seja
honrado, depois ative `force_https` / `hsts`.

**O rate-limit ou a allowlist não veem o IP real**
Mesma causa: sem `trusted_proxies`, `REMOTE_ADDR` é usado tal qual.

**O favicon não se mostra**
O favicon carregado deve ter ≤ 512 KB (ico/png/svg/jpg/webp); senão, fica o
ícone predefinido.

## Dados / registos

**Perda de `app.key`**
Todas as credenciais armazenadas tornam-se indecifráveis: os utilizadores
devem voltar a ligar-se. Faça sempre cópia de `app.key`.

**Onde estão os registos?**
`app/storage/logs/app.log` (aplicativo), `mail.log` (protocolos
IMAP/SMTP/Sieve, se `logs.enabled`), e o registo de auth (`auth_logging`).
Visíveis no admin (*Registos*). Integração fail2ban:
[fail2ban.md](fail2ban.md).

**Passar para MariaDB esvaziou os dados**
Comportamento esperado: a mudança de `db_dsn` recomeça sobre um esquema
virgem (os ficheiros de contactos por utilizador são conservados). Não há
ferramenta de migração de dados.

## Atualizações

**"Falha: Não encontrado no GitHub … repo privado ou recurso ausente"**
O repositório oficial está bloqueado (`GitHubClient::REPO` =
`WatT-For/MXWebMail`) e é **público**: nenhum token é requerido. O GitHub
devolve 404 - não 403 - para um recurso ausente ou um acesso insuficiente;
a mensagem indica o endpoint culpado (`releases/latest`, `commits/…` ou um
caminho `raw` do catálogo de plugins). Um 404 persistente significa em geral
um recurso ausente (nenhuma release publicada, `update.plugins_ref`
desconhecido). O caso "repo privado" só diz respeito a um fork configurado
via `update.repo` + PAT em `config.php`.

**HTTP 404 em `…/releases/download/…` (catálogo de plugins ou download de atualização)**
No repo oficial público, `browser_download_url` funciona. O caso só faz
sentido para um fork privado via `update.repo`: `browser_download_url`
devolve lá 404 mesmo com token válido - corrigido a partir da 0.36.1, os
assets são então descarregados via o endpoint API `releases/assets/<id>`
quando um PAT está configurado em `config.php`.

**O site responde 503 "update" / página de manutenção durante uma atualização**
Normal: a flag `app/storage/update/applying` corta a API de utilizador
durante a cópia de ficheiros (nunca mistura de código antigo/novo). É
retirada no fim; se ficar após uma falha, expira sozinha ao fim de 15 min -
ou elimine `app/storage/update/applying` manualmente.

**"Migrações pendentes" após uma atualização**
A finalização (`/api/admin/update/finalize`) não executou: botão
*Finalizar* em *Sobre*, ou em CLI `php app/bin/post-update.php`. Sem ação,
as migrações aplicam-se na mesma no pedido seguinte (migração preguiçosa),
mas é preferível controlá-las.

**Voltar atrás após uma atualização (rollback)**
Botão *Restaurar* em *Sobre*: restaura os ficheiros de
`app/storage/update/backup-<ts>` (os últimos 3 backups são conservados),
elimina os ficheiros criados e recopia as bases SQLite guardadas. Sob
**MariaDB**, as migrações não são reversíveis: restaure primeiro a base a
partir do seu `mysqldump`, depois relance o rollback (é proposta a
confirmação forçada). Para recomeçar do zero: reinstale o pacote de origem
e restaure `app/storage/` (incluindo `app.key` e `db/`).

**Banner "esquema da base mais recente que o código"**
`meta.schema_ahead`: o código instalado é mais antigo que o esquema
(rollback de ficheiros sem restauração da base, ou pacote mais antigo
implantado). Restaure a base ou reaplique a versão correspondente; as
migrações permanecem suspensas enquanto existir a diferença.

## Diversos

**"Reencaminhar tal qual" (bounce): o destinatário vê o mail como spam?**
Possível: o reencaminhamento quebra SPF/DMARC no final (o servidor de envio
já não corresponde ao remetente original) - comportamento normal dos
bounce, os cabeçalhos `Resent-*` documentam a retransmissão. Para conservar
a autenticação, prefira o reencaminhamento clássico.

**Sob Nginx, as pastas privadas são acessíveis?**
Não, se o `root` apontar para `public/` e o bloco `location ~ /\.` +
`^/(app|dist)` estiver presente. Nunca aponte o `root` para a raiz do
projeto.

**Erro 403 "cross_site" num pedido legítimo**
A proteção `Sec-Fetch-*` rejeitou o pedido. Se integrar uma API externa
legítima, adicione uma regra em `secfetch_allow`.

**Depurar um erro interno (500)**
Ative temporariamente `debug = true` (nunca em produção) para ver a
mensagem, depois consulte `app.log`.
