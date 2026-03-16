# ⚙️ VETTRYX WP Core

O **VETTRYX WP Core** é o plugin central e fundacional para todos os sites desenvolvidos e gerenciados pela VETTRYX Tech. Ele atua como um framework corporativo, responsável por centralizar ferramentas, gerenciar atualizações automáticas (OTA) e garantir a conformidade legal (LGPD/GDPR) de todo o ecossistema de plugins da agência.

## 🚀 Principais Funcionalidades

* **Painel Centralizado:** Cria o menu "VETTRYX Tech" no painel do WordPress, permitindo a ativação e desativação granular de módulos contratados por cada cliente.
* **Leitura Dinâmica:** O Core faz varredura automática na pasta de módulos e lê os cabeçalhos nativos, dispensando registro manual (hardcode) de novas ferramentas.
* **Atualizações Over-The-Air (OTA):** Integrado nativamente com o GitHub via *Plugin Update Checker (PUC v5)*. O Core detecta novas Releases neste repositório e notifica o painel do WordPress do cliente, permitindo atualizações em 1 clique.
* **Conformidade com LGPD/GDPR:** Possui integração raiz com a *WP Consent API*, garantindo que os módulos injetores de rastreamento respeitem as leis de privacidade.
* **Arquitetura Modular:** Construído para ser leve. O Core carrega na memória apenas os submódulos que estão explicitamente ativados no banco de dados (`wp_options`).

## 🧩 Ecossistema de Módulos (Submódulos Git)

Este repositório é composto por submódulos independentes. Atualmente, o Core gerencia:

1. **[VETTRYX WP Cookie Manager](https://github.com/vettryx/vettryx-wp-cookie-manager):** Gerenciador de consentimento LGPD nativo e 100% personalizável.
2. **[VETTRYX WP Fast Gallery](https://github.com/vettryx/vettryx-wp-fast-gallery):** Gerenciador simplificado de álbuns de serviços com fotos de Antes e Depois.
3. **[VETTRYX WP Site Signature](https://github.com/vettryx/vettryx-wp-site-signature):** Personalização white-label do painel administrativo e assinatura corporativa.
4. **[VETTRYX WP Tracking Manager](https://github.com/vettryx/vettryx-wp-tracking-manager):** Injeção otimizada de scripts (GA4, Meta Pixel) com bloqueio nativo pré-consentimento.
5. **[VETTRYX WP WhatsApp Widget](https://github.com/vettryx/vettryx-wp-whatsapp):** Botão flutuante nativo e ultraleve, focado em conversão e performance.
6. **[VETTRYX WP Audit Log](https://github.com/vettryx/vettryx-wp-audit-log):** Monitoramento silencioso de segurança e registro de atividades (Auditoria).
7. **[VETTRYX WP Reports](https://github.com/vettryx/vettryx-wp-reports):** Geração nativa de relatórios mensais de manutenção e SLA em PDF.
8. **[VETTRYX WP Compliance](https://github.com/vettryx/vettryx-wp-compliance):** Central de LGPD e notificação oficial de incidentes de segurança (SLA 24h).

## 🛠️ Deploy e CI/CD (Para Desenvolvedores)

A geração do arquivo de instalação para os clientes é **100% automatizada** via GitHub Actions.

⚠️ **Nunca baixe o código-fonte direto da branch `main` para instalar no cliente.** A branch principal não contém a pasta `vendor` compilada.

### Para instalar em um novo cliente

1. Acesse a aba **Releases** deste repositório no GitHub.
2. Baixe o arquivo compilado `vettryx-wp-core.zip` da versão mais recente.
3. Instale normalmente via painel do WordPress.

### Clonando o repositório localmente

Como utilizamos submódulos, para baixar o Core e todos os módulos de uma vez, utilize:

    git clone --recurse-submodules https://github.com/vettryx/vettryx-wp-core.git

### Como adicionar um novo módulo ao Core

Para acoplar uma nova ferramenta da agência ao ecossistema, utilize o terminal na raiz deste repositório:

    git submodule add https://github.com/vettryx/nome-do-novo-repositorio.git modules/nome-da-pasta
    git add .
    git commit -m "feat: adiciona modulo nome-da-pasta"
    git push

## 📄 Licença e Propriedade

Este software é de **Propriedade Exclusiva da VETTRYX Tech** e seu uso é estritamente comercial, regido por contrato. Não é um software de código aberto (Open Source). É proibida a cópia, distribuição ou modificação sem autorização prévia.

---

**VETTRYX Tech**
*Transformando ideias em experiências digitais.*
