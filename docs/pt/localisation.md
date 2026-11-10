# Tradução / idiomas

*Traduzido do francês - a versão francesa é a que faz fé.*

## 1. Funcionamento

A localização assenta em dicionários PHP `chave => texto`:

- núcleo: `app/src/MXWebMail/Locale/<lang>.php`;
- plugins: `app/plugins/<name>/langs/<lang>.php` (chaves com prefixo
  `plugin.<name>.`);
- temas: `app/templates/<name>/langs/<lang>.php` (chaves com prefixo
  `theme.<name>.`).

O tradutor funde estas fontes (primeiro o núcleo, depois os plugins, depois
os temas - estes últimos vencem em caso de colisão), com o inglês (`en`) como
**idioma de base** (fallback) seguido do idioma ativo. `t('chave', $params)`
efetua a interpolação `{param}`; uma chave desconhecida é devolvida tal
qual.

## 2. Idiomas instalados

Estão disponíveis 10 idiomas: **de, en, es, fr, it, nl, pl, pt, ru, tr**. O
idioma de base (fallback) é o **inglês**: `app/src/MXWebMail/Locale/en.php`
deve permanecer exaustivo.

## 3. Escolha do idioma

`language` no `config.php` (ou admin > *Geral*):

- `auto` (predefinição): cabeçalho `Accept-Language` do navegador;
- senão o código de um idioma instalado (`fr`, `en`…).

Fallback final: `en`. O frontend recebe o dicionário completo em
`window.MXWM.i18n` (usado via `i18n.js`).

## 4. Adicionar um idioma

1. Crie `app/src/MXWebMail/Locale/<code>.php`:
   `return ['chave' => 'texto', …]`.
2. (Recomendado) adicione os dicionários de plugins e temas:
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. O idioma aparece automaticamente no seletor do admin (lista = ficheiros
   presentes em `Locale/`).

Nenhum registo central a alterar: basta a presença do ficheiro.

## 5. Lado PHP e JS

- PHP: `$this->t('chave')` nos controladores/plugins; `t('chave')` nos
  modelos.
- JS: `ctx.t('chave')` em `mount(element, ctx)` dos plugins; `t('chave')` via
  `i18n.js`.

Os `label`/`help`/`description` dos manifestos (`plugin.json`, `theme.json`)
e dos esquemas (`config.schema.json`) aceitam chaves de tradução
(pass-through se não for uma chave conhecida).

## 6. Paridade dos dicionários

O inglês é o idioma de base: toda nova chave deve ser primeiro adicionada em
`en.php`, e cada dicionário deve permanecer em paridade com `en`.

## 7. Documentação traduzida

Cada idioma do webmail tem o seu diretório `docs/<lang>/` (`fr`, `de`, `en`,
`es`, `it`, `nl`, `pl`, `pt`, `ru`, `tr`) com os mesmos nomes de ficheiro. O
francês em `docs/fr/` é a versão de referência: qualquer modificação de um
documento canónico deve ser repercutida nas traduções, e qualquer novo artigo
deve ser criado em cada idioma.
