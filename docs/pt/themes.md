# Temas

*Traduzido do francês - a versão francesa é a que faz fé.*

Os temas (modelos de interface) vivem em `app/templates/<name>/`. Três são
fornecidos: `default`, `dark` e `midnight` (os dois últimos herdam de
`default`).

- **`default`** - interface clara/escura conforme o SO (`dark_mode: auto`),
  acento azul Bootstrap.
- **`dark`** - mesma estrutura que `default` forçada em escuro: barra em
  gradiente, pasta ativa tingida com borda lateral, densidade de lista
  reduzida.
- **`midnight`** - identidade "noite": acento teal (`#0d9488`/`#2dd4bf`),
  paleta navy tingida, raios suavizados, pastas ativas em pílulas, borda
  teal sob a barra superior, ícones finos (`regular`) e página de ligação em
  gradiente.

## 1. Estrutura de um tema

```
app/templates/<name>/
├── theme.json          # manifesto
├── config.schema.json  # (opcional) definições do tema
├── pages/              # vistas PHP (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # dicionários (chaves com prefixo theme.<name>.)
```

### `theme.json`

```json
{
	"name": "dark",
	"label": "Sombre",
	"version": "0.13.0",
	"extends": "default"
}
```

- `label` pode ser uma chave de tradução (pass-through caso contrário);
- `extends` define o tema pai (opcional).

### `config.schema.json`

Formato plano `{chave: {label, type, default, options?, help?}}` com tipos
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Exemplo
(`default`):

```json
{
	"dark_mode": { "type": "enum", "options": {"auto": "…", "light": "…", "dark": "…"}, "default": "auto" },
	"primary_color": { "type": "color", "default": "#0d6efd" },
	"link_color": { "type": "color", "default": "#0d6efd" },
	"header_color": { "type": "color", "default": "#0d6efd" },
	"folder_active_color": { "type": "color", "default": "#0d6efd" },
	"primary_color_dark": { "type": "color", "default": "" },
	"link_color_dark": { "type": "color", "default": "" },
	"header_color_dark": { "type": "color", "default": "" },
	"folder_active_color_dark": { "type": "color", "default": "" }
}
```

Os valores são armazenados na tabela `themes` (coluna `config`, JSON),
editados no admin e expostos via variáveis CSS `--mxwm-*`, um atributo
`<html data-dark-mode>` e um script inline (resolução de `auto` via
`prefers-color-scheme`).

As variáveis `--mxwm-*` das chaves `X_color` são emitidas sob `:root` - nome
em kebab-case (`folder_active_color` > `--mxwm-folder-active`), declinadas
em:
- `-rgb` (tripleto `r, g, b` para os `rgba()`);
- `-text`/`-text-rgb` (texto legível sobre esse fundo, contraste calculado
  no servidor);
- `-hover`/`-active`/`-active-border` (tons −15 %/−20 %/−25 %, equivalente
  PHP do `shade-color()` Sass do Bootstrap);
- `-active-rgb` (tom −20 % em tripleto rgb);
- `-tint` (+50 %, bordas de foco) e `-focus-rgb` (+15 %, halos de botões).

O CSS do modelo redeclara os componentes Bootstrap - compilados com cores
literais - para estas variáveis: botões `btn-primary`/`btn-outline-primary`,
caixas `form-check-input`, foco de `form-control`/`form-select`/`btn-close`,
hover de links e `link-primary`, `progress-bar` seguem `primary_color`; os
elementos "selecionados" (`.dropdown-item.active`, `.list-group-item.active`,
`.nav-pills`/`.pagination` ativos) seguem `folder_active_color`.

A cor clara aplica-se em ambos os modos: cada chave `X_color_dark` reemite a
sua variante (tons incluídos) sob `[data-bs-theme=dark]` **apenas se houver
um valor definido** (guardado ou predefinido do campo) - senão, a cor clara
persiste em escuro. O seletor segue `data-bs-theme`, pelo que a variante se
aplica quer `dark_mode` seja `auto` quer `dark`.
Um campo `color` deixado vazio recai na predefinição do campo; para uma
chave `*_dark` sem predefinição, vazio = mesma cor que no modo claro.

## 2. Herança

A cadeia de resolução é `name > extends > … > default` (implícita se
ausente). `default` é sempre o último recurso. Anti-ciclo e profundidade
máxima 8.

- Cada ficheiro (página, asset, esquema) resolve-se no **primeiro
  existente** da cadeia.
- `theme.json` e `langs/` **não** são herdados (cada tema tem os seus).
- Vistas renderizadas pela aplicação: `app`, `login`, `admin`,
  `admin-login`, `maintenance` (todas fornecidas por `default`, portanto
  herdáveis).
- Uma vista personalizada deve escapar `<?= $pluginHead ?>` no seu `<head>`
  (depois de `$themeScript`) para que as injeções `app.head` dos plugins
  funcionem lá - cf. `default/pages/app.php`. Sem ele, o HTML dos plugins à
  escuta de `app.head` é recolhido e depois silenciosamente ignorado nessa
  página.

Exemplo: `dark` e `midnight` fornecem apenas `assets/app.css` e o seu
`theme.json`/esquema; todo o resto (páginas, dicionários) é retomado de
`default`.

## 3. Criar um tema

1. Crie `app/templates/<name>/theme.json` (eventualmente com `extends`).
2. Adicione as vistas em falta em `pages/` (senão herdadas) e
   `assets/app.css`.
3. Adicione `config.schema.json` para as definições e `langs/<lang>.php`
   para os rótulos (chaves `theme.<name>.`; `label`/`help`/opções do esquema
   aceitam estas chaves).

## 4. Administração dos temas

No admin (*Temas*):

- definir o **tema predefinido** (escreve `user_defaults.theme`);
- **autorizar** um tema para os utilizadores (`allowed`) - o tema
  predefinido permanece sempre selecionável;
- **configurar** cada tema (modal gerado a partir de `config.schema.json`).

O utilizador escolhe o seu tema via a definição `theme`; o CSS é aplicado a
quente.
