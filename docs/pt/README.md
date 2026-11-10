# Documentação MXWebMail

**Idiomas** : [fr](../fr/README.md) · [de](../de/README.md) · [en](../en/README.md) · [es](../es/README.md) · [it](../it/README.md) · [nl](../nl/README.md) · [pl](../pl/README.md) · **pt** · [ru](../ru/README.md) · [tr](../tr/README.md)

*Traduzido do francês - a versão francesa é a que faz fé.*

Documentação destinada aos **administradores** que instalam, distribuem e
exploram o MXWebMail, bem como aos **desenvolvedores** que estendem o projeto
(plugins, temas, núcleo).

## Percurso aconselhado

- **Instalar**: [installation.md](installation.md)
- **Configurar**: [configuration.md](configuration.md)
- **Administrar diariamente**: [administration.md](administration.md)
- **Ligar os servidores de correio**: [domaines.md](domaines.md)
- **Resolver problemas**: [depannage.md](depannage.md)

## Índice

| Ficheiro | Conteúdo |
|---|---|
| [installation.md](installation.md) | Pré-requisitos, assistente `/setup/`, instalação manual, implantação Apache/Nginx, etapas pós-instalação. |
| [configuration.md](configuration.md) | Referência completa das chaves de `app/config/config.php` (valores predefinidos e função). |
| [administration.md](administration.md) | Guia do painel de administração: secções, utilizadores, registos, atualizações. |
| [domaines.md](domaines.md) | Configuração dos servidores IMAP/SMTP/Sieve por domínio, domínio `*`, autodetecção. |
| [plugins.md](plugins.md) | Os 30 plugins incluídos (função, configuração) + criação de um plugin (hooks, API, esquema). |
| [themes.md](themes.md) | Sistema de temas (`app/templates/`), herança, criação de um tema. |
| [localisation.md](localisation.md) | Idiomas disponíveis, adicionar um idioma, dicionários de plugins/temas. |
| [base-de-donnees.md](base-de-donnees.md) | Esquema, migrações, SQLite ↔ MariaDB, `app.key`, bases por utilizador. |
| [securite.md](securite.md) | Modelo de segurança e recomendações de endurecimento. |
| [fail2ban.md](fail2ban.md) | Integração fail2ban: registo de autenticação, filtro, jail, logrotate. |
| [developpement.md](developpement.md) | Arquitetura, carregador automático, hooks do núcleo. |
| [depannage.md](depannage.md) | Erros comuns, registos, FAQ. |

## Convenções

- Os caminhos são relativos à raiz do projeto.
- Os valores predefinidos citados correspondem a
  `app/config/config.example.php` e ao código (`app/src/MXWebMail/…`),
  versão `0.53.0`.
- Esta documentação descreve a **realidade do código**: se um ficheiro
  divergir, corrija-o.
- Traduções: cada idioma tem o seu diretório `docs/<lang>/`; `fr/` é a
  versão de referência.
