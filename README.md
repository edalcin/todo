# Todo App

Um sistema de gerenciamento de tarefas (Todo/Kanban) simples, eficiente e auto-hospedado, desenvolvido em PHP com SQLite.

## ✨ Funcionalidades

- **Múltiplos Quadros:** Organize suas tarefas em diferentes contextos ou projetos.
- **Sistema Kanban:** Arraste e solte tarefas entre colunas customizáveis.
- **Tags e Cores:** Categorize tarefas com tags coloridas e globais ou específicas por projeto.
- **Segurança Simples:** Acesso protegido por PIN configurável.
- **Backup e Restauração:** Ferramentas integradas para baixar e restaurar o banco de dados SQLite.
- **PWA (Progressive Web App):** Instale como um aplicativo no seu celular ou desktop para acesso rápido e offline (cache de assets).
- **Interface Responsiva:** Design moderno que se adapta a diferentes tamanhos de tela.

## 🚀 Como Instalar

### Requisitos

- Servidor Web (Apache, Nginx, etc.)
- PHP 7.4 ou superior
- Extensão `php-sqlite3` habilitada

### Passos para Instalação

1.  **Clone o repositório:**
    ```bash
    git clone https://github.com/edalcin/todo.git
    cd todo
    ```

2.  **Configure o ambiente:**
    - Crie um arquivo `.env` a partir do modelo:
      ```bash
      cp .env.example .env
      ```
    - Abra o `.env` e altere o `PIN_CODE` para a sua senha de preferência:
      ```text
      PIN_CODE=SeuPinAqui
      ```

3.  **Permissões de Escrita:**
    Certifique-se de que o servidor web tenha permissão de escrita na pasta do projeto para criar e atualizar o arquivo `todo.sqlite` e os logs. O arquivo `.env` também deve ser legível pelo servidor.

4.  **Acesse no Navegador:**
    Aponte seu servidor para a pasta do projeto e acesse via `http://localhost/todo` (ou o endereço configurado).

## 🛠️ Tecnologias Utilizadas

- **Backend:** PHP (Single-file API)
- **Banco de Dados:** SQLite (PDO)
- **Frontend:** Vanilla JavaScript, CSS Moderno (Variables, Flexbox, Grid)
- **PWA:** Service Workers e Web App Manifest

## 🔒 Segurança

- O arquivo `.env` contém seu PIN e **não deve ser commitado** (já incluído no `.gitignore`).
- O arquivo `config.php` agora é seguro para ser commitado, pois carrega as informações do ambiente.
- O banco de dados `todo.sqlite` também está no `.gitignore` por padrão.

## 📝 Licença

Este projeto está licenciado sob a [GNU GPLv3](LICENSE).
