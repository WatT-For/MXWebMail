# Desenvolvimento

*Traduzido do francês - a versão francesa é a que faz fé.*

Documentação para compreender a arquitetura e estender o projeto (núcleo,
plugins, temas).

## 1. Arquitetura

```
public/                front controller (index.php) + assets
app/src/MXWebMail/     código da aplicação:
  Application/App.php  contentor de serviços + dispatch + rotas
  API.php, Utils.php   fachadas de compatibilidade para o código vendorado (MailSo/Lib)
  Compat/              pontes de compatibilidade MailSo (ActionsBridge,
                       ConfigBridge, PluginsBridge)
  Config/              carregamento/escrita da configuração
  Controller/          PageController, ApiController, AdminController,
                       SetupController (assistente /setup, servido enquanto
                       storage/INSTALLED estiver ausente - depois 404) ;
    Api/               controladores API por domínio (Messages, Folders,
                       Contacts, Compose, Accounts, Settings, Filters,
                       Pgp, Poll)
    Admin/             controladores admin por separador (System, Domains, Users,
                       Plugins, Themes)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + dicionários
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          acesso a dados (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (HTTP Releases partilhado), UpdateService (núcleo),
                       PluginPackageService (catálogo/install/remove de plugins),
                       importações de contactos (CsvContacts, VCardContacts,
                       RainLoopContacts)
  Sieve/               ScriptBuilder - geração de regras de filtros
  Theme/               ThemeManager
app/src/MailSo/        biblioteca de correio vendorada (sem modificações)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           utilidades MXWebMailLib (classmap)
app/src/bootstrap.php  carregador automático próprio
```

- **Frontend**: Knockout + Bootstrap 5.3, módulos ES em `public/assets/js/`
  (app.js, viewmodels/, models/, bindings/, ui/).
- **Sem Composer**: carregador automático próprio (ver §2).

## 2. Carregador automático (`app/src/bootstrap.php`)

- **PSR-4**: `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` > `app/src/Sabre`.
- **Classmap insensível a maiúsculas**: `MailSo\` > `app/src/MailSo`,
  `MXWebMailLib\` > `app/src/Lib` (código legado, ficheiros em minúsculas,
  multi-classe). Gerada por varredura `token_get_all`, cacheada em
  `app/storage/cache/classmap.php`, reconstruída automaticamente em falha
  (uma vez por pedido).
- Carrega os `functions.php` de sabre/uri e sabre/xml (autoload "files").

## 3. Hooks do núcleo

Os plugins registam-se em: `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`,
`imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`,
`message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

No painel admin: `admin.mounts` (collect) alimenta `adminMounts` do
bootstrap - descritor `{js, plugin, section, tab?, label?}` montado via o
binding `pluginMount` (opt `admin: true` > `ctx.admin`, as chamadas do
módulo são prefixadas `/admin/plugin/…` para `Plugin::apiAdmin<Action>`).
Sem `tab`: bloco inline na zona `section` (ex. `security-account`); com
`tab` + `label`: sub-separador dedicado da página `section` (ex. `security`
> `#/security/<tab>`), renderizado só se o plugin estiver ativado. A secção
especial `'page'` (+ `icon` opcional, classe Font Awesome) cria uma
**página de nível superior** no menu lateral (`#/<tab>`). `admin.post-login`
é emitido no login da conta admin local; lançar `TotpRequiredException` aí
bloqueia o acesso (ex. TOTP admin).

Ver [plugins.md](plugins.md) para criar um plugin.

## 3bis. Distribuição de plugins (GitHubClient + PluginPackageService)

- `GitHubClient` mutualiza o acesso GitHub para `UpdateService` (pacote
  núcleo `mxwebmail-*.zip` via `coreAsset`) e `PluginPackageService` (leitura
  do repo no ref `update.plugins_ref`: `resolveCommit()` >
  `GET /commits/<ref>` dá sha + tree sha, `tree()` >
  `GET /git/trees/<sha>?recursive=1` mapeia os blobs `app/plugins/**`,
  `rawUrl()` > `raw.githubusercontent.com` fixado no sha do commit).
  Repositório bloqueado pela constante `REPO`, cache de release partilhada
  `storage/cache/update-check.json`. Cada asset truncado traz `download_url`,
  resolvido por `pickUrl()`: endpoint API `releases/assets/<id>`
  (`Accept: application/octet-stream`) se um PAT estiver configurado em
  `config.php` - obrigatório em repo privado onde `browser_download_url`
  devolve 404 - senão `browser_download_url`. Caches de plugins:
  `plugins-ref.json` (TTL `update_ttl`), `plugins-tree-<sha>.json` e
  `plugin-manifest-<name>-<sha>.json` (imutáveis).
- `PluginPackageService`: `catalog()`, `install()` (stageia cada ficheiro via
  raw > verif. tamanho + `blobId()` sha1 git > validação `plugin.json` +
  `min_app`/`max_app` > swap com backup único por plugin > invalidação
  classmap/opcache), `remove()` (rmrf + purga da linha `plugins` via
  `PluginRepository::delete()`). Lock partilhado `storage/update/update.lock`
  com `UpdateService`. Staging em `storage/update/plugins/`. Fonte
  alternativa `update.plugins_local` (disco, dev/testes, sem verificação
  remota).
- O empacotamento do zip de release exclui `app/plugins/*` do zip núcleo -
  nenhum asset de plugin é publicado: bump `version` em `plugin.json` + push
  no ramo seguido = publicação. O zip exclui também os ficheiros meta do repo
  (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`, `SECURITY.md`,
  `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`); `LICENSE` continua distribuído.

## 4. Cache & compressão

Tudo é configurável (admin > *Desempenho*, secções `http.*` / `cache.*` de
`config.php`):

- **Compressão**: `App::sendResponse()` chama `Response::gzip()` no ponto de
  envio único - cobre `php -S`, Apache e Nginx. As respostas já codificadas
  ou de tipo não comprimível são ignoradas; `Vary: Accept-Encoding` é posto
  assim que o tipo é comprimível. `zlib.output_compression` ativo > no-op.
- **Assets estáticos** (`public/assets/img|js|vendor`): servidos pelo
  servidor web sob o URL versionado `/assets/v<APP_VERSION>/…` (rewrite
  `public/.htaccess`, bloco `location` nginx documentado em installation.md).
  TTL de 1 ano immutable sob Apache; o servidor de dev serve em `no-cache`.
- **Assets PHP** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`, favicon):
  `PageController::serveAsset()` põe `Cache-Control` de `http.assets_ttl` /
  `http.favicon_ttl` (+ `immutable` se `?v=` presente) e, quando `http.etag`,
  `ETag`/`Last-Modified` > `304` em `If-None-Match`. Os `?v=` vêm de
  `theme.json`/`plugin.json` (`ThemeManager::version`, `PluginBase::version`) -
  o bump dirigido de um componente busta a sua própria cache.
- **Cache de servidor**: `ActionsBridge::Cacher()` devolve um cliente sem
  driver quando `cache.enabled=false` (Get/Set no-op). GC periódico
  (`cache.gc_hours`, marcador `storage/cache/_gc.ts`) - purga todos os
  ficheiros da pasta, incluindo `classmap.php` (reconstruído no pedido
  seguinte). TTL próprios: `cache.domain_detect_ttl` (detecção),
  `cache.update_ttl` (releases).
- **HTML**: `no-cache` - o shell referencia sempre os assets da versão
  atual. As respostas API/privadas nunca recebem TTL público.

## 5. Convenções do projeto

- **Bootstrap**: um único componente JS por elemento (`Data`) - nunca
  `tooltip:` (nem outro binding) num elemento `data-bs-toggle`; colocá-lo no
  ícone filho.
- **Versionamento** semver `0.MINOR.PATCH`: `PATCH` = correção/refator,
  `MINOR` = feature ou breaking change. Bump dirigido: `APP_VERSION`
  (núcleo) ou `plugin.json`/`theme.json` (componente modificado). Sem bump
  global a cada commit.
