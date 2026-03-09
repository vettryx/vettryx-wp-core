# ⚙️ VETTRYX WP Core

> **Versão:** 1.1.0 | **Requisito Mínimo:** PHP 8.2+

O **VETTRYX WP Core** é o plugin central e fundacional para todos os sites desenvolvidos e gerenciados pela VETTRYX Tech. Ele atua como um framework corporativo, responsável por centralizar ferramentas, gerenciar atualizações automáticas (OTA) e garantir a conformidade legal (LGPD/GDPR) de todo o ecossistema de plugins da agência.

## 🚀 Principais Funcionalidades

* **Painel Centralizado:** Cria o menu "VETTRYX Tech" no painel do WordPress, permitindo a ativação e desativação granular de módulos contratados por cada cliente.
* **Atualizações Over-The-Air (OTA):** Integrado nativamente com o GitHub via *Plugin Update Checker (PUC v5)*. O Core detecta novas Releases neste repositório e notifica o painel do WordPress do cliente, permitindo atualizações em 1 clique sem exposição de tokens.
* **Conformidade com LGPD/GDPR:** Possui integração raiz com a *WP Consent API*, garantindo que os módulos injetores de rastreamento respeitem as leis de privacidade.
* **Arquitetura Modular:** Construído para ser leve. O Core carrega na memória apenas os submódulos que estão explicitamente ativados no banco de dados (`wp_options`).

## 🧩 Ecossistema de Módulos (Submódulos Git)

Este repositório é composto por submódulos independentes. Atualmente, o Core gerencia:

1. **[VETTRYX WP Site Signature](https://github.com/vettryx/vettryx-wp-site-signature):** Gerenciador inteligente de copyright e assinatura de desenvolvimento corporativo.
2. **[VETTRYX WP Tracking Manager](https://github.com/vettryx/vettryx-wp-tracking-manager):** Injetor nativo e blindado de IDs de rastreamento (GTM, GA4, Meta Pixel) para campanhas de marketing.

## 🛠️ Deploy e CI/CD (Para Desenvolvedores)

A geração do arquivo de instalação para os clientes é **100% automatizada** via GitHub Actions.

**Nunca baixe o código-fonte direto da branch `main` para instalar no cliente.** A branch principal não contém a pasta `vendor` compilada.

Para instalar em um novo cliente:

1. Acesse a aba **Releases** deste repositório no GitHub.
2. Baixe o arquivo compilado `vettryx-wp-core.zip` da versão mais recente.
3. Instale normalmente via painel do WordPress.

### Como adicionar um novo módulo ao Core

Para acoplar uma nova ferramenta da agência ao ecossistema, utilize o terminal na raiz deste repositório:

git submodule add <https://github.com/vettryx/nome-do-novo-repositorio.git> modules/nome-da-pasta
git add .
git commit -m "feat: adiciona novo modulo"
git push

---

**VETTRYX Tech**
*Transformando ideias em experiências digitais.*
