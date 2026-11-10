# Instalação

*Traduzido do francês - a versão francesa é a que faz fé.*

## 1. Pré-requisitos

- **PHP ≥ 8.4** com as extensões: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom`, e `pdo_sqlite` **ou**
  `pdo_mysql`. `ldap` é opcional (só plugins LDAP).
  **`opcache` fortemente recomendado em produção** (`opcache.enable=1`) - a
  aplicação carrega dezenas de ficheiros PHP por pedido.
- Um servidor **IMAP** acessível (e SMTP, ManageSieve opcional) para o
  correio.
- **Nenhuma dependência a instalar**: o repo é autosuficiente (carregador
  automático próprio, MailSo, Sabre, assets e bibliotecas JS vendoradas).
- O document root do servidor web deve ser **`public/`**, nunca a raiz do
  projeto.

## 2. Estrutura de pastas

```
public/            raiz web - index.php, define.php, assets/, .htaccess
app/               todo o não-público (pode deslocar-se para fora da raiz web)
app/src/           código da aplicação + bibliotecas vendoradas
app/config/        config.php (não versionado) + config.example.php
app/plugins/       extensões (vazia na instalação - plugins do catálogo GitHub)
app/templates/     temas/páginas
app/storage/       runtime: db, cache, logs, tmp, app.key, INSTALLED - nunca no webroot
```

`public/define.php` define `MXWM_APP_DIR` (caminho da pasta `app/`). Para
tirar `app/` da raiz web, desloque a pasta e adapte esse caminho (absoluto ou
relativo).

## 3. Instalação via o assistente `/setup/` (recomendada)

1. Aponte o vhost para `public/`.
2. Abra `https://webmail.example.com/`. Enquanto o bloqueio
   `app/storage/INSTALLED` não existir, todo URL é redirecionado para
   `/setup/` - o assistente é servido pela própria aplicação (nenhum
   ficheiro de instalação no webroot).
3. O assistente desenrola-se em 5 etapas:
   - **Pré-requisitos**: verifica o PHP, as extensões, a presença dos
     ficheiros vendorados e a escrita de `app/storage/` (bloqueante) e
     `app/config/` (não bloqueante - o `config.php` pode ser criado à mão).
   - **Aplicação**: nome mostrado (`app_name`), caminho admin
     (`admin_path`), tamanho cumulativo máximo dos anexos
     (`attachments_max_total`, limitado a 25 MB por defeito e limitado por
     `upload_max_filesize`/`post_max_size`). O limite por ficheiro
     `attachment_max_size` é escrito derivado: `min(25,
     attachments_max_total)`.
   - **Base de dados**: SQLite (predefinição) ou MariaDB/MySQL. Em MariaDB,
     efetua-se um teste de ligação real; a base é criada (`utf8mb4`) na
     instalação.
   - **Administrador**: conta admin local (`admin_user` + palavra-passe ≥ 8
     caracteres). Se deixar estes campos vazios, o login admin recai sobre
     um utilizador `is_admin` autenticado via IMAP (ver
     [administration.md](administration.md)).
   - **Resumo**: controlo dos valores depois botão **Instalar**.
4. A instalação escreve `app/config/config.php` (um ficheiro existente é
   arquivado como `config.<timestamp>.bak`), aplica as migrações, tenta
   **detetar o domínio de alojamento** (SRV > autoconfig Mozilla > MX >
   sondagem) e adiciona-o à tabela `domains`, depois coloca o bloqueio
   `app/storage/INSTALLED`.
   Ao clicar em **Instalar**, aparece um ecrã de progresso a página inteira:
   a deteção do domínio pode fazer durar a operação até ~2 minutos - não
   feche a página (a instalação terminaria do lado do servidor na mesma).
   Três domínios públicos estão também **pré-autorizados como contas
   secundárias** - `gmail.com`, `outlook.com`, `yahoo.com` (OAuth2 exigido,
   login primário rejeitado; ver [domaines.md](domaines.md) §8). Elimináveis
   em admin > *Domínios*.
5. **Após a instalação**: `/setup` responde agora 404 (nada a eliminar) e
   **faça cópia de `app/storage/app.key`** (ver §6).

> Se `app/config/` não for gravável, o assistente mostra o conteúdo de
> `config.php` a criar manualmente. A instalação continua uma vez o ficheiro
> no lugar.

## 4. Instalação manual

```bash
cp app/config/config.example.php app/config/config.php   # editar os valores
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

A migração é aplicada na mesma no primeiro acesso (`App::db()`), basta pois
criar `config.php` depois `app/storage/INSTALLED` (o conteúdo importa pouco,
só `is_file()` é testado). `app/storage/app.key` é gerado automaticamente na
primeira cifragem de credencial.

## 5. Implantação web

### Apache

```apache
<VirtualHost *:443>
    ServerName webmail.example.com
    DocumentRoot /var/www/mxwebmail/public
    <Directory /var/www/mxwebmail/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

O `.htaccess` de raiz pode reescrever para `public/` como rede de
transição, mas o document root oficial é `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Deve ser >= attachment_max_size (25 MB por defeito).
    # attachments_max_total (cumul de anexos) é verificado no envio: cada
    # ficheiro chega em pedido separado.
    client_max_body_size 30m;

    # Dotfiles nunca servidos
    location ~ /\.(?!well-known) { deny all; }

    # Rede: pasta privada rejeitada mesmo se root apontar por erro para a raiz
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Assets versionados /assets/vX.Y.Z/… > ficheiro real (equivalente da
    # RewriteRule do .htaccess do Apache). TTL longo: a versão muda a cada
    # release. Os URL /assets/theme|plugin/ passam por try_files
    # > PHP (os seus cabeçalhos ficam geridos pela config "http").
    location ~ ^/assets/v[0-9.]+/ {
        rewrite ^/assets/v[0-9.]+/(.*)$ /assets/$1 last;
    }
    location ~ ^/assets/(img|js|vendor)/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

Observações:

- Os `.htaccess` são inertes sob Nginx: é o bloco `location ~ /\.` que
  esconde os dotfiles, e `^/(app|dist)` que protege a pasta privada.
- `autoindex` está desativado por defeito (equivalente de
  `Options -Indexes`).
- Compressão opcional: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Sob Apache, `public/.htaccess` cobre já `mod_deflate` + os TTL de assets;
  a compressão PHP (`http.gzip`) funciona em ambos os casos.

### Desempenho (recomendações servidor)

- **HTTP/2 (Apache)**: `a2enmod http2` depois `Protocols h2 h2c http/1.1` no
  VirtualHost (`h2` basta sob TLS; `h2c` só é útil a clientes não
  navegadores - Edge/Firefox só implementam h2 em HTTPS). `mod_http2` exige
  `mpm_event` (já a predefinição com PHP-FPM) - é incompatível com
  `mpm_prefork`/`mod_php`.
- **PHP-FPM**: `pm.max_children` adaptado à carga (a predefinição 5 mal
  basta para alguns separadores/utilizadores simultâneos: o poll, as
  imagens `cid:`, os anexos e o proxy de imagens paralelizam os pedidos).
- **OPcache**: ativo por defeito no pacote PHP Debian (`opcache.enable=1`
  em FPM) - verifique `opcache_get_status()` em caso de dúvida.

## 6. Etapas pós-instalação

1. **Faça cópia de `app/storage/app.key`**: cifra todas as credenciais
   armazenadas (palavras-passe IMAP, segredos de plugins). A sua perda
   obriga os utilizadores a ligarem-se de novo; nunca deve ser servido nem
   versionado.
2. **Crie pelo menos um domínio** no admin (página *Domínios*): com
   `restrict_domains = true` (predefinição), nenhum login é possível sem um
   domínio configurado. O instalador tenta detetá-lo automaticamente.
3. **Instale os plugins** a partir do admin, separador *Extensões*: o zip
   de release já não embarca os plugins - são descarregados, atualizados e
   desinstalados a partir desse catálogo (ver
   [administration.md](administration.md#extensões-plugins) e
   [plugins.md](plugins.md)). Na atualização do núcleo, os plugins já
   instalados são preservados (`app/plugins/` é excluído da cópia).
4. Verifique `force_https` / `hsts` se estiver atrás de um proxy inverso
   (ver [securite.md](securite.md) para `trusted_proxies`).
5. Informe `admin_ip_allowlist` se o painel admin deve ser restringido a
   certos IPs.
6. **Atualizações**: fazem-se a partir do admin (*Sobre*) via as GitHub
   Releases - verificação `files.sha256` por ficheiro, cópia de ficheiros +
   base SQLite, rollback assistido e migrações de base orquestradas.
   Detalhe em [administration.md](administration.md#sobre) e procedimentos
   de alternativa em [depannage.md](depannage.md#atualizações).

## 7. URL principais

- `/` - webmail (login > pastas > mensagens)
- `/{admin_path}` - painel de administração (predefinição `/admin`)
- `#/@settings/<separador>` - definições de utilizador (geral, identidades,
  contactos, filtros Sieve, OpenPGP, separadores de plugins)
