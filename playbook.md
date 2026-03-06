# Playbook VETTRYX: Fluxo de Engenharia de Software (CI/CD + Kanban)

Este documento mapeia o fluxo profissional de ponta a ponta adotado pela VETTRYX Tech para o desenvolvimento e lançamento de novos módulos e ferramentas no ecossistema WordPress. O objetivo deste fluxo é garantir previsibilidade, documentação de escopo e automação de entregas.

---

## 🏗️ Fase 1: O Quadro de Batalha (GitHub Projects)

Antes de qualquer código, visualizamos o fluxo de trabalho.

1. Acesse o seu repositório no GitHub.
2. Clique na aba **Projects** e depois em **Link a project** > **New project**.
3. Escolha o template **Board** (Kanban clássico).
4. Configure as colunas essenciais:
   * **Backlog:** Ideias e módulos futuros (A gaveta de ideias).
   * **To Do:** O que está planejado para o ciclo atual (Pronto para iniciar).
   * **In Progress:** Onde você está com a "mão na massa" no VS Code.
   * **In Review/Testing:** Código feito, mas em fase de testes no ambiente local.
   * **Done:** Validado e disparado para produção (Release gerada).

---

## 📝 Fase 2: O Contrato do Módulo (GitHub Issues)

Nunca se começa a programar sem um escopo definido. A Issue atua como o documento de requisitos do que será construído.

1. Vá na aba **Issues** do repositório e clique em **New issue**.
2. No título, seja claro: `[Módulo] Criação do Sistema de Assinatura` ou `[Feature] Painel de Configurações`.
3. Estruture a descrição com os seguintes pontos:
   * **Objetivo:** Descreva em 1 ou 2 parágrafos o que esse módulo/ferramenta vai resolver para o cliente final.
   * **Requisitos de Aceite (To-Do List):** Listar os passos técnicos (`- [ ] criar pasta`, `- [ ] registrar cabeçalho`, etc.).
4. Na barra lateral direita, adicione um **Label** (ex: `new-module`) e vincule a Issue ao **Project** criado na Fase 1. A Issue aparecerá automaticamente na coluna *To Do*.

---

## 💻 Fase 3: A Mão na Massa (Desenvolvimento Local)

Com o escopo documentado, é hora de ir para o código.

1. No Kanban (Projects), arraste o card da Issue de *To Do* para **In Progress**.
2. Abra o VS Code e crie o código do módulo dentro do ambiente local.
3. Marque os itens concluídos na sua Issue no GitHub conforme for avançando.
4. Quando tudo estiver funcionando no ambiente de testes, arraste o card no Kanban para **In Review/Testing** e faça a validação final.

---

## 🚀 Fase 4: O Fechamento Mágico (Commit e Push)

Aqui a automação conecta o código com a documentação usando palavras-chave no terminal do VS Code.

1. Suponha que a Issue que você criou recebeu o número `#3` do GitHub.
2. No terminal do seu VS Code, rode os comandos de envio usando a palavra `Closes` seguida do número da Issue no commit:
   `git commit -m "feat: finaliza o modulo de assinatura. Closes #3"`
3. Ao fazer o push, o GitHub vai ler a mensagem, fechar a Issue e mover o card para *Done* automaticamente.

---

## ⚙️ Fase 5: A Fábrica Trabalhando (Integração Contínua)

O código está no GitHub e a Issue foi fechada automaticamente. Agora, empacotamos o produto.

1. Vá na aba **Actions** do repositório.
2. Selecione o workflow **Build and Release Core**.
3. Clique em **Run workflow** para acionar a esteira.
4. Aguarde a finalização. O robô irá empacotar a pasta limpa em `.zip`, ler a versão no arquivo `.php` principal e criar uma Release oficial pronta para o atualizador do WordPress consumir.
