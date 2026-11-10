# Fail2ban

*Traduzido do francês - a versão francesa é a que faz fé.*

O MXWebMail pode escrever um registo de autenticação dedicado, pensado para
ser lido pelo [fail2ban](https://github.com/fail2ban/fail2ban) com o fim de
banir os IPs que multiplicam as falhas de ligação. Esta página descreve a
ativação do registo e uma integração fail2ban completa (filtro, jail,
rotação).

## Ativação

Três chaves de `app/config/config.php` (referência:
[configuration.md](configuration.md#registo-de-autenticação-estilo-fail2ban)),
também editáveis no admin, secção *Registos*:

| Chave | Predefinição | Função |
|---|---|---|
| `auth_logging` | `false` | Ativa o registo. |
| `auth_logging_filename` | `auth.log` | Ficheiro em `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Formato de linha. |

Com o formato predefinido, uma linha assemelha-se a:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` vale `AUTH OK`, `AUTH FAILED (<código http>)` ou
  `AUTH FAILED (totp)` (código TOTP incorreto introduzido - o pedido inicial
  de segundo fator não é uma falha e não regista nada).
- As tentativas sobre o **login admin** estão cobertas: o login leva o
  prefixo `admin:` (ex. `admin:admintest`). Um login IMAP válido mas não
  admin é registado `AUTH FAILED (403)`.
- As tentativas já bloqueadas pelo rate-limit interno são registadas
  `AUTH FAILED (429)` - portanto também contam para o fail2ban
  (reincidência).
- As quebras de linha são neutralizadas no login e no resultado: uma entrada
  forjada não pode injetar linhas falsas no ficheiro.

## Filtro

`/etc/fail2ban/filter.d/mxwebmail.conf` (para o formato predefinido):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

Se `auth_logging_format` for personalizado, adapte `failregex` (e
`datepattern` se `{date:FMT}` mudar - o fail2ban exige uma marca temporal
reconhecível no início da linha ou identificável via `datepattern`).

Verifique que o filtro corresponde ao ficheiro real:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (ou `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Depois `fail2ban-client reload` e `fail2ban-client status mxwebmail`.

## Pontos de vigilância

- **Fuso horário**: o fail2ban compara a marca temporal das linhas com a
  hora do sistema. Deixe `logs.time_zone` vazio (= fuso do servidor) ou
  ajuste-o ao **mesmo** fuso que o sistema que executa o fail2ban - um
  desfasamento falseia `findtime` e o ban.
- **Proxy inverso**: sem `trusted_proxies` informado, o registo contém o IP
  do proxy - o fail2ban baniria então o proxy e cortaria o acesso a
  **todos** os utilizadores. Declare os proxies de confiança e verifique que
  o IP registado é o do cliente.
- **Rotação**: o ficheiro cresce sem limite. Exemplo `logrotate`:

  ```
  /var/www/mxwebmail/app/storage/logs/auth.log {
      weekly
      rotate 8
      missingok
      notifempty
      compress
      copytruncate
  }
  ```

- **Complemento, não substituição**: o fail2ban atua ao nível da rede; o
  rate-limit interno (`login_max_attempts`, `login_lock_seconds` - ver
  [securite.md](securite.md#rate-limit-do-login)) continua a ser a primeira
  linha de defesa da aplicação. Ambos são independentes e acumuláveis.
