# Segurança

*Traduzido do francês - a versão francesa é a que faz fé.*

## 1. Modelo de segurança integrado

### "Manter sessão" (tokens persistentes)

A caixa "Manter sessão" emite um cookie `mxwm_remember`
(`selector:validator`, HttpOnly, Secure, SameSite=Lax, duração
`remember_days` - 30 dias por defeito). A tabela `auth_tokens` só armazena o
**hash SHA-256** do validador; este é **rodado a cada uso** (um cookie
roubado já apresentado é detetável e invalidado). O restauro volta a
decifrar a palavra-passe IMAP (`CredentialCrypt`) e volta a verificar as
credenciais - uma falha elimina o token. Cada token regista o **IP na
emissão** (`ip`), o **último IP** conhecido (`last_ip`, a cada restauro) e o
User-Agent - visíveis em Definições > Sessões para detetar uma sessão
desconhecida. Os tokens são aí listados e revogáveis, e o token atual é
revogado no encerramento de sessão **manual** (o encerramento por
inatividade `autologout` preserva-o: a sessão é restaurada silenciosamente
na visita seguinte).

### CSRF

Todo POST exige um token CSRF de sessão (`X-CSRF-Token`, campo de formulário
ou chave JSON); as APIs de plugins (`/api/plugin/…`) exigem-no seja qual for
o método HTTP. Uma segunda camada, baseada nos cabeçalhos **`Sec-Fetch-*`**,
rejeita os pedidos cross-site salvo navegação GET para um documento - mesmo
sem ativação do utilizador, porque o navegador retira `Sec-Fetch-User` após
cada redirecionamento (portais, links reescritos) (`secfetch_allow` permite
alargar estas regras).

### Cabeçalhos HTTP

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, HSTS opcional, e uma
**CSP** com nonce por pedido (a diretiva `script-src` inclui `unsafe-eval`,
requerido pelo Knockout). Os anexos servidos inline recebem
`Content-Security-Policy: sandbox`.

### Cifragem de credenciais

As palavras-passe IMAP (e os segredos de plugins) são cifradas
**XSalsa20-Poly1305** (`sodium_crypto_secretbox`, libsodium) com a chave
mestra `app/storage/app.key`, em sessão e na base. Ver
[base-de-donnees.md](base-de-donnees.md).

### Contas secundárias OAuth2

Os domínios públicos (Gmail, Outlook, Yahoo - ver
[domaines.md](domaines.md)) são adicionados por **authorization code +
PKCE**: o `state` anti-CSRF e o verifier são mantidos em sessão, o
`redirect_uri` é reconstruído do lado do servidor (host filtrado como
`force_https`). Só o *refresh token* é persistido, cifrado como uma
palavra-passe; o *access token* efémero só existe em memória durante a
ligação (XOAUTH2/OAUTHBEARER). A rotação de refresh token (Microsoft,
Yahoo) é reaplicada na base a cada troca. O email do `id_token` serve de
controlo de coerência; a prova de pertença continua a ser a própria ligação
IMAP OAuth. O callback (`/api/oauth/callback`) só devolve uma mini-página
`postMessage`>`window.close()` para o popup de consentimento.

As credenciais de aplicação OAuth2 são **por utilizador** (tabela
`oauth_apps`, introduzidas na primeira adição de conta ou nas definições):
o `secret` é cifrado com a mesma chave mestra e nunca é devolvido à
interface. Uma app eliminada ou editada invalida as contas que dependem dela
(o refresh token fica ligado ao client_id de origem).

### Rate-limit do login

`login_max_attempts` (5) depois bloqueio `login_lock_seconds` (300 s). O
contador é persistido em `storage/cache/login_attempts.json` (chave
`sha256(IP|login)`, purga de entradas inativas > 1 h, escrita sob lock
`flock`). Um bloqueio ativo não é nem prolongado nem re-registado pelas
tentativas rejeitadas (429). Aplica-se também ao login admin. Um registo de
autenticação estilo fail2ban está disponível (`auth_logging*`, placeholders
`{request:ip}`, `{imap:login}`, `{result}`; CR/LF neutralizados): cobre os
logins webmail e admin (login com prefixo `admin:`) e as falhas de código
TOTP. Integração completa: [fail2ban.md](fail2ban.md).

### Sanitização do HTML das mensagens

O corpo das mensagens é sanitizado no navegador (DOMParser inerte +
allowlist): tags e atributos restritos (sem
`script`/`iframe`/`object`/`on*`…), bloqueio dos esquemas
`javascript:`/`vbscript:`/`data:`, neutralização dos estilos traiçoeiros
(`position:fixed`, conteúdo oculto), prefixação de classes. A CSP é a
segunda camada.

### Envio de mensagens

- **From**: whitelist estrita - o endereço da conta ou uma identidade
  declarada (IDN aceite nas duas formas); qualquer outro valor é rejeitado
  (403).
- **Destinatários**: os campos To/Cc/Bcc passam por um parse estrito - um
  segmento inválido não é descartado silenciosamente, o envio é rejeitado
  (400) com a lista de endereços incompreendidos.
- **CR/LF**: neutralizados no assunto, nos cabeçalhos de threading
  (In-Reply-To, References), nos nomes de pastas referenciados e nos
  parâmetros MIME dos anexos (`filename=`).
- **Bcc**: o cabeçalho é retirado dos dados enviados ao servidor SMTP mas
  conservado na cópia "Itens enviados" (visível só para o remetente).
- **Editor**: o iframe TinyMCE traz uma `Content-Security-Policy` que
  bloqueia todo carregamento remoto - o conteúdo de uma mensagem
  reencaminhada/colada não pode exfiltrar uma confirmação de leitura ao
  abrir.
- **Anexos temporários**: tokens com âmbito de utilizador, nome sanitizado
  (`basename` + caracteres de controlo eliminados), purga a 24 h.
- **Erros SMTP**: o cliente recebe uma mensagem genérica; as respostas
  brutas do servidor só são expostas se `labs.smtp_show_server_errors`
  estiver ativado (diagnóstico, pode revelar detalhes internos).

### OpenPGP

A cifragem/decifragem faz-se **inteiramente no navegador** (OpenPGP.js). O
servidor só armazena o porta-chaves armored; as chaves privadas estão
protegidas pela sua passphrase e nunca são decifradas do lado do servidor.
**Só o corpo da mensagem é cifrado**: o envio de anexos é rejeitado quando a
cifragem está ativada (sairiam em claro).

### Proxy de imagens

As imagens remotas passam por `/api/proxy`: só `http(s)`, allowlist
opcional (wildcards), **anti-SSRF** (rejeição de IPs privados/reservados,
revalidado em **cada** redirecionamento - 3 máximo), timeout, tamanho
limitado. O MIME real do corpo é verificado contra uma **allowlist bitmap**
(png, jpeg, gif, webp, avif, bmp, ico) - SVG e todo tipo ativo são rejeitados -
e a resposta traz `Content-Security-Policy: sandbox` + `nosniff`. Os
registos só conservam scheme+host+caminho (nunca a query, que pode conter
tokens).

### Gestão de IPs e proxies

`X-Forwarded-For`/`X-Forwarded-Proto` só são tidos em conta se
`REMOTE_ADDR` figurar em `trusted_proxies` (CIDR). A deteção HTTPS
(`isHttps`) segue a mesma regra.

## 2. Recomendações de endurecimento

- **Document root = `public/`** apenas; nunca apontar para a raiz do
  projeto. As pastas privadas (`app/`, `config/`, `storage/`…) têm os seus
  `.htaccess` de proteção, mas é apenas uma rede de segurança.
- **HTTPS obrigatório**: ative `force_https` + `hsts` em produção.
- **Atrás de um proxy inverso** (Nginx, HAProxy, Cloudflare…): informe
  `trusted_proxies` para tornar fiáveis rate-limit, allowlist e registos;
  sem isso, todo `X-Forwarded-*` é ignorado.
- **Restrinja o admin**: `admin_ip_allowlist` (IP/CIDR CSV) e um
  `admin_path` não trivial; ative o **TOTP admin** via o plugin
  `two-factor-auth` (o segredo `admin_totp_secret` só é exigido enquanto o
  plugin estiver ativado).
- **Conta admin local**: prefira `admin_user` + `admin_password_hash`
  (palavra-passe forte) em vez do fallback IMAP para a administração.
- **Faça cópia de `app.key`** fora do webroot; nunca o versione.
- **`debug = false`** em produção (não expor os erros internos).
- **O assistente `/setup`** está integrado no núcleo: responde 404 uma vez
  colocado o bloqueio `app/storage/INSTALLED` - já nenhum ficheiro de
  instalação a eliminar no webroot.
- Verifique `ssl.verify_certificate`: `false` só é aceitável para
  certificados auto-assinados controlados.
- Em modo manutenção, `maintenance = true` serve uma página de manutenção
  dedicada em 503 em `/` (utilizadores ligados basculados no seu próximo
  pedido API, sessão conservada) e corta o `/api/*` de utilizador (admin e
  `/api/ping` permanecem ativos).

## 3. Limites conhecidos

- O repositório `GitHubClient::REPO` está bloqueado em `WatT-For/MXWebMail`:
  os pacotes instalados (zip núcleo da release, ficheiros de plugins lidos
  no repo no ref `update.plugins_ref` via a API git +
  `raw.githubusercontent.com`, cada um verificado por sha1 de blob git) só
  provêm desse repositório - nenhum URL arbitrário configurável. O pacote
  núcleo é verificado em dois níveis: soma sha256 do asset (ficheiro
  `<zip>.sha256` da release) depois `files.sha256` (manifesto por ficheiro
  embarcado, controlado após extração); as constantes
  `APP_REQ_PHP`/`APP_REQ_FROM` do `App.php` do pacote declaram os
  pré-requisitos (versão PHP, versão de origem mínima) aplicados antes da
  cópia. Enquanto o repo permanecer **privado**, é requerido um PAT do
  GitHub (acesso `Contents: Read` ao repo) configurado em `config.php`: a
  API, os assets (servidos via o endpoint `releases/assets/<id>` - o
  `browser_download_url` devolve 404 em repo privado mesmo autenticado) e os
  `raw` dependem dele. `update.plugins_local` e `update.manifest_url`
  (manifesto de release alternativo) são reservados a dev/testes -
  modificáveis só via `config.php`, nunca via a API admin. Durante a cópia
  dos ficheiros, a flag `storage/update/applying` corta a API de utilizador
  (503, expira após 15 min).
- A mudança SQLite > MariaDB não migra os dados (esquema virgem).
- As chaves privadas OpenPGP são armazenadas armored do lado do servidor (a
  passphrase protege a própria chave, sem encapsulamento suplementar).
