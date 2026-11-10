# Domínios e servidores de correio

*Traduzido do francês - a versão francesa é a que faz fé.*

Os servidores IMAP/SMTP/Sieve configuram-se **por domínio** no painel admin
(*Domínios*). Cada domínio descreve como alcançar o servidor de correio dos
seus utilizadores.

## 1. Campos de um domínio

| Campo | Valor |
|---|---|
| `name` | Nome de domínio (ex. `example.com`), ou `*` para o catch-all. |
| `imap_host` | Host do servidor IMAP. |
| `imap_port` | Porta IMAP (predefinição `993`). |
| `imap_encryption` | `ssl` (predefinição), `tls` (STARTTLS) ou `none`. |
| `smtp_host` | Host do servidor SMTP (envio). |
| `smtp_port` | Porta SMTP (predefinição `465`). |
| `smtp_encryption` | `ssl` (predefinição), `tls` ou `none`. |
| `sieve_host` | Host ManageSieve (opcional). |
| `sieve_port` | Porta Sieve (predefinição `4190`). |
| `active` | Ativar/desativar o domínio. |

## 2. Opções avançadas (`options`, JSON)

O campo `options` aceita um objeto JSON com definições finas por domínio:

- `whitelist` - lista de endereços autorizados para este domínio;
- `secondary_only` - o domínio só é utilizável como **conta secundária**
  (adição de conta a partir de um utilizador já ligado); o login primário é
  rejeitado com "Domínio reservado às contas secundárias";
- `oauth` - fornecedor OAuth2 do domínio (`google`, `microsoft`, `yahoo`):
  as contas secundárias são adicionadas por consentimento do fornecedor
  (popup), nunca por palavra-passe (ver §8);
- `imap.shortLogin` - truncar o login;
- `imap.lowerLogin` - forçar o login em minúsculas;
- `imap.timeout` - tempo de ligação IMAP;
- `imap.disabled_capabilities` - capacidades IMAP a desativar (servidores
  defeituosos). Exemplo: `ESORT` para um servidor que o anuncia sem
  implementar as opções de retorno RFC 5267 (`RETURN (PARTIAL ...)`) - o
  webmail já deteta este caso e bascula sozinho para a ordenação clássica; a
  chave só serve se a deteção não se aplicar (cache desativada);
- `imap.body_text_limit` - tamanho máx (bytes) dos corpos de texto
  recuperados ao abrir uma mensagem (predefinição `0` = ilimitado; um teto
  ~256 K economiza o fetch dos boletins muito longos);
- `imap.message_list_limit` - além (predefinição `10000` mensagens na
  pasta), o MailSo evita o SORT do servidor e cacheia a lista de UIDs em
  segundo plano;
- `smtp.useAuth` - forçar/desativar a autenticação SMTP;
- `smtp.timeout` - tempo de ligação SMTP;
- `sieve.encryption` - cifra da ligação Sieve.

## 3. Domínio `*` (catch-all)

O domínio `*` fornece os **parâmetros predefinidos** aplicados a todo
domínio não declarado - mas apenas quando `restrict_domains = false`: sob
`restrict_domains`, `*` é ignorado e não pode autorizar um login primário.
É a forma mais simples de servir vários domínios atrás do mesmo servidor de
correio.

## 4. Resolução do domínio no login

No login, o domínio é determinado assim:

1. Se o utilizador escreve um login com `@dominio`, esse domínio é usado.
2. Senão, `default_domain` aplica-se: `auto` (predefinição) = host HTTP sem
   `www.` nem porta; um nome fixo; ou `''` (nenhum).
3. Se `restrict_domains = true` (predefinição), só os domínios **nomeados**
   presentes na tabela são aceites (`*` e a deteção são ignorados) -
   **crie portanto pelo menos um domínio antes de todo login**.

## 5. Deteção automática

`autodetect.*` (desativado por defeito) permite descobrir a configuração de
um domínio desconhecido no momento do login:

- `enabled`: ativa a deteção;
- `use_ispdb`: consulta a base Mozilla (`autoconfig.thunderbird.net`);
- `probe`: sonda `imap.`/`mail.`/`smtp.<dominio>`.

A ordem de deteção é: DNS SRV > autoconfig Mozilla > MX > sondagem de
portas.

O Sieve é detetado qualquer que seja a fonte: `_sieve._tcp` (SRV), senão
sondagem da porta **4190** sobre `sieve.<dominio>` (se `probe` ativo ou via
o botão *Detetar*) depois sobre o host IMAP encontrado - o Dovecot serve
quase sempre ManageSieve sobre o mesmo host. A cifra não há que configurar:
a ligação bascula para STARTTLS automaticamente quando o servidor a anuncia.

O botão **Detetar** da página *Domínios* lança a mesma deteção a pedido,
independentemente de `autodetect.enabled`, e preenche os campos do domínio.

> O instalador `/setup/` tenta também esta deteção sobre o host do vhost (e
> os seus pais) para criar automaticamente o primeiro domínio.

## 6. Teste

O botão **Testar a ligação** valida o protocolo do separador ativo do modal
(IMAP, SMTP ou Sieve; os outros separadores testam IMAP). Use-o após cada
modificação para verificar que credenciais, portas e cifras estão corretos
antes de deixar os utilizadores ligarem-se.

## 7. Caso comum: um único servidor de correio

Para um servidor único (ex. Postfix + Dovecot em `mail.example.com`
servindo `example.com`):

1. Crie o domínio `example.com` com `imap_host = mail.example.com`,
   `smtp_host = mail.example.com`, e eventualmente `sieve_host`.
2. Deixe `default_domain = auto` e `restrict_domains = true`.
3. Os utilizadores ligam-se com `user@example.com` ou simplesmente `user`
   (o domínio é deduzido do host HTTP).

Variante multi-domínios: um domínio `*` com `restrict_domains = false`
serve de catch-all - todo domínio não declarado herda os seus parâmetros
(ver §3; atenção, o login primário continua então possível em todos os
domínios - `secondary_only` pode rejeitá-lo, ver §8).

## 8. Domínios públicos como conta secundária (Gmail, Outlook, Yahoo)

O Gmail, Outlook.com e Yahoo já não aceitam a autenticação IMAP por simples
palavra-passe - a adição destas contas passa por **OAuth2** (consentimento
num popup, depois XOAUTH2/OAUTHBEARER para IMAP e SMTP).

As credenciais de aplicação OAuth2 (client ID, secret, tenant Microsoft)
são **próprias de cada utilizador**: o admin não regista nenhuma app - só
autoriza o fornecedor sobre o domínio. Cada utilizador cria a sua própria
aplicação na consola de desenvolvedores do fornecedor.

### Configuração (admin)

Estes três domínios estão **pré-criados na instalação** (conta secundária +
fornecedor OAuth já definidos). Se os eliminou, recrie-os assim:

1. **Crie o domínio** (*Domínios > Adicionar* ou botão *Detetar*):
   `imap.gmail.com:993` / `smtp.gmail.com:465` para o Gmail,
   `outlook.office365.com:993` / `smtp.office365.com:587` para o Outlook,
   `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465` para o Yahoo.
2. Marque **Apenas conta secundária** (login primário rejeitado) e escolha
   o fornecedor em **Auth OAuth2**.

### Registo da aplicação (utilizador)

Na primeira adição de uma conta do domínio, o webmail pede as credenciais
da app e regista-as para o utilizador - geríveis depois em *Definições >
Aplicações OAuth2* (uma app por fornecedor, reutilizada para todas as
contas desse fornecedor). URI de redirecionamento a declarar na consola:
`<base>/api/oauth/callback`.

- **Google** (Cloud Console > APIs & Services > Credentials > cliente OAuth
  Web, scope `https://mail.google.com/`): scope *restrito* - uma app em
  modo "Testing" está limitada a ~100 utilizadores de teste e os seus
  refresh tokens expiram após ~7 dias; a produção exige a verificação do
  Google.
- **Microsoft** (Entra > App registrations, `signInAudience` incluindo as
  contas pessoais): permissões delegadas "Office 365 Exchange Online"
  `IMAP.AccessAsUser.All` + `SMTP.Send`, scope `offline_access` implícito.
  Possível banner "editor não verificado" no consentimento (não bloqueante).
- **Yahoo** (Developer Console > app OpenID Connect + Mail API, scopes
  `openid`, `mail-r`, `mail-w`): o acesso Mail API exige uma **aprovação
  prévia do Yahoo** (formulário de pedido + revisão manual) e a propagação
  da redirect URI pode demorar ~24 h.

### Funcionamento

- O utilizador adiciona a conta a partir do menu de contas ou *Definições >
  Contas*: entrada do endereço > (primeira vez: entrada das credenciais da
  app) > popup de consentimento > a conta aparece com um badge de
  fornecedor. *Definições > Contas* permite também a troca, a atualização
  das credenciais, a promoção a conta principal e a eliminação.
- Uma conta secundária pode ser **promovida a principal** (estrela em
  *Definições > Contas*) - a flag `secondary_only` do domínio só bloqueia o
  *login* primário, não esta promoção.
- O *refresh token* é armazenado cifrado (`accounts.imap_pass`); cada
  ligação IMAP/SMTP troca um access token efémero. O Microsoft e o Yahoo
  **reemitem um refresh token a cada uso**: é substituído automaticamente
  na base.
- O secret da app é cifrado (`oauth_apps.secret`, mesmo mecanismo que as
  palavras-passe) e nunca é devolvido à interface - "vazio = sem
  alteração" na edição.
- **Modificar ou eliminar uma app** quebra as contas associadas: o refresh
  token fica ligado ao client_id de origem - elimine e volte a adicionar a
  conta.
- Não há ManageSieve nestes fornecedores: os filtros Sieve não estão
  disponíveis nas contas OAuth.

Combinar `secondary_only` sobre o domínio `*` e domínios internos normais
reserva o login primário aos domínios listados deixando as secundárias
abertas em todo o lado - útil sem `restrict_domains`.
