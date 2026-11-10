# Plugins

*Traduzido do francês - a versão francesa é a que faz fé.*

Os plugins são extensões confinadas em `app/plugins/<name>/`, carregadas
quando ativadas (a partir do admin, separador *Extensões*). Registam-se em
**hooks** do núcleo.

> **Sessão só de leitura**: os endpoints GET fecham a sessão PHP cedo
> (`session_write_close`) para libertar o bloqueio. Os hooks chamados a
> partir de um GET (`app.data`, `contacts.provider`, `message.meta`,
> `message.view`, `imap.before-connect`, `sieve.before-connect`…) **não
> devem nunca escrever `$_SESSION`** - essas escritas seriam silenciosamente
> perdidas.

## 0. Distribuição via o repositório GitHub

Os plugins **não estão incluídos** no zip de release `mxwebmail-<ver>.zip`:
são lidos diretamente no repositório a um **ref configurável**
(`update.plugins_ref` - `'release'` = tag da última release por defeito,
`'main'` ou qualquer ref git). O catálogo (`PluginPackageService`,
separador *Extensões* - as entradas não instaladas figuram no fim da
tabela) é construído a partir da API git:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` lista os ficheiros sob
  `app/plugins/` com o seu **sha1 de blob git** e o seu tamanho;
- o `plugin.json` de cada plugin é lido via `raw.githubusercontent.com`
  (fixado no sha do commit) - versão, descrição, `min_app`/`max_app`;
- **Instalar / atualizar**: cada ficheiro do plugin é descarregado via
  `raw`, verificado (tamanho + sha1 de blob do tree), depois `plugin.json`
  validado (nome concordante, versão semver, `main` são, compatibilidade
  `APP_VERSION`) antes de substituir a pasta - um plugin instalado está
  **desativado** por defeito; a pasta antiga vai para
  `storage/update/plugins/backup-<name>-<ts>` (uma única conservada,
  rollback automático);
- **Desinstalar**: eliminação definitiva da pasta + purga da linha
  `plugins` (configuração perdida), sem cópia de segurança.

Publicar uma versão de plugin = bump `version` no seu `plugin.json` e push
no ramo seguido por `update.plugins_ref` - nenhuma release nem asset
requerido.

O repositório está bloqueado em `GitHubClient::REPO` (mesma constante que o
updater núcleo) - nenhum URL arbitrário é aceite. `update.channel` e a
cache de release (`cache.update_ttl`) são partilhados com a verificação de
atualização do núcleo; o ref>commit é cacheado a esse TTL, o tree e os
manifestos são endereçados pelo seu sha (imutáveis). O `check` estático
exige `version` semver em `plugin.json`.

Para dev/testes fora de rede, `update.plugins_local` (caminho absoluto de
uma pasta de plugins em `config.php`) serve de fonte: catálogo e
instalação por cópia de disco, sem verificação remota.

Um plugin ativado pode também **ser reservado à superfície admin** (coluna
*Acesso utilizadores* do separador *Extensões*, flag `user_allowed` na
tabela `plugins`, **desmarcada por defeito**): só é então carregado na
página `/{admin_path}`, nas rotas `/api/admin/*` e nas sessões admin. Noutro
lugar é totalmente inerte (nenhum hook, `/api/plugin/<name>/*` > 404) -
mesmo para os utilizadores `is_admin` não ligados ao painel. Os hooks de
login admin (`admin.post-login`, `admin.totp-required`, `admin.mounts`)
continuam a funcionar. Um plugin cujos hooks declarados no manifesto são
todos hooks `admin.*` não tem nenhuma superfície de utilizador: a coluna
não mostra então switch para ele (`-`).

## 1. Plugins do catálogo oficial

| Plugin | Função | Hooks |
|---|---|---|
| `add-x-originating-ip-header` | Adiciona `X-Originating-IP` (IP do remetente) aos envios. | `message.before-send` |
| `anti-spoofing` | Deteção de usurpação no leitor: Return-Path/Reply-To/Sender incoerentes, display-name imitando uma marca (homóglifos e confundíveis detetados), domínio isco (subdomínio armadilha, typosquat, trans-TLD), SPF/DKIM/DMARC avaliados em alinhamento com o domínio do remetente (veredictos lidos em `Authentication-Results`, `Received-SPF` ou os cabeçalhos SpamAssassin/rspamd) - os subdomínios dos domínios protegidos são legítimos. | `imap.message-headers`, `message.view` |
| `avatars` | Avatar do remetente: ícones de serviços conhecidos, Gravatar, favicon do domínio, iniciais. | `app.data` |
| `black-list` | Lista negra de ligação (wildcards + exceções) - verificada sobre o email **resolvido** (pós-mapping). | `auth.login.mapped` |
| `white-list` | Lista branca de ligação (wildcards + exceções) - verificada sobre o email **resolvido** (pós-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Sincronização de contactos a partir de um livro CardDAV (Nextcloud, Radicale, iCloud…) - **HTTPS + host público exigidos**. | `app.data` |
| `change-password` | Mudança de palavra-passe (drivers poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Etiquetas de cor sobre as mensagens (manuais + regras automáticas). | `app.data`, `message.meta` |
| `ics-viewer` | Pré-visualização dos convites/anexos `.ics` no leitor **+ resposta iTIP** (Aceitar/Talvez/Recusar sobre `METHOD:REQUEST`, enviada por SMTP ao organizador). | `app.data` |
| `imap-contacts-suggestions` | Sugestões de contactos a partir dos remetentes de uma pasta IMAP. | `contacts.provider` |
| `ldap-contacts` | Sugestões de contactos a partir de um diretório LDAP (autocompletar). | `contacts.provider` |
| `ldap-login` | Resolução LDAP: uid/alias > endereço IMAP real antes do login. | `auth.login.mapping` |
| `mailbox-detect` | Deteção das pastas sistema e criação das que faltam. | `app.data` |
| `pwa` | Torna o webmail instalável (manifesto + service worker mínimo, ícones). | `app.head`, `app.routes` |
| `webmail-migration` | Página admin de importação a partir de uma pasta `data` de **RainLoop ou SnappyMail** (caminho de servidor ou ZIP): domínios, livros `AddressBook.sqlite`/PDO, identidades, definições, pastas sistema, chaves PGP - scan dry-run depois importação idempotente, contas desconhecidas ignoradas. | `admin.mounts` |
| `two-factor-auth` | TOTP de dois fatores (Google Authenticator, Aegis…) - contas de utilizador **e** conta admin local (provisioning QR num sub-separador dedicado de Admin > Segurança; exigido no login admin enquanto o plugin estiver ativado). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Banner de anúncio do administrador (página app, opcionalmente página de login - manutenção). Só texto em claro. | `app.head` |
| `attachments-clamav` | Scan ClamAV (`clamd`, `zINSTREAM`) dos anexos carregados e das importações - rejeição em deteção, política `on_error` configurável. | `attachment.upload` |
| `filelink` | Anexos demasiado grandes para o SMTP enviados como **link de download expirável** (`/filelink/<token>` - attachment forçado, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Aprendizagem spam/ham em cada (des)marcação: comando externo (`rspamc`, `sa-learn`) ou cópia IMAP para uma pasta dovecot-antispam. | `message.spam` |
| `wkd-keys` | Descoberta automática das chaves PGP dos destinatários: **WKD** (advanced + direct), colheita dos cabeçalhos **Autocrypt**, recurso `keys.openpgp.org` opcional - importação proposta ao redigir. | `app.data`, `message.view` |
| `seasonal-decorations` | Paisagem monocromática discreta nos banners conforme a data (Ano Novo, Páscoa, 1 de abril, Halloween, Natal): biblioteca de objetos SVG recomposta em camadas `mask-image` via `scenes.json` ({svg, pos, size}), cor contrastada automática - janelas ajustáveis, opcional fora de sessão, sem animação. | `app.head` |
| `link-confirmation` | Modal de confirmação antes de abrir um link do corpo de um mail (anti-phishing): destino real mostrado, sinalização de links suspeitos (texto ≠ URL, IP literal, punycode, credenciais no URL), domínios de confiança e "não perguntar mais para este domínio". | `app.head` |
| `always-cc-bcc` | Destinatários Cc ou Bcc sistemáticos no envio (arquivo de conformidade), por conta ou todas - o Bcc fica invisível. | `message.before-send` |
| `report-abuse` | Botão "Assinalar" (barra de ações): reencaminha os mails marcados como `.eml` anexos para um endereço abuse administrado, marcação spam opcional. | `app.data` |
| `vcf-viewer` | Pré-visualização dos anexos `.vcf` no leitor + importação num clique para o livro (botão integrado no frontend quando `features.vcf_viewer`). | `app.data` |
| `send-rate-limit` | Quota de envio por conta (mensagens/hora, mensagens/dia, destinatários/dia) - rejeição 429 além disso. Só webmail. | `message.before-send` |
| `login-notify` | Mail de alerta à conta numa ligação a partir de um IP desconhecido (modelo Gmail) - depositado não lido na inbox, opt-out por utilizador. | `auth.post-login`, `app.data` |
| `sso-header` | SSO via proxy de autenticação (cabeçalho de utilizador de confiança) - exige IPs de proxy declarados e uma palavra-passe mestra IMAP. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Página admin agregando os relatórios DMARC (rua) de uma caixa IMAP dedicada - parsing XML/gzip, agregados por domínio e IP origem. | `admin.mounts` |

## 2. Configuração de um plugin

No admin (*Extensões* > botão ⚙), a configuração é gerada a partir do
ficheiro `config.schema.json` do plugin. Tipos suportados: `string`,
`text`, `integer`, `number`, `password`, `bool`, `enum`, `color`, `list` -
`color` renderiza um seletor nativo + um campo `#RRGGBB` (o valor vazio é
aceite: o campo recai na predefinição do plugin). Os valores são armazenados
na tabela `plugins` (coluna `config`, JSON).

Detalhes de configuração notáveis:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (predefinição `*`), `pass_min_length` (10),
  `pass_min_strength` (70), `check_hibp` (k-anonimato), depois os parâmetros
  próprios do driver (`poppassd_host/port`, `pdo_dsn`/`pdo_sql` com
  placeholders `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`,
  `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (placeholder `%u`), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (placeholder `%q`), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (um domínio protegido cobre
  também os seus subdomínios, ex. `accountprotection.microsoft.com`),
  `brands` (detetados no nome mostrado após normalização dos acentos e dos
  caracteres confundíveis - "AppIe TV" é reconhecido como "Apple TV"), e os
  interruptores
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  Os resultados SPF/DKIM/DMARC só são tidos em conta quando alinhados com o
  domínio do `From`: um `dkim=fail` sobre a assinatura do transportador ou
  um `spf=fail` sobre um mailfrom reenviado (SRS) não dispara alerta.
- **`two-factor-auth`** - `issuer` (predefinição `app_name`), `label`
  (`email` = endereço completo, `local` = parte antes de `@`; `local` evita
  que as apps TOTP deduzam um ícone de marca do domínio, ex. Yahoo) e
  `icon_url` (parâmetro `image=` não padrão, mostrado por
  Aegis/andOTP/FreeOTP - ignorado pelo Authy; vazio = favicon público da
  instância). O plugin provisiona também a 2FA da conta admin local:
  segredo `admin_totp_secret` em `config.php` (via a API admin
  `/api/admin/plugin/two-factor-auth/*`), pendente em sessão, exigido no
  login admin via o hook `admin.post-login`. Desativar o plugin desativa a
  aplicação admin.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcards;
  valores separados por espaços, vírgulas ou ponto e vírgula), `exceptions`,
  `auth_error` (403 "conta não autorizada" > 401 genérico). Atenção à
  semântica herdada do plugin legacy: para black-list, `exceptions` =
  emails da lista negra que **continuam autorizados**; para white-list, se
  `exceptions` estiver vazio todo email fora da lista branca é rejeitado,
  mas se estiver informado **só os emails das exceções são rejeitados** e
  tudo o resto passa - não pôr lá endereços "autorizados adicionalmente"
  (efeito inverso ao esperado).

- **`link-confirmation`** - `mode` (`all` = todo link externo, `suspicious`
  = só os links suspeitos), `whitelist` (domínios de confiança, um por linha
  ou separados por espaço/vírgula/ponto e vírgula - subdomínios incluídos),
  `warn_mailto` (confirmar também `mailto:`/`tel:`, desativado por defeito)
  e `remember` (caixa "não perguntar mais para este domínio" - memorizado
  no navegador via localStorage, não do lado do servidor).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (destinos, vazio =
  inativo), `senders` (wildcards de remetentes, `*` = todos).
- **`report-abuse`** - `abuse_address` (requerido, vazio = inativo),
  `subject_prefix`, `mark_spam` (marca também em spam via `message.spam` -
  alimenta a aprendizagem).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day`
  (0 = ilimitado), `exempt` (wildcards de contas isentas). Limita só o
  webmail: um cliente IMAP/SMTP direto contorna a quota.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (separador Definições de opt-out por
  utilizador).
- **`sso-header`** - `header` (`X-Forwarded-User` ou `REMOTE_USER`),
  `trusted_ips` (**requerido, vazio = plugin inerte**), `master_pass`
  (requerido; modo *same* = passdb `master=yes`), `master_user` (opcional:
  login `user*master`), `allowed_domains`, `hide_form`. A instância não
  deve ser alcançável contornando o proxy (o cabeçalho seria falsificável).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (caixa IMAP
  dos relatórios), `folder`, `batch_size`, `delete_processed`,
  `processed_folder`. Ligação IMAP própria do plugin (as opções do domínio
  não se aplicam).

Alguns plugins não têm esquema admin: a sua configuração é **por
utilizador** (ex. `carddav-contacts`, `color-tags`, `two-factor-auth` para
o estado da conta).

## 3. Criar um plugin

### Estrutura mínima

```
app/plugins/meu-plugin/
├── plugin.json          # manifesto
├── MeuPlugin.php        # classe principal
├── config.schema.json   # (opcional) esquema de configuração admin
├── langs/<lang>.php     # dicionários (chaves com prefixo plugin.<name>.)
└── js/ ou assets/       # (opcional) assets servidos via /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "meu-plugin",
	"label": "plugin.meu-plugin.label",
	"class": "MeuPlugin",
	"main": "MeuPlugin.php",
	"version": "0.1.0",
	"description": "plugin.meu-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (opcional) é o nome mostrado do plugin em Admin > Extensões (chave
i18n `plugin.<name>.label` recomendada, texto literal aceite; o nome técnico
`name` fica mostrado ao lado e serve de identificador).

`min_app`/`max_app` (opcionais, semver `x.y.z`) limitam a compatibilidade
com `APP_VERSION` - a instalação ou atualização é rejeitada fora de
intervalo, e o catálogo marca a entrada *incompatível*.

### Classe principal

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MeuPlugin extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Hooks disponíveis

- **Núcleo**: `app.routes`, `app.head` (HTML injetado no `<head>` de
  `app.php`, `login.php` e `maintenance.php` - as vistas `admin*.php` não o
  emitem), `app.data`, `auth.login.mapping`, `auth.login.mapped`,
  `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin**: `admin.mounts` (`collect` > descritor `{js, plugin, section,
  tab?, label?}` renderizado por `pluginMount` no painel admin - sem `tab`:
  bloco inline na zona chamada `section`, ex. `security-account`; com `tab`
  + `label`: sub-separador dedicado da página chamada `section`, ex.
  `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, emitido no login da conta admin local -
  lançar `TotpRequiredException` exige um segundo fator),
  `admin.totp-required` (`first` > booleano: mostra o campo TOTP na página
  de login admin).

Cada plugin declara em `hooks` os hooks que escuta. Um manifesto que só
declara hooks `admin.*` marca o plugin como puramente admin - a coluna
*Acesso utilizadores* do separador Extensões não mostra switch para ele. O
núcleo chama `HookRegistry::run('hook', …)`; o plugin implementa um método
do mesmo nome (ou um `on<Hook>` segundo a convenção do núcleo).

### API genérica

Todo plugin é chamável via `/api/plugin/<name>/<action>` (GET/POST) >
método `api<Action>(Request $r, ?array $user)`. O token CSRF é exigido em
todos os métodos (o cliente `api.js` envia-o sistematicamente) - as
chamadas diretas sem cabeçalho `X-CSRF-Token` são rejeitadas em 403.

Do lado admin, `/api/admin/plugin/<name>/<action>` (GET/POST) > método
`apiAdmin<Action>(Request $r)`: sessão admin (`$_SESSION['admin']`) + CSRF
requerido; não há `$user` (a conta admin local não é uma linha `users`).
Atenção: `requireAdmin` fecha a sessão em escrita - para aí armazenar um
estado (ex. segredo pendente), reabra-a via
`$this->app->startSession()` depois `session_write_close()`.

### Separador de utilizador

Para adicionar um separador em `#/@settings/…`, forneça JS servido via
`/assets/plugin/<name>/<file>` e declare o separador (ver os plugins
`change-password`, `carddav-contacts`, `color-tags`, `two-factor-auth` como
exemplos).

### Traduções

Chaves com prefixo `plugin.<name>.` em `langs/<lang>.php`;
`description`/`label`/`help` dos manifestos e esquemas aceitam estas chaves
(pass-through senão). Lado PHP: `$this->t('chave')`, lado JS:
`ctx.t('chave')` em `mount(element, ctx)`.
