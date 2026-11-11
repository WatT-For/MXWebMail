# Base de dados

*Traduzido do francês - a versão francesa é a que faz fé.*

O MXWebMail funciona com **SQLite** (predefinição), **MariaDB >= 10.x** ou
**MySQL >= 8.0**. O driver é escolhido via `db_dsn` no `config.php`.

## 1. Ligação

- `db_dsn = 'sqlite'` > `app/storage/db/webmail.sqlite` (modo WAL +
  `foreign_keys`).
- Senão, um DSN PDO completo (ex.
  `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`) com
  `db_user`/`db_pass`.

Opções PDO: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

## 2. Migrações

O esquema é **portável** entre SQLite/MariaDB/MySQL (chaves primárias
autoincrementadas adaptadas ao driver; MySQL >= 8.0 requerido pelas funções de
janela SQL usadas em v12). As migrações são versionadas (`meta.schema_version`)
e aplicadas automaticamente no primeiro acesso à base (`App::db()`), mesmo
durante a instalação. Sob o driver `mysql`, os objetos já presentes (coluna,
índice) são detetados via `information_schema`: uma reexecução após falha a meio
da migração é idempotente.

Após uma atualização do código, o separador *Sobre* dispara a
**finalização** (`/api/admin/update/finalize`): as migrações da nova versão
executam-se em contexto admin, depois as bases de contactos por utilizador
são migradas por sua vez (uma base em falha é registada e retentada no seu
próximo acesso). Como alternativa: `php app/bin/post-update.php` reexecuta
as migrações em CLI. Se o esquema for mais recente que o código (rollback de
ficheiros sem restauração da base), `meta.schema_ahead` é colocado e
assinalado em *Sobre* - as migrações ficam então suspensas.

## 3. Tabelas globais

| Tabela | Conteúdo |
|---|---|
| `meta` | pares chave/valor (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (único), `display_name`, `is_admin`, `disabled`, `session_epoch` (incrementado por "Forçar encerramento de sessão" - rejeita as sessões ativas no pedido seguinte), `created_at`, `last_login_at`. |
| `accounts` | contas IMAP dos utilizadores: `email`, `imap_user`, `imap_pass` (cifrado), `is_default` (conta principal, modificável em Definições > Contas), `position` (ordem de visualização das contas secundárias, reordenável por arrastar e largar em Definições > Contas), `auth` (`password`, `google`, `microsoft`, `yahoo` - OAuth2: `imap_pass` armazena então o *refresh token* cifrado). |
| `oauth_apps` | aplicações OAuth2 por utilizador: `provider`, `client_id`, `secret` (cifrado), `tenant` - uma app por `(user_id, provider)` (ver [domaines.md](domaines.md) §8). |
| `user_settings` | definições de utilizador (chave/valor), PK `(user_id, key)`. |
| `identities` | identidades de envio: `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | servidores de correio por domínio (ver [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (reservado ao admin se 0 - predefinição), `config` (JSON) por plugin. |
| `themes` | `config` (JSON) e `allowed` por tema. |
| `pgp_keys` | porta-chaves OpenPGP (`key_data` armored, `is_private`). |
| `auth_tokens` | tokens "Manter sessão": `selector` (único), `token` (SHA-256 do validador, rodado a cada uso), `agent` (User-Agent), `ip` (na emissão), `last_ip` (último restauro), `created_at`, `last_used_at`, `expires_at`. |

As antigas tabelas `contacts` / `contact_emails` foram deslocadas para fora
da base global (ver §5).

## 4. `app.key`

`app/storage/app.key` é uma chave mestra de 32 bytes (64 hex), gerada na
primeira cifragem de credencial. Cifra (XSalsa20-Poly1305, libsodium
secretbox):

- as palavras-passe IMAP em sessão e em `accounts.imap_pass` (e os *refresh
  tokens* OAuth2 das contas `auth != 'password'`);
- os segredos de aplicações OAuth2 dos utilizadores (`oauth_apps.secret`);
- os segredos de plugins (ex. palavra-passe CardDAV).

**Faça uma cópia e nunca a perca**: a sua perda invalida todas as
credenciais armazenadas (os utilizadores devem voltar a ligar-se). Nunca
deve ser servida nem versionada.

## 5. Bases por utilizador (contactos)

Os contactos estão isolados em bases SQLite individuais:
`app/storage/db/users/<id>.sqlite` (tabelas `contacts`, `contact_emails`).
Isto vale **também sob MariaDB**: os dados pessoais permanecem em ficheiros
SQLite.

A migração `contacts_moved` copia uma única vez os contactos da base global
para estes ficheiros (transação + `INSERT OR IGNORE`, auto-reparável), e
depois elimina as tabelas globais.

## 6. Passar de SQLite para MariaDB/MySQL

1. No admin (*Base de dados*), indique o DSN/host/porta/nome/utilizador/
   palavra-passe de MariaDB. O teste de ligação é **obrigatório** e cria a
   base (`utf8mb4`, collation `utf8mb4_unicode_ci`) se não existir.
2. As migrações reexecutam o esquema sobre a nova base.

> **Importante**: a mudança de `db_dsn` recomeça sobre um **esquema
> virgem** - os dados (utilizadores, domínios, definições) **não são
> migrados**. Não existe ferramenta de exportação. Os ficheiros de
> contactos por utilizador, em contrapartida, permanecem inalterados.

## 7. Cópia de segurança / restauro

- SQLite: copie `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) e
  `app.key`.
- MariaDB/MySQL: faça um dump da base com `mysqldump` (ou `mariadb-dump` em
  MariaDB), mais `app.key` e `db/users/*.sqlite`.
