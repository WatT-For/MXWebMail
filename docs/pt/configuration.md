# Referência de configuração

*Traduzido do francês - a versão francesa é a que faz fé.*

O ficheiro `app/config/config.php` só define as chaves que **sobrescrevem**
os valores predefinidos (`app/config/config.example.php`, carregado por
`Config.php`). Copie `config.example.php` para `config.php` e adapte-o. O
painel de administração edita a maioria destas chaves por secção; apenas
algumas exigem edição manual (`update.*` sobretudo, mas também
`import_max_size`, `smime_ca_path`, `logs.max_size` e `logs.keep`).

Os arrays associativos são fundidos em profundidade; as listas são
substituídas.

## Armazenamento

| Chave | Predefinição | Função |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) ou DSN PDO completo (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | Utilizador PDO (MariaDB). |
| `db_pass` | `''` | Palavra-passe PDO (MariaDB). |

## Aplicação

| Chave | Predefinição | Função |
|---|---|---|
| `app_name` | `MXWebMail` | Nome mostrado (título, PWA, TOTP). |
| `favicon` | `''` | Ficheiro favicon carregado em `storage/branding`, servido via `/favicon.ico`; vazio = ícone predefinido. |
| `website_url` | `https://mxrelay.fr/webmail/` | Link para o site oficial (botão admin); vazio = oculto. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | Link "Sponsor" para a página de donativos (botão admin + definições webmail); vazio = oculto. |
| `debug` | `false` | Devolve os erros internos aos clientes API (nunca em produção). |
| `language` | `auto` | `auto` = `Accept-Language` do navegador; senão código de um idioma instalado (`fr`, `en`…). Fallback final `en`. |

## Domínios

| Chave | Predefinição | Função |
|---|---|---|
| `default_domain` | `auto` | Sufixo de um login escrito sem `@dominio`: `auto` = host HTTP (sem `www.` nem porta), `''` = nenhum, ou um domínio fixo. |
| `restrict_domains` | `true` | Só os domínios nomeados da tabela `domains` são aceites no login (nem `*` nem a deteção se aplicam). |
| `autodetect.enabled` | `false` | Deteção automática da configuração de um domínio desconhecido no login. |
| `autodetect.use_ispdb` | `false` | Fallback sobre a base Mozilla (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Sondagem de portas `imap.`/`mail.`/`smtp.`/`sieve.<dominio>`. |

Os servidores IMAP/SMTP/Sieve configuram-se **por domínio** no admin (ver
[domaines.md](domaines.md)). O domínio `*` serve de parâmetros catch-all.

## Segurança

| Chave | Predefinição | Função |
|---|---|---|
| `admin_path` | `admin` | Segmento de URL do painel admin. |
| `admin_user` | `''` | Conta admin local; vazio = fallback sobre um utilizador `is_admin` verificado via IMAP. |
| `admin_password_hash` | `''` | `password_hash('palavra-passe', PASSWORD_DEFAULT)` da conta admin local. |
| `admin_totp_secret` | `''` | Segredo TOTP (base32) da conta admin local, escrito pelo plugin `two-factor-auth`; vazio = desativado. Não exigido se o plugin estiver desativado. |
| `login_max_attempts` | `5` | Tentativas de ligação antes de bloqueio temporário. |
| `login_lock_seconds` | `300` | Duração do bloqueio (segundos). |
| `admin_ip_allowlist` | `''` | IPs/CIDR (CSV) autorizados sobre o admin; vazio = todos. |
| `session_lifetime` | `0` | Duração de sessão em horas; `0` = cookie de sessão. |
| `remember_days` | `30` | Duração de vida dos tokens "Manter sessão" em dias (1-365). |
| `maintenance` | `false` | Modo manutenção: `/` serve uma página dedicada em 503 (todos públicos, sessão conservada) e o `/api/*` de utilizador responde 503 - `/api/admin/*` e `/api/ping` permanecem ativos. Os utilizadores ligados basculam para a página de manutenção no seu próximo pedido API; esta recarrega sozinha no fim. |
| `force_https` | `false` | Redireciona HTTP > HTTPS (301). |
| `hsts` | `false` | Emite `Strict-Transport-Security` quando o pedido é HTTPS. |
| `cookie_samesite` | `Lax` | `Lax` (predefinição, requerido para o retorno OAuth2) / `Strict` (quebra o fluxo OAuth2) / `None` (exige HTTPS). |
| `csp_extra` | `''` | Diretivas CSP adicionais (ex. `img-src https:`). |
| `trusted_proxies` | `''` | IPs/CIDR (CSV) dos reverse proxies de confiança. Obrigatório para tornar fiável `X-Forwarded-*` (rate-limit, allowlist, registos). |
| `secfetch_allow` | `''` | Regras `Sec-Fetch-*` adicionais (ex. `site=same-origin;mode=cors`). |

## Atualizações

O repositório oficial está bloqueado em `GitHubClient::REPO`
(`WatT-For/MXWebMail`). Esta secção - partilhada pelo updater núcleo e pelo
catálogo de plugins (lido no repo no ref `update.plugins_ref`, cada
ficheiro descarregado verificado por sha1 de blob git) - não é editável via
o admin (salvo `channel`).

| Chave | Predefinição | Função |
|---|---|---|
| `update.repo` | `''` | Fallback dev se `GitHubClient::REPO` estiver vazio (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (única definição exposta no admin). |
| `update.manifest_url` | `''` | Dev: manifesto JSON em formato release, elude a API GitHub. |
| `update.plugins_ref` | `'release'` | Ref git fonte do catálogo de plugins: `'release'` (tag da última release), `'main'` (ramo - um push de `plugin.json` publica a atualização) ou qualquer ref literal. |
| `update.plugins_local` | `''` | Dev/testes: caminho absoluto de uma pasta de plugins - catálogo + install a partir do disco, fora de rede e sem verificação remota. Prevalece sobre `plugins_ref`. |

## Funcionalidades

| Chave | Predefinição | Função |
|---|---|---|
| `attachment_max_size` | `25` | MB máximo por ficheiro. Deve ser ≤ `attachments_max_total` (além disso, o limite não tem efeito: o upload limita já ao `min` dos dois - o admin rejeita a gravação incoerente). |
| `attachments_max_total` | `25` | MB cumulados por mensagem. |
| `allow_additional_accounts` | `true` | O utilizador pode adicionar/gerir contas IMAP secundárias (Definições > Contas). |
| `allow_additional_identities` | `true` | O utilizador pode criar identidades adicionais. |
| `popup_identity` | `false` | Abre a definição de identidade no primeiro login se o nome estiver vazio. |
| `dangerous_actions` | `true` | Autoriza esvaziar/eliminar uma pasta, eliminar mensagens (incluindo a deslocação para o lixo). |
| `contacts_enable` | `true` | Livro de endereços + autocompletar. |
| `contacts_suggestions_limit` | `20` | Número máx de sugestões de autocompletar (1-100). |
| `import_max_size` | `100` | Tamanho máx de um `.mbox` importado (MB) - os `.eml` conservam o teto `attachment_max_size`. Não editável no admin. |
| `smime_ca_path` | `''` | Caminho CA (ficheiro PEM) para a confiança S/MIME; vazio = só assinatura criptográfica verificada (`PKCS7_NOVERIFY`). Não editável no admin. |

## `user_defaults` - predefinições das definições de utilizador

Sobrescrevíveis pelo utilizador. As chaves autorizadas em escrita via
`/api/settings` são estas mais as declaradas pelos plugins (hook
`settings.allowed`).

| Chave | Predefinição | Função |
|---|---|---|
| `messages_per_page` | `50` | Mensagens por página (5-200). |
| `list_sort` | `REVERSE DATE` | Ordenação da lista (critério IMAP SORT em whitelist). |
| `view_images` | `ask` | Imagens externas: `always` / `ask` / `match` (remetente conhecido). |
| `msg_default_action` | `reply` | Botão de resposta predefinido: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Segundos antes de marcar lida uma mensagem aberta (0 = imediato). |
| `compose_autosave` | `60` | Segundos entre gravações automáticas do rascunho (0 = nunca). |
| `mdn_mode` | `ask` | Confirmações de leitura: `ask` (banner de pedido) / `never` (oculto). |
| `autologout` | `30` | Minutos de inatividade antes do encerramento (0 = nunca). Não revoga o token "Manter sessão": a sessão é restaurada silenciosamente na visita seguinte. |
| `list_refresh` | `5` | Minutos entre atualizações automáticas (0 = nunca). |
| `notify_new_mail` | `false` | Notificação do navegador à chegada de um mail. |
| `contacts_autosave` | `true` | Guardar automaticamente o destinatário nos contactos. |
| `folder_pane_width` | `240` | Largura da coluna de pastas (px, 140-600). |
| `message_pane_width` | `340` | Largura da coluna de lista de mensagens (px, 200-800). |
| `layout` | `horizontal` | Disposição de leitura: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Altura da lista de mensagens em modo vertical (px, 120-1200). |
| `compose_modal_width` | `0` | Largura do modal de redação (px, 0 = predefinição Bootstrap). |
| `compose_modal_height` | `0` | Altura do modal de redação (px, 0 = auto). |
| `identity_modal_width` | `0` | Largura do modal de identidade (px, 0 = predefinição Bootstrap). |
| `identity_modal_height` | `0` | Altura do modal de identidade (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | Esvaziar o lixo ao encerrar sessão. |
| `empty_junk_on_logout` | `false` | Esvaziar a pasta de spam ao encerrar sessão. |
| `list_threads` | `false` | Agrupar as mensagens em conversas (IMAP THREAD). |
| `after_delete` | `next` | Após eliminar/mover o mail mostrado: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Atalhos de teclado da vista de correio (ajuda via a tecla `?`). |
| `theme` | `default` | Modelo de interface (pasta `app/templates/<name>`). |

## TLS (ligações de saída IMAP/SMTP/Sieve)

| Chave | Predefinição | Função |
|---|---|---|
| `ssl.verify_certificate` | `true` | Verificar o certificado (`false` = certificado auto-assinado). |
| `ssl.allow_self_signed` | `false` | Aceitar os certificados auto-assinados. |
| `ssl.cafile` / `ssl.capath` | `''` | CA personalizada (caminho absoluto / pasta). |
| `ssl.disable_compression` | `true` | Desativa a compressão TLS. |
| `ssl.security_level` | `1` | Nível de segurança OpenSSL (0-5). |
| `ssl.local_cert` | `''` | Eventual certificado de cliente. |

## Proxy de imagens externas

| Chave | Predefinição | Função |
|---|---|---|
| `image_proxy` | `true` | Servir as imagens remotas via `/api/proxy`. |
| `image_proxy_allowlist` | `''` | Domínios autorizados (CSV, `*.dominio` ok); vazio = todos (IPs privados sempre bloqueados). |

## HTTP - compressão & cache do navegador

Editáveis no admin, secção *Desempenho > HTTP*.

| Chave | Predefinição | Função |
|---|---|---|
| `http.gzip` | `true` | Comprime as respostas texto/JSON em PHP (gzip) - funciona também sem `mod_deflate`. Ignorado se `zlib.output_compression` estiver ativo. **Atrás de Apache/nginx: `false` aconselhado** - o servidor comprime em filtro (streaming) e o PHP evita um `gzencode` em memória. Manter `true` para `php -S` (dev, sem `mod_deflate`). |
| `http.gzip_level` | `6` | Nível de compressão 1-9. |
| `http.gzip_min_size` | `1024` | Tamanho mínimo do corpo (bytes) para comprimir. |
| `http.etag` | `true` | ETag/Last-Modified + respostas 304 sobre os assets servidos por PHP (tema, plugins, favicon). |
| `http.assets_ttl` | `86400` | `max-age` dos assets tema/plugin (`/assets/theme|plugin/*`). `0` = revalidação sistemática. Com `?v=` (mtime do ficheiro servido), `immutable` é emitido. |
| `http.favicon_ttl` | `3600` | `max-age` de `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (privado) das imagens via `/api/proxy` - serve também de TTL para a cache em disco (`storage/cache/proxy`). `0` = nenhuma cache (re-fetch + revalidação SSRF a cada visualização). Purga gerida por `cache.gc_hours`: manter `proxy_ttl ≤ cache.gc_hours`. |

As respostas privadas/API nunca recebem cache pública; o HTML é `no-cache`.

**Produção**: ativar **opcache** (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ o número de ficheiros PHP - o código
invalida ele próprio as suas caches via `opcache_invalidate`,
`opcache.validate_timestamps` pode ficar ativo). As caches aplicativas
(`classmap.php`, manifestos de plugins, dicionários i18n, `themedata.php`)
são geridas pelo código - nenhuma invalidação manual requerida.

## Cache aplicativa (ficheiro)

Editáveis no admin, secção *Desempenho > Cache de servidor*.

| Chave | Predefinição | Função |
|---|---|---|
| `cache.enabled` | `true` | Ativa a cache de ficheiro `app/storage/cache` (listas de mensagens IMAP indexadas sobre o ETag da pasta, DNS, deteção de domínio, verificação de atualização, GC da pasta). Não toca nem no carregador automático (`classmap.php`) nem em `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL da cache negativa de deteção IMAP/SMTP (s). |
| `cache.update_ttl` | `3600` | TTL do resultado de verificação de atualização (s). |
| `cache.gc_hours` | `168` | Intervalo de purga dos ficheiros de cache expirados (h); `0` = nunca. |

## Registos MailSo (protocolos IMAP/SMTP/Sieve)

| Chave | Predefinição | Função |
|---|---|---|
| `logs.enabled` | `true` | Escreve `storage/logs/mail.log` (segredos mascarados). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG` para traçar o protocolo. |
| `logs.syslog` | `false` | Duplicado para `syslog()` se o registo de ficheiro estiver inativo. |
| `logs.time_zone` | `''` | Fuso das marcas temporais (ex. `Europe/Paris`); vazio = servidor. |
| `logs.max_size` | `5` | Rotação além de N MiB - vale para `mail.log`, `app.log` e o registo de auth. |
| `logs.keep` | `3` | Rotações conservadas (`app.1.log` … `app.{keep}.log`, teto 20). |

## Registo de autenticação (estilo fail2ban)

| Chave | Predefinição | Função |
|---|---|---|
| `auth_logging` | `false` | Ativa o registo de autenticação dedicado. |
| `auth_logging_filename` | `auth.log` | Ficheiro em `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Placeholders: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` vale `AUTH OK`, `AUTH FAILED (<código http>)` ou
`AUTH FAILED (totp)`. O registo cobre o login webmail e o login admin
(login com prefixo `admin:`). Para a ligação fail2ban (filtro, jail,
logrotate), ver [fail2ban.md](fail2ban.md).

## Labs / compatibilidade

| Chave | Predefinição | Função |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Mostrar os erros SMTP brutos ao cliente (diagnóstico, pode revelar detalhes internos). |
