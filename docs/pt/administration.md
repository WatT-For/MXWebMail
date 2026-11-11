# Administração

*Traduzido do francês - a versão francesa é a que faz fé.*

O painel de administração é acessível em `/{admin_path}` (predefinição
`/admin`). É uma administração **global** (multi-domínios); não há papéis
finos.

## 1. Autenticação admin

Dois modos, conforme a configuração:

1. **Conta local** (`admin_user` + `admin_password_hash` em `config.php`):
   login/palavra-passe, eventualmente TOTP (`admin_totp_secret`). O TOTP é
   provisionado e aplicado pelo plugin `two-factor-auth` (sub-separador
   "Autenticação de dois fatores" de *Segurança*, com código QR) - plugin
   desativado = não exigido no login.
2. **Fallback IMAP** (se não houver conta local): um utilizador autenticado
   via IMAP com o campo `is_admin = 1` abre o admin. Esse direito é
   revalidado a cada pedido (uma conta rebaixada/bloqueada/eliminada é
   rejeitada imediatamente).

O rate-limit de ligação aplica-se também ao admin, assim como
`admin_ip_allowlist` se informado. Um admin IMAP não pode rebaixar-se,
bloquear-se nem eliminar-se a si próprio.

## 2. Secções do painel

O painel compõe-se de separadores (hash `#/<página>[/<sub-separador>]`).

### Geral

`app_name`, `admin_path`, `language` (lista de idiomas instalados +
`auto`), `session_lifetime`, `remember_days` (duração dos tokens "Manter
sessão"), `maintenance`, `update.channel`. Gere também o **favicon** (upload
≤ 512 KB: ico/png/svg/jpg/webp, conteúdo validado). O modo **manutenção**
mostra uma página dedicada (503) em `/`: os utilizadores ligados são
basculados para ela no seu próximo pedido API (sessão conservada) e ela
recarrega sozinha no fim; o admin (`/{admin_path}` + `/api/admin/*`)
permanece plenamente acessível.

### Base de dados

Driver SQLite ou MariaDB/MySQL, host/porta/nome/utilizador/palavra-passe.
Um teste (`/api/admin/test-db`) é obrigatório antes da escrita: o servidor
valida a ligação e cria a base se necessário. Ver
[base-de-donnees.md](base-de-donnees.md).

O cartão **Cópia de segurança** descarrega um arquivo zip (`config.php`,
`app.key`, bases SQLite, `storage/branding`) - **sensível** (credenciais +
chave de cifragem), a conservar em lugar seguro. Uma base MariaDB não está
coberta: `mysqldump` do lado do servidor.

### Funcionalidades

Sub-separadores *Funcionalidades* e *Predefinições*:

- proxy de imagens + allowlist, tamanhos dos anexos;
- `contacts_enable` / `contacts_suggestions_limit`;
- `allow_additional_accounts` / `allow_additional_identities`,
  `popup_identity`, `dangerous_actions`;
- todas as definições `user_defaults.*` (predefinições de utilizadores).

### Desempenho

Sub-separadores *HTTP* e *Cache de servidor*:

- `http.*`: compressão gzip (ativação, nível, tamanho mínimo), `http.etag`,
  TTL dos assets tema/plugin, do favicon e do proxy de imagens;
- `cache.*`: cache de ficheiro aplicativa (ativação, TTL da deteção de
  domínio e da verificação de atualização, intervalo de purga).

Detalhe das chaves em [configuration.md](configuration.md) (secções *HTTP* e
*Cache aplicativa*).

### Segurança

Sub-separadores *Conta*, *Autenticação de dois fatores* (se o plugin
`two-factor-auth` estiver ativado), *Acesso*, *Cabeçalhos*, *TLS*:

- conta admin local (`admin_user`) e **mudança da palavra-passe** admin -
  preencher os dois campos depois *Guardar*: a palavra-passe só é
  modificada se estiverem preenchidos e idênticos;
- **TOTP** admin (sub-separador do plugin `two-factor-auth`): geração do
  segredo, código QR a ler, código de confirmação obrigatório antes da
  ativação (o segredo nunca é mostrado após a gravação);
- `admin_ip_allowlist` (botão "adicionar o meu IP"), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies`;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow`;
- secção `ssl.*` completa (certificados de saída).

### Registos

Sub-separadores *Visualizador* e *Parâmetros*:

- visualizador dos registos `app.log`, `mail.log` e do registo de auth (200
  últimas linhas), com botão "Esvaziar" para truncar o ficheiro
  selecionado;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Domínios

Configuração dos servidores de correio por domínio - ver
[domaines.md](domaines.md). O separador *Parâmetros* agrupa a resolução
(`default_domain`, `restrict_domains`, `autodetect.*`). Para os domínios
"apenas conta secundária + fornecedor OAuth" (Gmail, Outlook, Yahoo), o
admin só autoriza o fornecedor (opção `oauth` do modal de domínio): as
credenciais de aplicação OAuth2 são registadas por cada utilizador
(*Definições > Aplicações OAuth2*).

### Utilizadores

Lista + pesquisa de utilizadores, com botão de atualização (ícone) para
recarregar a lista. Por utilizador (modal, separadores *Perfil*,
*Definições*, *Contas/Identidades*, *Sessões*):

- `display_name`, `is_admin`, `disabled`;
- definições de utilizador efetivas (edição + reset - o reset restaura os
  `user_defaults` mas **preserva** as chaves internas `plugin.*` e
  `user_folders`, ex. segredo 2FA, proveniência das pastas);
- contas IMAP secundárias (eliminação, salvo a conta predefinida);
- identidades (criação/edição/eliminação, identidade predefinida);
- separador **Sessões**: tokens "Manter sessão" listados e revogáveis;
  **Forçar encerramento de sessão** revoga os tokens e mata as sessões
  ativas (session_epoch incrementado, rejeição no pedido seguinte).

A eliminação de um utilizador é em cascata e elimina também a sua base de
contactos (`storage/db/users/<id>.sqlite`).

### Extensões (plugins)

Cada plugin é listado sob o seu **nome mostrado** (campo `label` do
manifesto, traduzido - o nome técnico `name` fica mostrado ao lado em
`code`).

Ativação/desativação de cada plugin + modal de **configuração** gerado a
partir de `config.schema.json` (tipos `string`, `text`, `integer`, `number`,
`password`, `bool`, `enum`, `color`, `list`). A coluna **Acesso
utilizadores** restringe um plugin ativado à superfície admin (página
`/{admin_path}`, `/api/admin/*`, sessões admin): torna-se inerte para os
utilizadores - ver [plugins.md](plugins.md). Desmarcada por defeito (a
autorizar explicitamente); está ausente para os plugins que só declaram
hooks `admin.*` (nenhuma superfície de utilizador).

**Instalação / atualização a partir do GitHub**: os plugins são lidos
diretamente no repositório bloqueado (`GitHubClient::REPO`, o mesmo que o
updater núcleo) no ref `update.plugins_ref` - `'release'` = tag da última
release por defeito, `'main'` em dev (um push de `plugin.json` publica a
atualização, nenhuma release requerida). Cada ficheiro descarregado via
`raw.githubusercontent.com` é verificado por sha1 de blob git.
O catálogo é carregado à abertura do separador (cache partilhada com o check
núcleo) e é atualizável via o botão **atualizar** no canto superior direito;
as entradas do catálogo não instaladas são listadas no fim da tabela de
plugins:

- botão **Atualizar** nos plugins instalados quando uma versão mais recente
  está publicada (a pasta antiga é copiada para
  `storage/update/plugins/backup-<name>-<ts>` - uma única conservada,
  rollback automático em caso de falha);
- botão **Instalar** nas entradas do catálogo não presentes - um plugin
  recém-instalado está **desativado** por defeito;
- botão **Desinstalar**: eliminação **definitiva** da pasta e da
  configuração (linha `plugins` purgada), sem cópia de segurança;
- `min_app`/`max_app` do `plugin.json` remoto: uma entrada incompatível com
  `APP_VERSION` é marcada *incompatível* e a sua instalação é rejeitada.

### Migração webmail (RainLoop / SnappyMail)

Se o plugin `webmail-migration` estiver ativado, uma página **Migração
webmail** aparece no menu lateral (antes de *Sobre*): migra os dados de uma
pasta `data/_data_/_default_/` de uma instalação **RainLoop ou
SnappyMail**, indicada por **caminho de servidor** ou **upload ZIP**
(extração em `storage/tmp`, purgada via o botão dedicado ou após 2 h).

- **Analisar**: inventário sem escrita (domínios `*.ini`/`*.json`, contas
  encontradas, volumes contactos/identidades/chaves/definições por
  utilizador);
- **Lançar a importação**: importação idempotente - domínios em falta,
  contactos (livros por conta + global + DSN PDO declarada em
  `application.ini`, frequências e tipos de emails conservados, contactos
  eliminados excluídos), identidades (predefinida = `Id` vazia), definições
  de utilizador conhecidas (mensagens por página, atualização,
  auto-gravação de contactos, pastas sistema, tema se existir) e chaves PGP
  armored. Re-executável sem duplicados.

Só as contas que correspondem a um **utilizador existente** são importadas;
as outras são listadas no relatório. As palavras-passe das contas
adicionais não são recuperáveis (cifradas pelo webmail de origem) - a
recriar manualmente. Os filtros Sieve vivem no servidor de correio: nada a
importar.

Do lado do utilizador, o separador *Definições > Contactos* oferece também
uma **importação** `.vcf`/`.csv`/`.sqlite` (livro `AddressBook.sqlite`
RainLoop/SnappyMail - num livro multi-utilizadores só os contactos da conta
são retomados) e uma **exportação** `.vcf`, cobrindo qualquer outro livro,
incluindo uma exportação manual SnappyMail.

### Temas

- tema **predefinido** (escreve `user_defaults.theme`);
- **autorização** por tema (`allowed`): um utilizador só pode escolher um
  tema autorizado (o predefinido fica sempre selecionável);
- modal de **configuração** do tema (mesmos tipos + `color`);
- visualização da cadeia de herança (`extends`).

Ver [themes.md](themes.md).

### Sobre

Versão (`APP_VERSION`), PHP/SAPI/limites, driver e tamanho da base,
caminhos, extensões carregadas. Um link para o site oficial é
mostrado na navbar. O cartão de cabeçalho integra também as
**atualizações** via GitHub Releases (verificação, download, aplicação,
restauro):

- *Verificar* > *Descarregar* > *Aplicar* > finalização automática das
  migrações; estados conservados em `storage/update/state.json`;
- na extração, `files.sha256` (manifesto por ficheiro do pacote) é
  verificado, os pré-requisitos do pacote (`APP_REQ_PHP`/`APP_REQ_FROM` no
  seu `App.php`: versão PHP, versão de origem mínima) são controlados, e os
  ficheiros modificados localmente são assinalados antes da aplicação;
- durante a cópia, a API de utilizador responde 503 (flag
  `storage/update/applying`, página de manutenção do lado web - expira após
  15 min em caso de falha);
- cada ficheiro sobrescrito é copiado em `storage/update/backup-<ts>` com
  `manifest.json` (versão, `schema_version`, listas); sob SQLite a base
  (`webmail.sqlite` + `db/users/*.sqlite`) é copiada após checkpoint WAL -
  sob MariaDB, a cópia externa (`mysqldump`) fica a cargo do admin (aviso
  antes da aplicação);
- *Restaurar* (rollback): restaura os ficheiros da última cópia, elimina os
  ficheiros criados e recopia as bases SQLite; sob MariaDB, rejeição se o
  esquema avançou (restaure o dump depois confirme o forçamento);
- o banner "esquema mais recente que o código" assinala um rollback de
  ficheiros sem restauração da base;
- ação *Reinicializar* para abandonar um pacote descarregado/extraído.

> O repositório oficial está bloqueado em `GitHubClient::REPO` e é **público**:
> a verificação funciona sem configuração nem token (desativada apenas enquanto
> a constante não estiver informada).
> `update.channel` continua editável aqui; `update.repo` ajusta-se
> manualmente em `config.php`.

## 3. Escrita de `config.php`

O admin escreve `config.php` **por secção**, atomicamente (ficheiro
temporário + `rename`, `chmod 0600`), preservando as chaves não fornecidas.
Se `app/config/` não for gravável, a interface indica-o (`saved_readonly`)
e as alterações são mostradas mas não persistidas.

## 4. Testar a ligação

Em *Domínios*, **Testar a ligação** valida o protocolo do separador ativo do
modal (IMAP, SMTP ou Sieve - os outros separadores testam IMAP), e
**Detetar** preenche os campos sem gravar. A deteção (`DomainDetector`)
funciona independentemente de `autodetect.enabled`. Gravar manualmente um
domínio apaga o marcador "auto-detetado" (`options.detected`).
