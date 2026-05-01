<?php
try {
    require_once 'config.php';
} catch (Exception $e) {
    error_log("Erro crítico ao carregar config.php: " . $e->getMessage());
    die("Erro de configuração do sistema. Verifique os logs para mais detalhes.");
}

// Verificar Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$isLoggedIn = isset($_SESSION['auth']) && $_SESSION['auth'] === true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Todo">
    <link rel="apple-touch-icon" href="favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { gray: { 750: '#2d3748', 850: '#1a202c' } } } }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php if ($isLoggedIn): ?>
    <!-- Libs apenas para usuário logado -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <style>
        .kanban-col { min-height: 100px; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .ghost-card { background-color: #cbd5e0; opacity: 0.5; border: 2px dashed #4a5568; }
        .dark .ghost-card { background-color: #4a5568; border-color: #a0aec0; }
        .ghost-board { background-color: #e2e8f0; opacity: 0.8; }
        .dark .ghost-board { background-color: #2d3748; }

        /* Mobile Column Navigation */
        .mobile-col-nav { display: none; }
        .mobile-col-nav button.active {
            background-color: #2563eb;
            color: white;
        }
        .dark .mobile-col-nav button.active {
            background-color: #3b82f6;
        }

        /* Move-to dropdown */
        .move-to-menu {
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 50;
            min-width: 180px;
        }

        /* Mobile styles */
        @media (max-width: 768px) {
            /* Column navigation tabs on mobile */
            .mobile-col-nav { display: flex; overflow-x: auto; gap: 4px; padding: 8px 16px; background: inherit; border-bottom: 1px solid rgba(128,128,128,0.2); }
            .mobile-col-nav button { flex-shrink: 0; padding: 6px 14px; border-radius: 9999px; font-size: 13px; font-weight: 600; white-space: nowrap; border: 1px solid rgba(128,128,128,0.3); transition: all 0.2s; }

            /* Full-width columns on mobile */
            #boardColumns { flex-direction: column !important; gap: 0 !important; overflow-x: hidden !important; overflow-y: auto !important; padding: 8px !important; }
            #boardColumns > div { width: 100% !important; flex-shrink: 1 !important; max-height: none !important; }
            #boardColumns > div.mobile-hidden { display: none !important; }

            /* Larger touch targets */
            .kanban-col > div { padding: 4px 0; }

            /* Card actions for mobile */
            .card-mobile-actions { display: flex !important; }

            /* Full-screen modals on mobile */
            #cardModal > div, #columnModal > div, #tagFilterModal > div, #priorityFilterModal > div, #idFilterModal > div, #trashModal > div, #archivedModal > div, #sortOptionsModal > div {
                max-width: 100% !important;
                width: 100% !important;
                min-height: 100vh;
                border-radius: 0 !important;
                margin: 0;
            }
            #cardModal, #columnModal, #tagFilterModal, #priorityFilterModal, #idFilterModal, #trashModal, #archivedModal, #sortOptionsModal {
                align-items: stretch !important;
                padding: 0 !important;
            }

            /* Main area */
            main.flex-1 { padding: 0 !important; }
        }

        /* Desktop: hide mobile-only elements */
        @media (min-width: 769px) {
            .card-mobile-actions { display: none !important; }
        }
    </style>
    <?php endif; ?>
</head>
<body class="bg-gray-100 text-gray-800 transition-colors duration-300 dark:bg-gray-900 dark:text-gray-100 h-screen flex flex-col overflow-hidden">

<?php if (!$isLoggedIn): ?>
    <!-- TELA DE LOGIN (PIN) -->
    <div class="flex flex-col items-center justify-center h-full w-full bg-gray-100 dark:bg-gray-900">
        <div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-xl max-w-sm w-full text-center border dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-2 text-blue-600">Todo</h1>
            <p class="text-gray-500 dark:text-gray-400 mb-6">Acesso Restrito</p>
            
            <?php if(!class_exists('PDO')): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm">
                    ⚠️ Extensão PDO não detectada no PHP.
                </div>
            <?php endif; ?>

            <form id="loginForm" onsubmit="return handleLogin(event)">
                <input type="password" id="pinInput" placeholder="Digite o PIN" 
                       class="w-full text-center text-2xl tracking-widest p-3 border rounded mb-4 dark:bg-gray-700 dark:border-gray-600 outline-none focus:ring-2 focus:ring-blue-500" 
                       maxlength="4" autofocus required>
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded font-bold hover:bg-blue-700 transition">ENTRAR</button>
            </form>
            <p id="errorMsg" class="text-red-500 mt-4 hidden"></p>
        </div>
    </div>

    <script>
        async function handleLogin(e, pinValue = null) {
            if (e) e.preventDefault();
            const pin = pinValue || document.getElementById('pinInput').value;
            const el = document.getElementById('errorMsg');
            
            try {
                const res = await fetch('api.php?action=login', {
                    method: 'POST',
                    body: JSON.stringify({ pin }),
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    localStorage.setItem('savedPin', pin);
                    window.location.href = 'index.php'; // Usa href para limpar query params como ?logout=true
                } else {
                    el.innerText = "PIN Incorreto.";
                    el.classList.remove('hidden');
                    document.getElementById('pinInput').value = '';
                    localStorage.removeItem('savedPin');
                }
            } catch (err) {
                console.error(err);
                if (!pinValue) alert('Erro de conexão');
            }
        }
        
        // Auto-login se houver PIN salvo e não estivermos saindo
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('logout')) {
                localStorage.removeItem('savedPin');
                return;
            }

            const savedPin = localStorage.getItem('savedPin');
            if (savedPin) {
                // Preenche o input visualmente para feedback
                document.getElementById('pinInput').value = savedPin;
                handleLogin(null, savedPin);
            }
        });
        
        // Dark mode init para login
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>

<?php else: ?>
    <!-- TELA KANBAN (Logado) -->
    <header class="bg-white dark:bg-gray-800 shadow p-2 sm:p-4 flex flex-wrap justify-between items-center z-10 gap-2 sm:gap-4">
        <div class="flex flex-wrap items-center gap-2 sm:gap-4">
            <h1 class="text-xl sm:text-2xl font-bold text-blue-600 dark:text-blue-400">
                <i class="fa-solid fa-check-double mr-1 sm:mr-2"></i>Todo
            </h1>
            
            <div class="relative" id="boardDropdownContainer">
                <button onclick="toggleBoardDropdown()" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 dark:bg-gray-700 font-bold text-blue-600 dark:text-blue-400 hover:bg-gray-200 dark:hover:bg-gray-600 transition border border-gray-200 dark:border-gray-600">
                    <i class="fa-solid fa-folder-tree"></i>
                    <span id="currentBoardName">Todos</span>
                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </button>
                <div id="boardDropdown" class="absolute left-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-xl border dark:border-gray-700 hidden z-50 overflow-hidden">
                    <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex justify-between items-center">
                        Projetos
                        <span class="text-[9px] lowercase font-normal italic">Arraste para reordenar</span>
                    </div>
                    <ul id="boardList" class="py-1 text-sm max-h-[70vh] overflow-y-auto no-scrollbar">
                        <!-- Projetos serão inseridos aqui -->
                    </ul>
                </div>
            </div>
            
            <div class="flex items-center gap-1 ml-2">
                <button onclick="createNewBoard()" class="text-blue-600 hover:bg-blue-50 dark:hover:bg-gray-700 p-2 rounded" title="Novo Projeto">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <button onclick="editCurrentBoard()" id="editBoardBtn" class="text-gray-500 hover:text-blue-500 p-2" title="Editar nome"><i class="fa-solid fa-pen"></i></button>
                <button onclick="deleteCurrentBoard()" id="deleteBoardBtn" class="text-gray-500 hover:text-red-500 p-2" title="Excluir projeto"><i class="fa-solid fa-trash"></i></button>
                <button onclick="archiveCurrentBoard()" id="archiveBoardBtn" class="text-gray-500 hover:text-yellow-500 p-2" title="Arquivar projeto"><i class="fa-solid fa-box-archive"></i></button>
                <button onclick="openArchivedModal()" class="text-gray-500 hover:text-blue-500 p-2" title="Projetos Arquivados"><i class="fa-solid fa-box-open"></i></button>
            </div>

            <button onclick="openTrashModal()" class="text-gray-500 hover:text-green-600 relative" title="Lixeira">
                <i class="fa-solid fa-recycle"></i>
                <span id="trashCount" class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full hidden">0</span>
            </button>

            <!-- Colunas Dockadas -->
            <div class="relative" id="dockedColsContainer" style="display:none">
                <button onclick="toggleDockedDropdown()" class="flex items-center gap-2 px-3 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 font-medium text-yellow-600 dark:text-yellow-400">
                    <i class="fa-solid fa-thumbtack"></i> <span class="hidden sm:inline">Dockadas</span> <span id="dockedColsBadge" class="bg-yellow-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"></span> <i class="fa-solid fa-chevron-down text-xs"></i>
                </button>
                <div id="dockedColsDropdown" class="absolute left-0 mt-1 w-56 bg-white dark:bg-gray-800 rounded shadow-lg border dark:border-gray-700 hidden z-50">
                    <div class="px-3 py-2 text-xs font-bold text-gray-400 uppercase border-b dark:border-gray-700">Colunas Dockadas</div>
                    <ul id="dockedColsList" class="py-1 text-sm"></ul>
                </div>
            </div>

            <!-- Novo Menu Filtros -->
            <div class="relative" id="filtrosDropdownContainer">
                <button onclick="this.nextElementSibling.classList.toggle('hidden')" class="flex items-center gap-2 px-3 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 font-medium">
                    Filtros <i class="fa-solid fa-chevron-down text-xs"></i>
                </button>
                <div class="absolute left-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded shadow-lg border dark:border-gray-700 hidden z-50">
                    <ul class="py-1 text-sm">
                        <li><button onclick="openTagFilterModal()" class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700">por TAGs</button></li>
                        <li><button onclick="openPriorityFilterModal()" class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700">por Prioridade</button></li>
                        <li><button onclick="openIdFilterModal()" class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700">por ID</button></li>
                        <li class="border-t dark:border-gray-700"><button onclick="clearFilters()" class="w-full text-left px-4 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-gray-700">Limpar Filtros</button></li>
                    </ul>
                </div>
            </div>

            <!-- Menu Manutenção (Backup/Restore) -->
            <div class="relative" id="maintenanceDropdownContainer">
                <button onclick="this.nextElementSibling.classList.toggle('hidden')" class="flex items-center gap-2 px-3 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 font-medium text-gray-500">
                    <i class="fa-solid fa-gears"></i> <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </button>
                <div class="absolute left-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded shadow-lg border dark:border-gray-700 hidden z-50">
                    <ul class="py-1 text-sm">
                        <li>
                            <a href="api.php?action=download_backup" class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                <i class="fa-solid fa-download text-blue-500"></i> Backup (.sqlite)
                            </a>
                        </li>
                        <li>
                            <button onclick="triggerRestore()" class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                <i class="fa-solid fa-upload text-green-500"></i> Restaurar
                            </button>
                            <input type="file" id="restoreInput" accept=".sqlite" class="hidden" onchange="handleRestore(event)">
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button onclick="openTagManager()" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition" title="Gerenciar Tags"><i class="fa-solid fa-tags"></i></button>
            <button onclick="toggleTheme()" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition"><i id="themeIcon" class="fa-solid fa-moon"></i></button>
            <a href="?logout=true" onclick="localStorage.removeItem('savedPin')" class="text-sm font-semibold text-red-500 hover:text-red-600 ml-2">SAIR</a>
        </div>
    </header>

    <!-- Mobile column navigation tabs -->
    <div class="mobile-col-nav bg-gray-100 dark:bg-gray-900 no-scrollbar" id="mobileColNav"></div>

    <main class="flex-1 overflow-x-auto overflow-y-hidden p-4 sm:p-6">
        <div class="h-full flex items-start gap-4 sm:gap-6" id="boardColumns"></div>
    </main>
    
    <div class="fixed bottom-6 right-6">
        <button onclick="openAddColumnModal()" class="bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-full shadow-lg transition transform hover:scale-105">
            <i class="fa-solid fa-plus text-xl"></i>
        </button>
    </div>

    <!-- Modais -->
    <div id="cardModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl p-4 sm:p-6 max-h-[95vh] flex flex-col overflow-hidden">
            <h2 id="cardModalTitle" class="text-xl font-bold mb-4">Novo Cartão</h2>
            <div class="flex-1 overflow-y-auto pr-1">
                <input type="hidden" id="cardId"><input type="hidden" id="cardColumnId">
                <div class="mb-4"><label class="block text-sm font-medium mb-1">Título</label><input type="text" id="cardTitleInput" class="w-full p-2 border rounded dark:bg-gray-700 dark:border-gray-600 outline-none"></div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Prioridade</label>
                    <div class="flex flex-wrap gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="cardPriority" value="" class="hidden peer" checked>
                            <span class="block px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm text-gray-500 peer-checked:bg-gray-200 dark:peer-checked:bg-gray-700 peer-checked:font-bold">Nenhuma</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="cardPriority" value="1" class="hidden peer">
                            <span class="block px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm text-amber-600 dark:text-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-900/20 peer-checked:border-amber-500 peer-checked:font-bold">1</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="cardPriority" value="2" class="hidden peer">
                            <span class="block px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm text-amber-600 dark:text-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-900/20 peer-checked:border-amber-500 peer-checked:font-bold">2</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="cardPriority" value="3" class="hidden peer">
                            <span class="block px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm text-amber-600 dark:text-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-900/20 peer-checked:border-amber-500 peer-checked:font-bold">3</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4"><label class="block text-sm font-medium mb-1">Detalhes</label><textarea id="cardDescInput" rows="6" class="w-full p-2 border rounded dark:bg-gray-700 dark:border-gray-600 outline-none"></textarea></div>
                <div class="mb-4"><label class="block text-sm font-medium mb-1">Links (um por linha)</label><textarea id="cardLinksInput" rows="2" class="w-full p-2 border rounded dark:bg-gray-700 dark:border-gray-600 outline-none" placeholder="https://..."></textarea></div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-1">Tags</label>
                    <div id="tag-editor-container" class="w-full border rounded dark:bg-gray-700 dark:border-gray-600 flex flex-wrap gap-2 items-center focus-within:ring-2 focus-within:ring-blue-500 p-2" onclick="document.getElementById('tag-entry-input') && document.getElementById('tag-entry-input').focus()">
                        <!-- Visual tags rendered here -->
                    </div>
                    <input type="hidden" id="cardTagsInput">
                    <div id="availableTags" class="flex flex-wrap gap-2 mt-2 min-h-[24px]"></div>
                </div>
            </div>
            <div class="flex flex-wrap justify-end gap-2 pt-4 border-t dark:border-gray-700" id="cardModalFooter">
                <button id="btnCopyDesc" onclick="copyDescription()" class="mr-auto px-4 py-2 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded flex items-center gap-2" title="Copiar detalhes">
                    <i class="fa-solid fa-copy"></i> Copiar
                </button>
                <div class="flex gap-2">
                    <button onclick="closeModal('cardModal')" class="px-4 py-2 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button onclick="saveCard()" class="px-4 py-2 bg-blue-600 text-white rounded">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="columnModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 max-h-[95vh] overflow-y-auto">
            <h2 id="columnModalTitle" class="text-xl font-bold mb-4">Nova Coluna</h2>
            <input type="hidden" id="columnId">
            <div class="mb-6"><label class="block text-sm font-medium mb-1">Título</label><input type="text" id="columnTitleInput" class="w-full p-2 border rounded dark:bg-gray-700 dark:border-gray-600 outline-none"></div>
            <div class="flex flex-wrap justify-between gap-2">
                <button id="btnDeleteColumn" onclick="deleteColumn()" class="px-4 py-2 text-red-500 hidden">Excluir</button>
                <div class="flex gap-2 ml-auto">
                    <button onclick="closeModal('columnModal')" class="px-4 py-2 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button onclick="saveColumn()" class="px-4 py-2 bg-blue-600 text-white rounded">Salvar</button>
                </div>
            </div>
        </div>
    </div>
    
    <div id="tagManagerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg p-6 flex flex-col max-h-[90vh]">
            <h2 class="text-xl font-bold mb-4">Gerenciar Tags</h2>
            <div id="tagManagerList" class="space-y-1 overflow-y-auto flex-1 pr-2">
                <!-- Lista de tags será renderizada aqui -->
            </div>
            <div class="flex justify-end mt-6">
                <button onclick="closeModal('tagManagerModal')" class="px-4 py-2 bg-blue-600 text-white rounded">Fechar</button>
            </div>
        </div>
    </div>
    
    <div id="tagFilterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 max-h-[90vh] flex flex-col">
            <h2 class="text-xl font-bold mb-4">Filtrar por Tags</h2>
            <div id="tagFilterList" class="overflow-y-auto flex-1 mb-4"></div>
            <div class="flex flex-wrap justify-between gap-2">
                <div class="flex gap-1">
                    <button onclick="selectAllTags(true)" class="px-2 sm:px-4 py-2 text-sm text-blue-500">Todas</button>
                    <button onclick="selectAllTags(false)" class="px-2 sm:px-4 py-2 text-sm text-blue-500">Nenhuma</button>
                </div>
                <div class="flex gap-2">
                    <button onclick="closeModal('tagFilterModal')" class="px-4 py-2 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button onclick="applyTagFilter()" class="px-4 py-2 bg-blue-600 text-white rounded">Aplicar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="priorityFilterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-sm p-6 max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold mb-4">Filtrar por Prioridade</h2>
            <div id="priorityFilterList" class="space-y-2 mb-6">
                <!-- Checkboxes injected via JS or static -->
                <div class="flex items-center">
                    <input id="filter-prio-1" type="checkbox" value="1" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                    <label for="filter-prio-1" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Prioridade 1</label>
                </div>
                <div class="flex items-center">
                    <input id="filter-prio-2" type="checkbox" value="2" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                    <label for="filter-prio-2" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Prioridade 2</label>
                </div>
                <div class="flex items-center">
                    <input id="filter-prio-3" type="checkbox" value="3" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                    <label for="filter-prio-3" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Prioridade 3</label>
                </div>
                <div class="flex items-center">
                    <input id="filter-prio-none" type="checkbox" value="none" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                    <label for="filter-prio-none" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300 italic">[Sem Prioridade]</label>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button onclick="closeModal('priorityFilterModal')" class="px-4 py-2 text-gray-600 dark:text-gray-300">Cancelar</button>
                <button onclick="applyPriorityFilter()" class="px-4 py-2 bg-blue-600 text-white rounded">Aplicar</button>
            </div>
        </div>
    </div>

    <div id="idFilterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-sm p-6 max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold mb-4">Filtrar por ID</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Digite um ID específico (ex: <code class="font-mono">3</code>) ou um intervalo (ex: <code class="font-mono">3-7</code>).</p>
            <input type="text" id="idFilterInput" placeholder="ex: 5 ou 3-7"
                class="w-full p-2 border rounded dark:bg-gray-700 dark:border-gray-600 outline-none font-mono mb-6"
                onkeydown="if(event.key==='Enter') applyIdFilter()">
            <div class="flex justify-end gap-2">
                <button onclick="closeModal('idFilterModal')" class="px-4 py-2 text-gray-600 dark:text-gray-300">Cancelar</button>
                <button onclick="applyIdFilter()" class="px-4 py-2 bg-blue-600 text-white rounded">Aplicar</button>
            </div>
        </div>
    </div>

    <div id="trashModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl p-6 flex flex-col max-h-[90vh]">
            <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                <h2 class="text-xl font-bold flex items-center gap-2"><i class="fa-solid fa-recycle"></i> Lixeira</h2>
                <button onclick="emptyTrash()" class="text-sm text-red-500 hover:text-red-700 hover:underline">Esvaziar Lixeira</button>
            </div>
            <div id="trashList" class="flex-1 overflow-y-auto space-y-2 p-2 bg-gray-50 dark:bg-gray-900 rounded border dark:border-gray-700 min-h-[200px]">
                <!-- Itens da lixeira -->
            </div>
            <div class="flex justify-end mt-4">
                <button onclick="closeModal('trashModal')" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Fechar</button>
            </div>
        </div>
    </div>

    <div id="archivedModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg p-6 flex flex-col max-h-[90vh]">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold flex items-center gap-2"><i class="fa-solid fa-box-archive"></i> Projetos Arquivados</h2>
            </div>
            <div id="archivedList" class="flex-1 overflow-y-auto space-y-2 p-2 bg-gray-50 dark:bg-gray-900 rounded border dark:border-gray-700 min-h-[100px]">
            </div>
            <div class="flex justify-end mt-4">
                <button onclick="closeModal('archivedModal')" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Fechar</button>
            </div>
        </div>
    </div>

    <div id="sortOptionsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-sm p-6">
            <h2 class="text-xl font-bold mb-4">Ordenar Coluna</h2>
            <div class="space-y-2">
                <button onclick="sortColumnBy('priority')" class="w-full text-left px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700 rounded border dark:border-gray-700 flex items-center gap-3">
                    <i class="fa-solid fa-arrow-down-1-9 text-blue-500"></i>
                    <span>Por Prioridade</span>
                </button>
                <button onclick="sortColumnBy('date_desc')" class="w-full text-left px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700 rounded border dark:border-gray-700 flex items-center gap-3">
                    <i class="fa-solid fa-calendar-arrow-down text-green-500"></i>
                    <span>Mais Recentes Primeiro</span>
                </button>
                <button onclick="sortColumnBy('date_asc')" class="w-full text-left px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700 rounded border dark:border-gray-700 flex items-center gap-3">
                    <i class="fa-solid fa-calendar-arrow-up text-orange-500"></i>
                    <span>Mais Antigos Primeiro</span>
                </button>
            </div>
            <div class="flex justify-end mt-6">
                <button onclick="closeModal('sortOptionsModal')" class="px-4 py-2 text-gray-600 dark:text-gray-300">Cancelar</button>
            </div>
        </div>
    </div>

    <script>
        let currentBoard = { id: null, name: '', columns: [], trash: [] };
        let boardsList = [];
        let systemTags = []; // Agora um array de objetos: {name: string, color: string | null}
        let activeTagFilters = []; // Tags selecionadas para filtro
        let activePriorityFilters = []; // Prioridades selecionadas para filtro: ['1', '2', '3', 'none']
        let activeIdFilter = null; // { from: number, to: number } ou null
        let dockedColumnIds = []; // Colunas dockadas (ocultas no kanban)
        let mobileActiveColId = null; // Coluna ativa no mobile
        let isUnifiedView = false;
        const API_URL = 'api.php';
        const isMobile = () => window.innerWidth <= 768;

        async function fetchAPI(action, method = 'GET', body = null) {
            let url = `${API_URL}?action=${action}`;
            const options = { method };

            if (method === 'GET' && body) {
                // Converte objeto body em query string para GET
                const params = new URLSearchParams(body).toString();
                url += `&${params}`;
            } else if (body) {
                // POST/PUT/DELETE envia body JSON
                options.body = JSON.stringify(body);
                options.headers = { 'Content-Type': 'application/json' };
            }

            try {
                const res = await fetch(url, options);
                if (res.status === 401) { window.location.reload(); return; }
                return await res.json();
            } catch (e) {
                console.error("API Error:", e);
                return null;
            }
        }

        let sortingColId = null;

        function sortColumn(colId) {
            sortingColId = colId;
            document.getElementById('sortOptionsModal').classList.remove('hidden');
        }

        function sortColumnBy(criteria) {
            if (!sortingColId) return;
            
            const col = currentBoard.columns.find(c => c.id === sortingColId);
            if (!col) return;

            if (criteria === 'priority') {
                col.cards.sort((a, b) => {
                    const pA = a.priority || '99'; 
                    const pB = b.priority || '99';
                    return pA.localeCompare(pB);
                });
            } else if (criteria === 'date_desc') {
                col.cards.sort((a, b) => {
                    const dA = a.createdAt || ''; 
                    const dB = b.createdAt || '';
                    return dB.localeCompare(dA); 
                });
            } else if (criteria === 'date_asc') {
                col.cards.sort((a, b) => {
                    const dA = a.createdAt || '';
                    const dB = b.createdAt || '';
                    return dA.localeCompare(dB); 
                });
            }

            saveBoardState().then(() => {
                renderBoard();
                closeModal('sortOptionsModal');
            });
        }

        document.addEventListener('DOMContentLoaded', async () => {
            initTheme();
            await loadBoardsList();
            
            // Determina qual board carregar inicialmente
            let customOrder = [];
            try {
                customOrder = JSON.parse(localStorage.getItem('boards_custom_order') || '[]');
            } catch(e) {}
            
            let initialId = 'todos';
            if (customOrder.length > 0) {
                initialId = customOrder[0];
            } else if (boardsList.length > 0) {
                // Se não houver ordem customizada, mas houver projetos, o primeiro projeto é o default
                // (O "Todos" ainda não está na boardsList aqui)
                // Mas por padrão, se não houver nada, 'todos' é o fallback seguro.
            }
            
            await loadBoard(initialId);
        });

        async function loadSystemTags(forManager = false) {
            const params = {};
            if (!forManager && currentBoard.id && currentBoard.id !== 'todos') {
                params.board_id = currentBoard.id;
            }
            const result = await fetchAPI('get_all_tags', 'GET', Object.keys(params).length ? params : null);
            systemTags = Array.isArray(result) ? result : [];
            if (systemTags.length > 0) {
                systemTags.sort((a, b) => a.name.localeCompare(b.name));
            }
        }

        // Cache de todas as tags (sem filtro) para o gerenciador
        let allSystemTags = [];

        // --- FILTROS ---
        function openTagFilterModal() {
            const listEl = document.getElementById('tagFilterList');
            listEl.innerHTML = '';

            // Add "Nenhuma" option
            const isNoneChecked = activeTagFilters.includes('--none--');
            const noneDiv = document.createElement('div');
            noneDiv.className = 'flex items-center';
            noneDiv.innerHTML = `<input id="filter-tag--none--" type="checkbox" ${isNoneChecked ? 'checked' : ''} value="--none--" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                             <label for="filter-tag--none--" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300 font-bold italic">[Nenhuma]</label>`;
            listEl.appendChild(noneDiv);

            // Em Todo, systemTags é array de objetos {name, color}
            // Precisamos apenas do nome para o filtro
            const tagNames = systemTags.map(t => t.name).sort();

            tagNames.forEach(tag => {
                const isChecked = activeTagFilters.includes(tag);
                const div = document.createElement('div');
                div.className = 'flex items-center';
                div.innerHTML = `<input id="filter-tag-${tag}" type="checkbox" ${isChecked ? 'checked' : ''} value="${tag}" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                 <label for="filter-tag-${tag}" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">${tag}</label>`;
                listEl.appendChild(div);
            });
            document.getElementById('tagFilterModal').classList.remove('hidden');
        }

        function openPriorityFilterModal() {
            // Reset checkboxes based on active state
            document.getElementById('filter-prio-1').checked = activePriorityFilters.includes('1');
            document.getElementById('filter-prio-2').checked = activePriorityFilters.includes('2');
            document.getElementById('filter-prio-3').checked = activePriorityFilters.includes('3');
            document.getElementById('filter-prio-none').checked = activePriorityFilters.includes('none');

            document.getElementById('priorityFilterModal').classList.remove('hidden');
        }

        function applyPriorityFilter() {
            activePriorityFilters = [];
            if(document.getElementById('filter-prio-1').checked) activePriorityFilters.push('1');
            if(document.getElementById('filter-prio-2').checked) activePriorityFilters.push('2');
            if(document.getElementById('filter-prio-3').checked) activePriorityFilters.push('3');
            if(document.getElementById('filter-prio-none').checked) activePriorityFilters.push('none');

            closeModal('priorityFilterModal');
            // Hide dropdown
            const dropdownContainer = document.getElementById('filtrosDropdownContainer');
            if (dropdownContainer) {
                dropdownContainer.querySelector('div').classList.add('hidden');
            }
            renderBoard();
        }

        function openIdFilterModal() {
            const input = document.getElementById('idFilterInput');
            if (activeIdFilter) {
                input.value = activeIdFilter.from === activeIdFilter.to
                    ? String(activeIdFilter.from)
                    : `${activeIdFilter.from}-${activeIdFilter.to}`;
            } else {
                input.value = '';
            }
            document.getElementById('idFilterModal').classList.remove('hidden');
            setTimeout(() => input.focus(), 50);
        }

        function applyIdFilter() {
            const raw = document.getElementById('idFilterInput').value.trim();
            if (!raw) {
                activeIdFilter = null;
            } else {
                const rangeMatch = raw.match(/^(\d+)\s*-\s*(\d+)$/);
                const singleMatch = raw.match(/^(\d+)$/);
                if (rangeMatch) {
                    activeIdFilter = { from: parseInt(rangeMatch[1]), to: parseInt(rangeMatch[2]) };
                } else if (singleMatch) {
                    const n = parseInt(singleMatch[1]);
                    activeIdFilter = { from: n, to: n };
                } else {
                    alert('Formato inválido. Use um número (ex: 5) ou intervalo (ex: 3-7).');
                    return;
                }
            }
            closeModal('idFilterModal');
            const dropdownContainer = document.getElementById('filtrosDropdownContainer');
            if (dropdownContainer) dropdownContainer.querySelector('div').classList.add('hidden');
            renderBoard();
        }

        function selectAllTags(shouldSelect) {
            const checkboxes = document.querySelectorAll('#tagFilterList input[type="checkbox"]');
            checkboxes.forEach(cb => {
                if (shouldSelect) { // "Todas" button clicked
                    cb.checked = true;
                    if (cb.value === '--none--') {
                        cb.checked = false; // "Nenhuma" should not be checked
                    }
                } else { // "Nenhuma" button clicked
                    cb.checked = false; // Uncheck all
                    if (cb.value === '--none--') {
                        cb.checked = true; // Only "Nenhuma" should be checked
                    }
                }
            });
        }

        function applyTagFilter() {
            const checkboxes = document.querySelectorAll('#tagFilterList input[type="checkbox"]:checked');
            activeTagFilters = Array.from(checkboxes).map(cb => cb.value);

            // Se "--none--" e outras tags estão selecionadas, isso significa "Todas" + "Nenhuma",
            // que deve resultar em mostrar todos os cartões.
            const noneFilterSelected = activeTagFilters.includes('--none--');
            if (noneFilterSelected && activeTagFilters.length > 1) {
                activeTagFilters = []; // Limpa o filtro para mostrar todos os cartões
            }

            closeModal('tagFilterModal');
            // Hide the main filter dropdown as well
            const dropdownContainer = document.getElementById('filtrosDropdownContainer');
            if (dropdownContainer) {
                dropdownContainer.querySelector('div').classList.add('hidden');
            }
            renderBoard(); // Re-renderiza para aplicar o filtro
        }

        function clearFilters() {
            activeTagFilters = [];
            activePriorityFilters = [];
            activeIdFilter = null;
            
            // Hide dropdown
            const dropdownContainer = document.getElementById('filtrosDropdownContainer');
            if (dropdownContainer) {
                dropdownContainer.querySelector('div').classList.add('hidden');
            }
            
            renderBoard();
        }

        // --- MOBILE HELPERS ---
        function renderMobileColNav() {
            const nav = document.getElementById('mobileColNav');
            if (!nav) return;
            nav.innerHTML = '';

            const cols = (currentBoard.columns || []).filter(c => !dockedColumnIds.includes(c.id));
            if (cols.length === 0) return;

            // Default to first column if none selected
            if (!mobileActiveColId || !cols.find(c => c.id === mobileActiveColId)) {
                mobileActiveColId = cols[0].id;
            }

            cols.forEach(col => {
                const cardCount = col.cards ? col.cards.length : 0;
                const btn = document.createElement('button');
                btn.className = `text-gray-600 dark:text-gray-300 bg-gray-200 dark:bg-gray-800 hover:bg-gray-300 dark:hover:bg-gray-700 ${col.id === mobileActiveColId ? 'active' : ''}`;
                btn.innerHTML = `${col.title} <span class="text-[10px] opacity-70">(${cardCount})</span>`;
                btn.onclick = () => {
                    mobileActiveColId = col.id;
                    applyMobileColumnVisibility();
                    // Update active state
                    nav.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                };
                nav.appendChild(btn);
            });
        }

        function applyMobileColumnVisibility() {
            if (!isMobile()) {
                // Desktop: show all columns
                document.querySelectorAll('#boardColumns > div').forEach(el => el.classList.remove('mobile-hidden'));
                return;
            }
            document.querySelectorAll('#boardColumns > div').forEach(el => {
                if (el.dataset.id === mobileActiveColId) {
                    el.classList.remove('mobile-hidden');
                } else {
                    el.classList.add('mobile-hidden');
                }
            });
        }

        // Re-check on resize
        window.addEventListener('resize', () => {
            applyMobileColumnVisibility();
        });

        async function moveCardToColumn(cardId, targetColId) {
            const now = new Date().toISOString();
            let movedCard = null;

            if (isUnifiedView) {
                const card = findCardById(cardId);
                if (!card) return;
                const targetCol = currentBoard.columns.find(c => c.id === targetColId);
                if (!targetCol) return;

                const res = await fetchAPI('move_card', 'POST', {
                    cardId: cardId,
                    fromBoardId: card.boardId,
                    toBoardId: card.boardId,
                    toColumnTitle: targetCol.title
                });

                if (res && res.success) {
                    await loadAllBoards();
                }
                return;
            }

            // Find and remove card from source column
            for (const col of currentBoard.columns) {
                const idx = col.cards.findIndex(c => c.id === cardId);
                if (idx !== -1) {
                    if (col.id === targetColId) return; // Already in target
                    movedCard = { ...col.cards[idx], updatedAt: now };
                    col.cards.splice(idx, 1);
                    break;
                }
            }

            if (!movedCard) return;

            // Add to target column (at the top)
            const targetCol = currentBoard.columns.find(c => c.id === targetColId);
            if (targetCol) {
                targetCol.cards.unshift(movedCard);
                saveBoardState().then(() => renderBoard());
            }
        }

        async function moveCardToBoard(cardId, targetBoardId, fromBoardId = null) {
            if (!confirm(`Mover este cartão para outro projeto?`)) return;
            
            const sourceId = fromBoardId || currentBoard.id;
            
            const res = await fetchAPI('move_card', 'POST', {
                cardId: cardId,
                fromBoardId: sourceId,
                toBoardId: targetBoardId
            });

            if (res && res.success) {
                if (isUnifiedView) {
                    await loadAllBoards();
                } else {
                    // Remove localmente para feedback imediato
                    for (const col of currentBoard.columns) {
                        const idx = col.cards.findIndex(c => c.id === cardId);
                        if (idx !== -1) {
                            col.cards.splice(idx, 1);
                            break;
                        }
                    }
                    renderBoard();
                }
            } else {
                alert("Erro ao mover cartão: " + (res?.error || "desconhecido"));
            }
        }

        function moveCardInColumn(cardId, direction) {
            for (const col of currentBoard.columns) {
                const idx = col.cards.findIndex(c => c.id === cardId);
                if (idx !== -1) {
                    const newIdx = idx + direction;
                    if (newIdx < 0 || newIdx >= col.cards.length) return;
                    // Swap
                    const now = new Date().toISOString();
                    [col.cards[idx], col.cards[newIdx]] = [col.cards[newIdx], col.cards[idx]];
                    col.cards[idx].updatedAt = now;
                    col.cards[newIdx].updatedAt = now;
                    saveBoardState().then(() => renderBoard());
                    return;
                }
            }
        }

        function openMoveToMenu(cardId, btnEl) {
            // Close any existing move-to menus
            document.querySelectorAll('.move-to-menu').forEach(m => m.remove());

            const card = findCardById(cardId);
            if (!card) return;

            const fromBoardId = isUnifiedView ? card.boardId : currentBoard.id;
            const currentCol = currentBoard.columns.find(col => col.cards.some(c => c.id === cardId));

            const menu = document.createElement('div');
            menu.className = 'move-to-menu bg-white dark:bg-gray-800 rounded-lg shadow-xl border dark:border-gray-700 py-1 max-h-[70vh] overflow-y-auto';

            // Section for Columns
            // In unified view, columns are aggregated by title.
            const colHeader = document.createElement('div');
            colHeader.className = 'px-3 py-2 text-[10px] font-bold text-gray-400 uppercase border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900';
            colHeader.textContent = 'Mover para Coluna';
            menu.appendChild(colHeader);

            currentBoard.columns.forEach(col => {
                const item = document.createElement('button');
                item.className = `w-full text-left px-4 py-3 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 ${col.id === currentCol?.id ? 'text-gray-400 cursor-default' : 'text-gray-700 dark:text-gray-200'}`;
                if (col.id === currentCol?.id) {
                    item.innerHTML = `<i class="fa-solid fa-check text-green-500"></i> ${col.title} <span class="text-[10px] text-gray-400">(atual)</span>`;
                } else {
                    item.innerHTML = `<i class="fa-solid fa-arrow-right text-blue-500"></i> ${col.title}`;
                    item.onclick = (e) => {
                        e.stopPropagation();
                        moveCardToColumn(cardId, col.id);
                        menu.remove();
                    };
                }
                menu.appendChild(item);
            });

            // Section for Projects
            if (boardsList.length > 0) {
                const boardHeader = document.createElement('div');
                boardHeader.className = 'px-3 py-2 text-[10px] font-bold text-gray-400 uppercase border-t border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900';
                boardHeader.textContent = 'Mover para Projeto';
                menu.appendChild(boardHeader);

                // Add "Todos" as a possible target? No, "Todos" is a view, not a storage board.
                
                boardsList.forEach(b => {
                    if (b.id === fromBoardId) {
                        const item = document.createElement('div');
                        item.className = 'w-full text-left px-4 py-3 text-sm flex items-center gap-2 text-gray-400 cursor-default';
                        item.innerHTML = `<i class="fa-solid fa-check text-green-500"></i> ${b.name} <span class="text-[10px] text-gray-400">(atual)</span>`;
                        menu.appendChild(item);
                        return;
                    }
                    const item = document.createElement('button');
                    item.className = `w-full text-left px-4 py-3 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 text-gray-700 dark:text-gray-200`;
                    item.innerHTML = `<i class="fa-solid fa-folder-tree text-amber-500"></i> ${b.name}`;
                    item.onclick = (e) => {
                        e.stopPropagation();
                        moveCardToBoard(cardId, b.id, fromBoardId);
                        menu.remove();
                    };
                    menu.appendChild(item);
                });
            }

            // Position relative to the button's card
            const cardEl = btnEl.closest('[data-id]');
            if (cardEl) {
                cardEl.style.position = 'relative';
                cardEl.appendChild(menu);
            } else {
                // If called from modal or somewhere else
                menu.style.position = 'fixed';
                const rect = btnEl.getBoundingClientRect();
                menu.style.top = rect.bottom + 'px';
                menu.style.left = rect.left + 'px';
                document.body.appendChild(menu);
            }

            // Close on outside click
            const closeHandler = (e) => {
                if (!menu.contains(e.target) && e.target !== btnEl) {
                    menu.remove();
                    document.removeEventListener('click', closeHandler);
                }
            };
            setTimeout(() => document.addEventListener('click', closeHandler), 10);
        }

        function findCardById(cardId) {
            for (const col of currentBoard.columns) {
                const card = col.cards.find(c => c.id === cardId);
                if (card) return card;
            }
            return null;
        }

        function initTheme() {
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
                document.getElementById('themeIcon').classList.replace('fa-moon', 'fa-sun');
            } else {
                document.documentElement.classList.remove('dark');
                document.getElementById('themeIcon').classList.replace('fa-sun', 'fa-moon');
            }
        }
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                document.getElementById('themeIcon').classList.replace('fa-sun', 'fa-moon');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                document.getElementById('themeIcon').classList.replace('fa-moon', 'fa-sun');
            }
            // Força re-render das cores das tags
            renderBoard();
            if(!document.getElementById('cardModal').classList.contains('hidden')) {
                renderTagSuggestions();
                renderTagEditor();
            }
             if(!document.getElementById('tagManagerModal').classList.contains('hidden')) {
                openTagManager();
            }
        }

        // --- DROPDOWN CONTROL ---
        function toggleBoardDropdown() {
            document.getElementById('boardDropdown').classList.toggle('hidden');
        }

        // Fecha dropdown ao clicar fora
        window.addEventListener('click', function(e) {
            const container = document.getElementById('boardDropdownContainer');
            const dropdown = document.getElementById('boardDropdown');
            if (container && !container.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        function triggerRestore() {
            document.getElementById('restoreInput').click();
            // Esconde o menu
            const dropdown = document.getElementById('maintenanceDropdownContainer').querySelector('div');
            if (dropdown) dropdown.classList.add('hidden');
        }

        async function handleRestore(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!confirm("Isso irá sobrescrever todo o banco de dados atual pelo arquivo de backup. Deseja continuar?")) {
                e.target.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('backup_file', file);

            try {
                const res = await fetch('api.php?action=restore_backup', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert("Banco de dados restaurado com sucesso! A página será reiniciada.");
                    window.location.reload();
                } else {
                    alert("Erro ao restaurar: " + (data.error || "Erro desconhecido"));
                }
            } catch (err) {
                console.error(err);
                alert("Erro de conexão ao tentar restaurar backup.");
            }
            e.target.value = '';
        }

        async function loadBoardsList() {
            boardsList = await fetchAPI('list_boards') || [];
            renderProjectTags();
        }

        function renderProjectTags() {
            const listEl = document.getElementById('boardList');
            const nameDisplay = document.getElementById('currentBoardName');
            if (!listEl || !nameDisplay) return;

            listEl.innerHTML = '';

            // Container para projetos ordenáveis (incluindo "Todos")
            const sortableContainer = document.createElement('div');
            sortableContainer.id = 'sortableBoards';
            listEl.appendChild(sortableContainer);

            // Adiciona "Todos" à lista se não estiver arquivado (simulado como ID 'todos')
            const allItems = [
                { id: 'todos', name: 'Todos os Projetos', isSpecial: true },
                ...boardsList
            ];

            // Recupera ordem do localStorage para incluir o "Todos" na ordenação customizada
            let customOrder = [];
            try {
                customOrder = JSON.parse(localStorage.getItem('boards_custom_order') || '[]');
            } catch(e) {}

            if (customOrder.length > 0) {
                allItems.sort((a, b) => {
                    let idxA = customOrder.indexOf(a.id);
                    let idxB = customOrder.indexOf(b.id);
                    if (idxA === -1) idxA = 999;
                    if (idxB === -1) idxB = 999;
                    return idxA - idxB;
                });
            }

            allItems.forEach(b => {
                const isActive = currentBoard.id === b.id;
                if (isActive) nameDisplay.innerText = b.id === 'todos' ? 'Todos' : b.name;

                const li = document.createElement('li');
                li.dataset.id = b.id;
                li.className = `px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer flex items-center justify-between group ${isActive ? 'font-bold text-blue-600 bg-blue-50/50 dark:bg-blue-900/20' : ''}`;
                
                const icon = b.id === 'todos' ? 'fa-layer-group text-blue-500' : 'fa-folder text-amber-500';
                
                li.innerHTML = `
                    <div class="flex items-center gap-2 truncate">
                        <i class="fa-solid ${icon} text-xs"></i>
                        <span class="truncate">${b.name}</span>
                    </div>
                    <i class="fa-solid fa-grip-vertical text-gray-300 dark:text-gray-600 opacity-0 group-hover:opacity-100 cursor-grab active:cursor-grabbing ml-2"></i>
                `;
                li.onclick = (e) => {
                    if (e.target.closest('.fa-grip-vertical')) return; 
                    selectBoard(b.id); 
                    toggleBoardDropdown(); 
                };
                sortableContainer.appendChild(li);
            });

            // Inicializa Sortable
            Sortable.create(sortableContainer, {
                animation: 150,
                handle: '.fa-grip-vertical',
                ghostClass: 'bg-blue-50',
                onEnd: async () => {
                    const ids = Array.from(sortableContainer.children).map(el => el.dataset.id);
                    localStorage.setItem('boards_custom_order', JSON.stringify(ids));
                    
                    // Separa os IDs reais para a API (removendo 'todos')
                    const realIds = ids.filter(id => id !== 'todos');
                    const res = await fetchAPI('reorder_boards', 'POST', { ids: realIds });
                    if (res && res.success) {
                        boardsList = realIds.map(id => boardsList.find(b => b.id === id));
                    }
                }
            });
        }

        function selectBoard(id) {
            console.log("Trocando para board:", id);
            loadBoard(id);
        }

        async function loadBoard(id) {
            try {
                // Sempre recarrega a lista de boards para garantir que está atualizada
                await loadBoardsList();

                if (id === 'todos') {
                    await loadAllBoards();
                    return;
                }

                // Se ID for nulo, e tivermos lista, tenta pegar o primeiro
                if (!id && boardsList.length > 0) {
                     id = boardsList[0].id;
                }

                const data = await fetchAPI('get_board', 'GET', id ? { id } : null);
                if (data && !data.error) {
                    isUnifiedView = false;
                    currentBoard = data;
                    if(!currentBoard.trash) currentBoard.trash = [];
                    if(!currentBoard.columns) currentBoard.columns = [];
                    
                    // Garante que cada coluna tenha um array de cards
                    currentBoard.columns.forEach(col => {
                        if (!col.cards) col.cards = [];
                    });

                    // Migração: atribuir uniqueId aos cards existentes sem ID
                    const allCards = [];
                    currentBoard.columns.forEach(col =>
                        col.cards.forEach(card => { if (!card.uniqueId) allCards.push(card); })
                    );
                    if (allCards.length > 0) {
                        let maxExisting = 0;
                        currentBoard.columns.forEach(col => col.cards.forEach(card => {
                            if (card.uniqueId && card.uniqueId > maxExisting) maxExisting = card.uniqueId;
                        }));
                        allCards.sort((a, b) => {
                            const dA = a.createdAt || '0';
                            const dB = b.createdAt || '0';
                            return dA.localeCompare(dB);
                        });
                        let counter = maxExisting + 1;
                        allCards.forEach(card => { card.uniqueId = counter++; });
                        await saveBoardState();
                    }

                    // Calcula nextCardId
                    let maxUniqueId = 0;
                    currentBoard.columns.forEach(col => col.cards.forEach(card => {
                        if (card.uniqueId && card.uniqueId > maxUniqueId) maxUniqueId = card.uniqueId;
                    }));
                    currentBoard.nextCardId = maxUniqueId + 1;

                    await loadSystemTags();
                    loadDockedColumns();
                    renderBoard();
                    updateTrashIcon();
                    renderProjectTags();
                    updateActionButtonsVisibility();
                } else {
                    console.error("Erro ao carregar board", id, data);
                }
            } catch (err) {
                console.error("Erro em loadBoard:", err);
            }
        }

        async function loadAllBoards() {
            try {
                // Garante que a lista de boards está atualizada
                if (boardsList.length === 0) await loadBoardsList();

                const boards = await fetchAPI('get_all_data');
                
                if (!boards || !Array.isArray(boards)) {
                    console.error("Erro ao carregar todos os projetos:", boards);
                    return;
                }

                isUnifiedView = true;
                currentBoard = {
                    id: 'todos',
                    name: 'Todos os Projetos',
                    columns: [],
                    trash: []
                };

                // Limpa filtros ao entrar na visão unificada
                activeTagFilters = [];
                activePriorityFilters = [];
                activeIdFilter = null;

                // Merge columns by title
                const columnsMap = new Map();
                boards.forEach(board => {
                    const cols = board.columns || [];
                    cols.forEach(col => {
                        const title = (col.title || 'Sem Título').trim();
                        if (!columnsMap.has(title)) {
                            columnsMap.set(title, {
                                id: 'unified-' + title.replace(/[^a-z0-9]/gi, '_').toLowerCase(),
                                title: title,
                                cards: []
                            });
                        }
                        const unifiedCol = columnsMap.get(title);
                        const cards = col.cards || [];
                        const cardsWithOrigin = cards.map(card => ({
                            ...card,
                            boardName: board.name,
                            boardId: board.id
                        }));
                        unifiedCol.cards.push(...cardsWithOrigin);
                    });
                });

                currentBoard.columns = Array.from(columnsMap.values());
                
                // Re-sort cards by date
                currentBoard.columns.forEach(col => {
                    col.cards.sort((a, b) => {
                        const dA = a.updatedAt || a.createdAt || '';
                        const dB = b.updatedAt || b.createdAt || '';
                        return dB.localeCompare(dA);
                    });
                });

                await loadSystemTags();
                loadDockedColumns();
                renderBoard();
                updateTrashIcon();
                renderProjectTags();
                updateActionButtonsVisibility();
            } catch (err) {
                console.error("Erro em loadAllBoards:", err);
            }
        }

        function updateActionButtonsVisibility() {
            const isTodos = currentBoard.id === 'todos';
            const buttons = ['editBoardBtn', 'deleteBoardBtn', 'archiveBoardBtn'];
            buttons.forEach(id => {
                const btn = document.getElementById(id);
                if (btn) btn.style.display = isTodos ? 'none' : 'flex';
            });
            
            // Botão de adicionar cartão também deve sumir em view unificada
            const addCardBtns = document.querySelectorAll('button[onclick^="openAddCardModal"]');
            addCardBtns.forEach(btn => btn.parentElement.style.display = isTodos ? 'none' : 'block');
            
            // Botão de adicionar coluna
            const addColBtn = document.querySelector('button[onclick="openAddColumnModal()"]');
            if (addColBtn) addColBtn.parentElement.style.display = isTodos ? 'none' : 'block';
        }

        async function saveBoardState() {
            if (isUnifiedView) return;
            await fetchAPI('save_board', 'POST', {
                id: currentBoard.id,
                data: { 
                    name: currentBoard.name, 
                    columns: currentBoard.columns,
                    trash: currentBoard.trash || []
                }
            });
            updateTrashIcon();
        }

        function updateTrashIcon() {
            const count = currentBoard.trash ? currentBoard.trash.length : 0;
            const badge = document.getElementById('trashCount');
            if(badge) {
                badge.innerText = count;
                if(count > 0) badge.classList.remove('hidden');
                else badge.classList.add('hidden');
            }
        }

        // --- DOCK / UNDOCK ---
        function getDockedKey() {
            return `dockedCols_${currentBoard.id}`;
        }

        function loadDockedColumns() {
            try {
                dockedColumnIds = JSON.parse(localStorage.getItem(getDockedKey()) || '[]');
            } catch(e) {
                dockedColumnIds = [];
            }
        }

        function saveDockedColumns() {
            localStorage.setItem(getDockedKey(), JSON.stringify(dockedColumnIds));
        }

        function dockColumn(colId) {
            if (!dockedColumnIds.includes(colId)) {
                dockedColumnIds.push(colId);
                saveDockedColumns();
                renderBoard();
            }
        }

        function undockColumn(colId) {
            dockedColumnIds = dockedColumnIds.filter(id => id !== colId);
            saveDockedColumns();
            renderBoard();
        }

        function toggleDockedDropdown() {
            document.getElementById('dockedColsDropdown').classList.toggle('hidden');
        }

        function renderDockedDropdown() {
            const container = document.getElementById('dockedColsContainer');
            const list = document.getElementById('dockedColsList');
            const badge = document.getElementById('dockedColsBadge');
            if (!container) return;

            // Remove IDs de colunas que não existem mais
            dockedColumnIds = dockedColumnIds.filter(id => currentBoard.columns.find(c => c.id === id));
            saveDockedColumns();

            if (dockedColumnIds.length === 0) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            badge.innerText = dockedColumnIds.length;
            list.innerHTML = '';

            dockedColumnIds.forEach(id => {
                const col = currentBoard.columns.find(c => c.id === id);
                const li = document.createElement('li');
                li.className = "flex items-center justify-between px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 group";
                li.innerHTML = `
                    <span class="truncate text-gray-700 dark:text-gray-200">${col.title}</span>
                    <button onclick="undockColumn('${id}')" class="text-gray-400 hover:text-green-500 p-1" title="Restaurar coluna">
                        <i class="fa-solid fa-rotate-left text-xs"></i>
                    </button>
                `;
                list.appendChild(li);
            });
        }

        // --- RENDER ---
        function renderBoard() {
            try {
                const container = document.getElementById('boardColumns');
                if (!container) return;
                container.innerHTML = '';

                if (!currentBoard || !currentBoard.columns || currentBoard.columns.length === 0) {
                    container.innerHTML = '<div class="flex flex-col items-center justify-center w-full h-full text-gray-500 italic"><i class="fa-solid fa-folder-open text-4xl mb-2"></i>Nenhum dado encontrado para exibir.</div>';
                    return;
                }

                let filteredColumns = JSON.parse(JSON.stringify(currentBoard.columns));

                // Apply filters
                if (activeTagFilters.length > 0 || activePriorityFilters.length > 0 || activeIdFilter) {
                    const noneTagFilterSelected = activeTagFilters.includes('--none--');

                    filteredColumns.forEach(col => {
                        if (!col.cards) col.cards = [];
                        col.cards = col.cards.filter(card => {
                            // 1. Tag Filter Logic
                            let matchesTags = true;
                            if (activeTagFilters.length > 0) {
                                if (noneTagFilterSelected) {
                                    matchesTags = !card.tags || card.tags.length === 0;
                                } else {
                                    matchesTags = Array.isArray(card.tags) && card.tags.some(tag => activeTagFilters.includes(tag));
                                }
                            }

                            // 2. Priority Filter Logic
                            let matchesPriority = true;
                            if (activePriorityFilters.length > 0) {
                                const effectivePrio = (!card.priority) ? 'none' : card.priority;
                                matchesPriority = activePriorityFilters.includes(effectivePrio);
                            }

                            // 3. ID Filter Logic
                            let matchesId = true;
                            if (activeIdFilter) {
                                matchesId = card.uniqueId >= activeIdFilter.from && card.uniqueId <= activeIdFilter.to;
                            }

                            return matchesTags && matchesPriority && matchesId;
                        });
                    });
                }

                filteredColumns.filter(col => !dockedColumnIds.includes(col.id)).forEach(col => container.appendChild(createColumnElement(col)));
                
                Sortable.create(container, {
                    animation: 150,
                    handle: '.column-drag-handle',
                    ghostClass: 'opacity-50',
                    disabled: isMobile(), // Disable column drag on mobile
                    onEnd: () => { updateModelFromDOM(); saveBoardState(); }
                });

                renderDockedDropdown();

                // Mobile: render column tabs and apply visibility
                renderMobileColNav();
                applyMobileColumnVisibility();
            } catch (err) {
                console.error("Erro em renderBoard:", err);
                const container = document.getElementById('boardColumns');
                if (container) container.innerHTML = '<div class="p-4 text-red-500 font-bold">Erro ao renderizar quadro. Verifique o console.</div>';
            }
        }

        function createColumnElement(colData) {
            const div = document.createElement('div');
            div.className = "w-[85vw] sm:w-96 flex-shrink-0 flex flex-col max-h-full bg-gray-200 dark:bg-gray-800 rounded-lg shadow-md border dark:border-gray-700";
            div.dataset.id = colData.id;
            div.innerHTML = `
                <div class="p-3 flex justify-between items-center border-b dark:border-gray-700 column-drag-handle cursor-move">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 truncate">${colData.title}</h3>
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-black/5 dark:bg-white/10 px-2 py-0.5 rounded-full flex-shrink-0">${(colData.cards || []).length}</span>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                        <button onclick="sortColumn('${colData.id}')" class="text-gray-400 hover:text-blue-500 p-1" title="Ordenar Coluna"><i class="fa-solid fa-arrow-down-short-wide"></i></button>
                        <button onclick="dockColumn('${colData.id}')" class="text-gray-400 hover:text-yellow-500 p-1" title="Dock Coluna"><i class="fa-solid fa-thumbtack text-xs"></i></button>
                        <button onclick="openEditColumnModal('${colData.id}')" class="text-gray-400 hover:text-gray-600 p-1"><i class="fa-solid fa-ellipsis"></i></button>
                    </div>
                </div>
                <div class="flex-1 p-2 overflow-y-auto no-scrollbar kanban-col space-y-3"></div>
                <div class="p-2 border-t dark:border-gray-700">
                    <button onclick="openAddCardModal('${colData.id}')" class="w-full py-2 flex items-center justify-center gap-2 text-gray-500 hover:bg-gray-300 dark:hover:bg-gray-700 rounded transition"><i class="fa-solid fa-plus"></i> Adicionar Cartão</button>
                </div>
            `;
            const listContainer = div.querySelector(`.kanban-col`);
            if (colData.cards && Array.isArray(colData.cards)) {
                colData.cards.forEach(card => {
                    const cardEl = createCardElement(card);
                    if (cardEl) listContainer.appendChild(cardEl);
                });
            }
            
            Sortable.create(listContainer, {
                group: 'shared-kanban',
                animation: 150,
                ghostClass: 'ghost-card',
                delay: isMobile() ? 300 : 0,
                delayOnTouchOnly: true,
                disabled: isMobile() || isUnifiedView, // Disable drag on mobile or unified view
                onEnd: () => { 
                    if (isUnifiedView) return;
                    const hasChanges = updateModelFromDOM(); 
                    saveBoardState(); 
                    if(hasChanges) renderBoard(); // Re-renderiza para mostrar nova data
                }
            });
            return div;
        }

        // --- COLOR GENERATOR ---
        
        function getTagHue(str) {
            let hash = 0x811c9dc5; for (let i = 0; i < (str || '').length; i++) { hash ^= str.charCodeAt(i); hash = Math.imul(hash, 0x01000193); }
            return Math.abs((hash * 137) % 360);
        }

        function getContrastYIQ(hexcolor){
            hexcolor = (hexcolor || "#ffffff").replace("#", "");
            const r = parseInt(hexcolor.substr(0,2),16);
            const g = parseInt(hexcolor.substr(2,2),16);
            const b = parseInt(hexcolor.substr(4,2),16);
            const yiq = ((r*299)+(g*587)+(b*114))/1000;
            return (yiq >= 128) ? 'black' : 'white';
        }

        function getTagStyles(tagName) {
            if (!tagName) return { light: {bg: '#eee', text: '#333'}, dark: {bg: '#333', text: '#eee'} };
            const tagData = systemTags.find(t => t.name.toLowerCase() === tagName.toLowerCase());
            
            // Se a tag existir e tiver uma cor customizada
            if (tagData && tagData.color) {
                const textColor = getContrastYIQ(tagData.color);
                return {
                    light: { bg: tagData.color, text: textColor },
                    dark:  { bg: tagData.color, text: textColor }
                };
            }

            // Fallback para o método antigo (hash do nome)
            const h = getTagHue(tagName);
            return {
                light: { bg: `hsl(${h}, 85%, 90%)`, text: `hsl(${h}, 80%, 25%)` },
                dark:  { bg: `hsl(${h}, 60%, 25%)`, text: `hsl(${h}, 80%, 90%)` }
            };
        }

        // Helper para aplicar estilos dark manualmente em elementos dinâmicos
        function applyDarkModeStyles(container) {
            if(document.documentElement.classList.contains('dark')) {
                container.querySelectorAll('[data-dark-bg]').forEach(s => {
                    s.style.backgroundColor = s.getAttribute('data-dark-bg');
                    s.style.color = s.getAttribute('data-dark-text');
                });
            }
        }

        // --- RENDER HELPERS ---
        function createCardElement(card) {
            try {
                const div = document.createElement('div');
                div.className = "bg-white dark:bg-gray-750 p-3 rounded shadow-sm border border-gray-200 dark:border-gray-600 cursor-pointer hover:shadow-md transition group relative flex flex-col gap-1";
                div.dataset.id = card.id;
                div.onclick = () => openEditCardModal(card);
                
                let tagsHtml = '';
                
                let priorityHtml = '';
                if (card.priority) {
                    priorityHtml = `<span class="text-[10px] font-bold px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800 bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100" title="Prioridade: ${card.priority}">
                        ${card.priority}
                    </span>`;
                }

                if ((card.tags && Array.isArray(card.tags) && card.tags.length > 0) || card.priority) {
                    tagsHtml = `<div class="flex flex-wrap gap-1 mb-1 items-center">
                        ${priorityHtml}
                        ${Array.isArray(card.tags) ? card.tags.map(t => {
                            if (!t) return '';
                            t = t.toLowerCase();
                            const colors = getTagStyles(t);
                            return `<span class="text-[10px] font-bold px-2 py-0.5 rounded border border-black/5 dark:border-white/10" 
                                          style="background-color: ${colors.light.bg}; color: ${colors.light.text};"
                                          data-dark-bg="${colors.dark.bg}" data-dark-text="${colors.dark.text}">
                                    ${t}</span>`;
                        }).join('') : ''}
                    </div>`;
                }

                // Formatação de Datas
                const formatDate = (isoStr) => {
                    if(!isoStr) return '';
                    const d = new Date(isoStr);
                    if (isNaN(d.getTime())) return '';
                    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                };
                const dateInfo = card.updatedAt
                    ? `<div class="text-[10px] text-gray-400 flex items-center gap-1"><i class="fa-regular fa-clock"></i> Atualizado: ${formatDate(card.updatedAt)}</div>`
                    : (card.createdAt ? `<div class="text-[10px] text-gray-400 flex items-center gap-1"><i class="fa-solid fa-star"></i> Criado: ${formatDate(card.createdAt)}</div>` : '');

                const uniqueIdBadge = card.uniqueId
                    ? `<span class="text-[10px] text-gray-400 font-mono self-end ml-auto pl-2 flex-shrink-0">#${card.uniqueId}</span>`
                    : '';

                const boardBadge = (isUnifiedView && card.boardName)
                    ? `<div class="text-[10px] font-bold text-blue-500 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded-full w-fit mb-1">${card.boardName}</div>`
                    : '';

                let linksHtml = '';
                if (card.links && card.links.length > 0) {
                    linksHtml = `<div class="flex flex-wrap gap-1 mt-1">
                        ${card.links.map(link => `<a href="${link}" target="_blank" onclick="event.stopPropagation()" class="text-[10px] text-blue-500 hover:underline flex items-center gap-1 bg-blue-50 dark:bg-blue-900/20 px-1.5 py-0.5 rounded"><i class="fa-solid fa-link"></i> Link</a>`).join('')}
                    </div>`;
                }

                // Mobile action bar: move-to column + reorder buttons
                const mobileActions = `
                    <div class="card-mobile-actions items-center gap-1 mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                        <button onclick="event.stopPropagation(); openMoveToMenu('${card.id}', this)"
                                class="flex-1 flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/40 transition">
                            <i class="fa-solid fa-arrow-right-arrow-left"></i> Mover para
                        </button>
                        <button onclick="event.stopPropagation(); moveCardInColumn('${card.id}', -1)"
                                class="flex items-center justify-center w-9 h-9 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded transition" title="Mover para cima">
                            <i class="fa-solid fa-chevron-up text-xs"></i>
                        </button>
                        <button onclick="event.stopPropagation(); moveCardInColumn('${card.id}', 1)"
                                class="flex items-center justify-center w-9 h-9 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded transition" title="Mover para baixo">
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </button>
                    </div>`;

                div.innerHTML = `${boardBadge}
                                 ${tagsHtml}
                                 <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">${card.title}</h4>
                                 ${card.description ? `<p class="text-xs text-gray-500 ${isMobile() ? '' : 'line-clamp-2'}">${card.description}</p>` : ''}
                                 ${linksHtml}
                                 <div class="flex items-end justify-between mt-2">
                                     <div>${dateInfo}</div>
                                     ${uniqueIdBadge}
                                 </div>
                                 ${mobileActions}
                                 <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 flex gap-2">
                                     <button onclick="event.stopPropagation(); openMoveToMenu('${card.id}', this)" class="text-gray-400 hover:text-blue-500 transition-colors" title="Mover">
                                         <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i>
                                     </button>
                                     <i class="fa-solid fa-pencil text-gray-400 text-[10px] flex items-center"></i>
                                 </div>`;
                
                applyDarkModeStyles(div);

                return div;
            } catch (e) {
                console.error("Erro ao criar elemento do cartão:", e, card);
                return null;
            }
        }

        function updateModelFromDOM() {
            const boardEl = document.getElementById('boardColumns');
            const now = new Date().toISOString();
            let hasChanges = false;
            
            // Build a map of ALL cards by id (before any DOM changes)
            const allCardsMap = new Map();
            currentBoard.columns.forEach(col => {
                col.cards.forEach(card => {
                    allCardsMap.set(card.id, { data: { ...card }, sourceColId: col.id });
                });
            });

            // FIRST PASS: collect all card IDs currently visible in the DOM
            const visibleCardIds = new Set();
            Array.from(boardEl.children).forEach(colEl => {
                const cardsListEl = colEl.querySelector('.kanban-col');
                Array.from(cardsListEl.children).forEach(cardEl => {
                    if (cardEl.dataset.id) visibleCardIds.add(cardEl.dataset.id);
                });
            });

            // SECOND PASS: build new columns from DOM order
            const newColumns = [];
            const processedColIds = new Set();

            Array.from(boardEl.children).forEach(colEl => {
                const colId = colEl.dataset.id;
                const originalCol = currentBoard.columns.find(c => c.id === colId);
                if (!originalCol) return;
                processedColIds.add(colId);

                const newCards = [];
                const cardsListEl = colEl.querySelector('.kanban-col');
                Array.from(cardsListEl.children).forEach(cardEl => {
                    const cardId = cardEl.dataset.id;
                    if (!cardId) return;

                    const entry = allCardsMap.get(cardId);
                    if (entry) {
                        const cardData = { ...entry.data };
                        if (entry.sourceColId !== colId) {
                            cardData.updatedAt = now;
                            hasChanges = true;
                        }
                        newCards.push(cardData);
                    }
                });

                // Preserve cards that were originally in this column but were NOT visible (filtered out)
                // and were NOT moved to another column (not visible anywhere in DOM)
                originalCol.cards.forEach(card => {
                    if (!visibleCardIds.has(card.id)) {
                        newCards.push({ ...card });
                    }
                });

                newColumns.push({ ...originalCol, cards: newCards });
            });

            // THIRD PASS: preserve columns that were NOT in the DOM (docked)
            currentBoard.columns.forEach(col => {
                if (!processedColIds.has(col.id)) {
                    newColumns.push({ ...col });
                }
            });
            
            currentBoard.columns = newColumns;
            return hasChanges;
        }

        // --- ACTIONS ---
        async function createNewBoard() {
            const name = prompt("Nome do novo projeto:");
            if (name) {
                const res = await fetchAPI('create_board', 'POST', { name });
                if (res.success) { 
                    await loadBoardsList(); 
                    loadBoard(res.id); 
                    document.getElementById('boardDropdown').classList.add('hidden');
                }
            }
        }
        async function editCurrentBoard() {
            const newName = prompt("Novo nome:", currentBoard.name);
            if(newName && newName !== currentBoard.name) {
                currentBoard.name = newName;
                await saveBoardState();
                loadBoardsList();
            }
        }
        async function deleteCurrentBoard() {
            if(confirm(`Excluir "${currentBoard.name}"?`)) {
                const res = await fetchAPI('delete_board', 'POST', { id: currentBoard.id });
                if(res.success) { 
                    await loadBoardsList(); 
                    loadBoard(null); 
                }
            }
        }

        async function archiveCurrentBoard() {
            if (!confirm(`Arquivar "${currentBoard.name}"?`)) return;
            const res = await fetchAPI('archive_board', 'POST', { id: currentBoard.id });
            if (res && res.success) {
                await loadBoardsList();
                if (boardsList.length > 0) {
                    loadBoard(boardsList[0].id);
                } else {
                    currentBoard = { id: null, name: 'Nenhum projeto ativo', columns: [], trash: [] };
                    renderBoard();
                }
            }
        }

        async function openArchivedModal() {
            document.getElementById('boardDropdown').classList.add('hidden');
            const list = document.getElementById('archivedList');
            list.innerHTML = '<div class="text-center text-gray-500 py-4">Carregando...</div>';
            document.getElementById('archivedModal').classList.remove('hidden');

            const archived = await fetchAPI('list_archived_boards') || [];
            list.innerHTML = '';

            if (archived.length === 0) {
                list.innerHTML = '<div class="text-center text-gray-500 py-4">Nenhum projeto arquivado.</div>';
                return;
            }

            archived.forEach(b => {
                const el = document.createElement('div');
                el.className = 'bg-white dark:bg-gray-800 p-3 rounded shadow border dark:border-gray-700 flex justify-between items-center';
                el.innerHTML = `
                    <span class="font-bold text-gray-800 dark:text-gray-200">${b.name}</span>
                    <button onclick="unarchiveBoard('${b.id}')" class="text-green-600 hover:text-green-800 text-sm font-bold flex items-center gap-1" title="Desarquivar">
                        <i class="fa-solid fa-rotate-left"></i> Desarquivar
                    </button>
                `;
                list.appendChild(el);
            });
        }

        async function unarchiveBoard(id) {
            const res = await fetchAPI('unarchive_board', 'POST', { id });
            if (res && res.success) {
                await loadBoardsList();
                openArchivedModal();
            }
        }

        // Modais e Helpers
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
        
        function openAddCardModal(colId) {
            document.getElementById('cardModalTitle').innerText = 'Novo Cartão';
            document.getElementById('cardId').value = '';
            document.getElementById('cardColumnId').value = colId;
            document.getElementById('cardTitleInput').value = '';
            document.getElementById('cardDescInput').value = '';
            document.getElementById('cardLinksInput').value = '';
            document.getElementById('cardTagsInput').value = '';

            const radios = document.getElementsByName('cardPriority');
            radios.forEach(r => r.checked = (r.value === ''));
            
            renderTagEditor();
            renderTagSuggestions(); // Carrega sugestões

            const btnDel = document.getElementById('btnDeleteCard'); if(btnDel) btnDel.remove();
            const btnMove = document.getElementById('btnMoveCard'); if(btnMove) btnMove.remove();
            const btnCopy = document.getElementById('btnCopyDesc'); if(btnCopy) btnCopy.classList.add('hidden');
            
            document.getElementById('cardModal').classList.remove('hidden');
            setTimeout(() => document.getElementById('cardTitleInput').focus(), 100);
        }
        
        function openEditCardModal(card) {
            document.getElementById('cardModalTitle').innerText = 'Editar Cartão';
            document.getElementById('cardId').value = card.id;
            document.getElementById('cardTitleInput').value = card.title;
            document.getElementById('cardDescInput').value = card.description || '';
            document.getElementById('cardLinksInput').value = (card.links && card.links.length > 0) ? card.links.join('\n') : '';
            document.getElementById('cardTagsInput').value = card.tags ? card.tags.join(', ') : '';
            
            const priority = card.priority || '';
            const radios = document.getElementsByName('cardPriority');
            radios.forEach(r => r.checked = (r.value === priority));

            renderTagEditor();
            renderTagSuggestions(); // Carrega sugestões

            const footer = document.getElementById('cardModalFooter');
            
            // Gerenciar Botão de Excluir
            const existingDel = document.getElementById('btnDeleteCard'); 
            if(existingDel) existingDel.remove();
            
            const delBtn = document.createElement('button');
            delBtn.id = 'btnDeleteCard';
            delBtn.innerText = 'Excluir';
            delBtn.className = "px-4 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded";
            delBtn.onclick = () => deleteCard(card.id);

            // Gerenciar Botão de Mover
            const existingMove = document.getElementById('btnMoveCard');
            if(existingMove) existingMove.remove();

            const moveBtn = document.createElement('button');
            moveBtn.id = 'btnMoveCard';
            moveBtn.className = "px-4 py-2 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded flex items-center gap-2";
            moveBtn.innerHTML = '<i class="fa-solid fa-arrow-right-arrow-left"></i> Mover';
            moveBtn.onclick = () => openMoveToMenu(card.id, moveBtn);
            
            // Inserir antes do botão de copiar ou no início
            const copyBtn = document.getElementById('btnCopyDesc');
            copyBtn.classList.remove('hidden');
            footer.insertBefore(delBtn, copyBtn);
            footer.insertBefore(moveBtn, copyBtn);
            
            document.getElementById('cardModal').classList.remove('hidden');
        }

        // --- TAG SYSTEM ---
        async function openTagManager() {
            // Carrega todas as tags (sem filtro de board) para o gerenciador
            const allResult = await fetchAPI('get_all_tags');
            allSystemTags = Array.isArray(allResult) ? allResult : [];
            allSystemTags.sort((a, b) => a.name.localeCompare(b.name));

            const listEl = document.getElementById('tagManagerList');
            listEl.innerHTML = '';
            if(allSystemTags.length === 0) {
                 listEl.innerHTML = '<span class="text-gray-400 italic">Nenhuma tag para gerenciar.</span>';
                 document.getElementById('tagManagerModal').classList.remove('hidden');
                 return;
            }

            allSystemTags.forEach(tag => {
                const item = document.createElement('div');
                item.className = 'flex items-center gap-2 p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700/50';
                item.id = `tag-manager-row-${CSS.escape(tag.name)}`;

                // Color picker
                const colorInputContainer = document.createElement('div');
                colorInputContainer.className = 'relative flex-shrink-0';

                const colorInput = document.createElement('input');
                colorInput.type = 'color';
                colorInput.id = `color-picker-${CSS.escape(tag.name)}`;
                colorInput.className = 'absolute w-full h-full inset-0 opacity-0 cursor-pointer';
                colorInput.value = tag.color || '#ffffff';

                const colorLabel = document.createElement('label');
                colorLabel.htmlFor = colorInput.id;
                colorLabel.className = 'w-8 h-8 block rounded border border-gray-300 dark:border-gray-600 cursor-pointer';
                colorLabel.style.backgroundColor = tag.color || '#ffffff';

                colorInputContainer.append(colorInput, colorLabel);

                colorInput.addEventListener('input', (e) => {
                    colorLabel.style.backgroundColor = e.target.value;
                });
                colorInput.addEventListener('change', async (e) => {
                    await saveTagColor(tag.name, e.target.value);
                });

                // Tag name (display)
                const nameContainer = document.createElement('div');
                nameContainer.className = 'flex items-center gap-1 flex-1 min-w-0';

                const tagNameEl = document.createElement('span');
                tagNameEl.className = 'font-semibold truncate';
                tagNameEl.innerText = tag.name;

                const editNameBtn = document.createElement('button');
                editNameBtn.className = 'text-gray-400 hover:text-blue-500 flex-shrink-0 p-1';
                editNameBtn.title = 'Renomear';
                editNameBtn.innerHTML = '<i class="fa-solid fa-pen fa-xs"></i>';
                editNameBtn.onclick = () => startRenameTag(tag.name, item);

                nameContainer.append(tagNameEl, editNameBtn);

                // Scope badge
                const scopeBadge = document.createElement('button');
                scopeBadge.className = 'text-[11px] px-2 py-0.5 rounded-full border flex-shrink-0 whitespace-nowrap';
                const scope = tag.scope || 'global';
                if (scope === 'global') {
                    scopeBadge.className += ' bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800';
                    scopeBadge.innerText = 'Global';
                } else {
                    const count = (tag.projectIds || []).length;
                    scopeBadge.className += ' bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800';
                    scopeBadge.innerText = count + ' projeto' + (count !== 1 ? 's' : '');
                }
                scopeBadge.title = 'Editar escopo';
                scopeBadge.onclick = () => openScopeEditor(tag.name, item);

                // Delete button
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'text-gray-400 hover:text-red-500 flex-shrink-0 p-1';
                deleteBtn.title = 'Excluir tag';
                deleteBtn.innerHTML = '<i class="fa-solid fa-trash fa-xs"></i>';
                deleteBtn.onclick = () => deleteTag(tag.name);

                item.append(colorInputContainer, nameContainer, scopeBadge, deleteBtn);
                listEl.appendChild(item);
            });

            applyDarkModeStyles(listEl);
            document.getElementById('tagManagerModal').classList.remove('hidden');
        }

        // --- RENAME TAG ---
        function startRenameTag(tagName, rowEl) {
            const nameContainer = rowEl.querySelector('.flex.items-center.gap-1');
            if (!nameContainer) return;

            nameContainer.innerHTML = '';
            const input = document.createElement('input');
            input.type = 'text';
            input.value = tagName;
            input.className = 'text-sm p-1 border rounded dark:bg-gray-700 dark:border-gray-600 outline-none focus:ring-2 focus:ring-blue-500 w-full';

            const confirmBtn = document.createElement('button');
            confirmBtn.className = 'text-green-500 hover:text-green-700 flex-shrink-0 p-1';
            confirmBtn.innerHTML = '<i class="fa-solid fa-check"></i>';

            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'text-gray-400 hover:text-gray-600 flex-shrink-0 p-1';
            cancelBtn.innerHTML = '<i class="fa-solid fa-times"></i>';

            const doRename = async () => {
                const newName = input.value.trim().toLowerCase();
                if (!newName || newName === tagName) {
                    openTagManager();
                    return;
                }
                const res = await fetchAPI('rename_tag', 'POST', { oldName: tagName, newName });
                if (res && res.error) {
                    alert(res.error);
                    return;
                }
                await loadSystemTags();
                await loadBoard(currentBoard.id);
                openTagManager();
            };

            confirmBtn.onclick = doRename;
            cancelBtn.onclick = () => openTagManager();
            input.onkeydown = (e) => {
                if (e.key === 'Enter') doRename();
                if (e.key === 'Escape') openTagManager();
            };

            nameContainer.append(input, confirmBtn, cancelBtn);
            input.focus();
            input.select();
        }

        // --- DELETE TAG ---
        async function deleteTag(tagName) {
            if (!confirm(`Excluir a tag "${tagName}"? Ela será removida de todos os cartões.`)) return;

            const res = await fetchAPI('delete_tag', 'POST', { name: tagName });
            if (res && res.error) {
                alert(res.error);
                return;
            }
            await loadSystemTags();
            await loadBoard(currentBoard.id);
            openTagManager();
        }

        // --- SCOPE EDITOR ---
        async function openScopeEditor(tagName, rowEl) {
            // Remove any existing scope editor
            const existingEditor = document.getElementById('scope-editor-panel');
            if (existingEditor) existingEditor.remove();

            const tag = allSystemTags.find(t => t.name === tagName);
            if (!tag) return;

            const currentScope = tag.scope || 'global';
            const currentProjectIds = tag.projectIds || [];

            const panel = document.createElement('div');
            panel.id = 'scope-editor-panel';
            panel.className = 'bg-gray-50 dark:bg-gray-900 border dark:border-gray-700 rounded p-3 mt-1 mb-1 space-y-3';

            // Radio options
            const radioGroup = document.createElement('div');
            radioGroup.className = 'space-y-2';

            const globalLabel = document.createElement('label');
            globalLabel.className = 'flex items-center gap-2 cursor-pointer text-sm';
            globalLabel.innerHTML = `<input type="radio" name="scopeRadio" value="global" ${currentScope === 'global' ? 'checked' : ''} class="text-blue-600"> Global (todos os projetos)`;

            const projectLabel = document.createElement('label');
            projectLabel.className = 'flex items-center gap-2 cursor-pointer text-sm';
            projectLabel.innerHTML = `<input type="radio" name="scopeRadio" value="project" ${currentScope === 'project' ? 'checked' : ''} class="text-blue-600"> Projetos específicos`;

            radioGroup.append(globalLabel, projectLabel);

            // Project checkboxes container
            const projectListContainer = document.createElement('div');
            projectListContainer.id = 'scope-project-list';
            projectListContainer.className = 'space-y-1 pl-6 max-h-40 overflow-y-auto';
            if (currentScope !== 'project') projectListContainer.classList.add('hidden');

            // Load active boards for checkboxes
            const activeBoards = boardsList || [];
            activeBoards.forEach(b => {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2';
                const checked = currentProjectIds.includes(b.id) ? 'checked' : '';
                div.innerHTML = `<input type="checkbox" value="${b.id}" ${checked} class="scope-project-cb w-3.5 h-3.5 text-blue-600 rounded"> <span class="text-sm">${b.name}</span>`;
                projectListContainer.appendChild(div);
            });

            // Toggle project list visibility
            radioGroup.addEventListener('change', (e) => {
                if (e.target.value === 'project') {
                    projectListContainer.classList.remove('hidden');
                } else {
                    projectListContainer.classList.add('hidden');
                }
            });

            // Save/Cancel buttons
            const actions = document.createElement('div');
            actions.className = 'flex justify-end gap-2';

            const cancelScopeBtn = document.createElement('button');
            cancelScopeBtn.className = 'text-sm px-3 py-1 text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700 rounded';
            cancelScopeBtn.innerText = 'Cancelar';
            cancelScopeBtn.onclick = () => panel.remove();

            const saveScopeBtn = document.createElement('button');
            saveScopeBtn.className = 'text-sm px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700';
            saveScopeBtn.innerText = 'Salvar';
            saveScopeBtn.onclick = async () => {
                const selectedScope = panel.querySelector('input[name="scopeRadio"]:checked').value;
                let selectedProjects = [];
                if (selectedScope === 'project') {
                    selectedProjects = Array.from(panel.querySelectorAll('.scope-project-cb:checked')).map(cb => cb.value);
                }
                await fetchAPI('update_tag_scope', 'POST', { name: tagName, scope: selectedScope, projectIds: selectedProjects });
                await loadSystemTags();
                openTagManager();
            };

            actions.append(cancelScopeBtn, saveScopeBtn);
            panel.append(radioGroup, projectListContainer, actions);

            // Insert panel after the row
            rowEl.after(panel);
        }
        
        async function saveTagColor(tagName, color) {
            const tag = systemTags.find(t => t.name === tagName);
            if (tag) tag.color = color;

            const allTag = allSystemTags.find(t => t.name === tagName);
            if (allTag) allTag.color = color;

            await fetchAPI('update_tag_color', 'POST', { name: tagName, color: color });

            renderBoard();

            if(!document.getElementById('cardModal').classList.contains('hidden')) {
                renderTagSuggestions();
                renderTagEditor();
            }
        }

        function renderTagEditor() {
            const container = document.getElementById('tag-editor-container');
            const hiddenInput = document.getElementById('cardTagsInput');
            const tags = hiddenInput.value ? hiddenInput.value.split(',').map(t => t.trim()).filter(t => t) : [];

            container.innerHTML = ''; // Limpa para reconstruir

            tags.forEach(tag => {
                const tagEl = document.createElement('span');
                const colors = getTagStyles(tag);
                tagEl.className = 'flex items-center gap-1 text-xs font-bold px-2 py-1 rounded';
                tagEl.style.backgroundColor = colors.light.bg;
                tagEl.style.color = colors.light.text;
                tagEl.setAttribute('data-dark-bg', colors.dark.bg);
                tagEl.setAttribute('data-dark-text', colors.dark.text);

                const textEl = document.createElement('span');
                textEl.innerText = tag;

                const removeBtn = document.createElement('button');
                removeBtn.className = 'text-black/30 dark:text-white/40 hover:text-black dark:hover:text-white transition-colors';
                removeBtn.innerHTML = '<i class="fa-solid fa-times fa-xs"></i>';
                removeBtn.onclick = (e) => {
                    e.stopPropagation();
                    removeTagFromInput(tag);
                };

                tagEl.appendChild(textEl);
                tagEl.appendChild(removeBtn);
                container.appendChild(tagEl);
            });

            const inputEl = document.createElement('input');
            inputEl.type = 'text';
            inputEl.id = 'tag-entry-input';
            inputEl.placeholder = 'Adicionar tag...';
            inputEl.className = 'flex-grow bg-transparent outline-none text-sm p-1';
            inputEl.onkeydown = (e) => {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    let newTag = e.target.value.trim().toLowerCase().replace(/,/g, '');
                    if (newTag) {
                        addTagToInput(newTag);
                    }
                } else if (e.key === 'Backspace' && e.target.value === '' && tags.length > 0) {
                    removeTagFromInput(tags[tags.length - 1]);
                }
            };

            container.appendChild(inputEl);
            applyDarkModeStyles(container);
        }

        function removeTagFromInput(tagToRemove) {
            const input = document.getElementById('cardTagsInput');
            let currentTags = input.value.split(',').map(t => t.trim().toLowerCase()).filter(t => t);
            
            // Filtra mantendo todas as tags EXCETO a que deve ser removida
            currentTags = currentTags.filter(t => t !== tagToRemove.toLowerCase());

            input.value = currentTags.join(', ');
            renderTagEditor();
            renderTagSuggestions();
        }
        
        function renderTagSuggestions() {
            const container = document.getElementById('availableTags');
            container.innerHTML = ''; // Limpa completamente o container

            const currentTags = document.getElementById('cardTagsInput').value.split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
            const suggestions = systemTags.filter(tagObj => !currentTags.includes(tagObj.name.toLowerCase()));

            if (suggestions.length === 0) {
                const text = systemTags.length > 0 ? 'Todas as tags sugeridas já foram usadas.' : 'Nenhuma tag existente no sistema.';
                container.innerHTML = `<span class="text-xs text-gray-400 italic">${text}</span>`;
                return;
            }
            
            const suggestionsHeader = document.createElement('span');
            suggestionsHeader.className = 'text-xs text-gray-400 w-full mb-1';
            suggestionsHeader.innerText = 'Sugestões (clique para adicionar):';
            container.appendChild(suggestionsHeader);

            suggestions.forEach(tagObj => {
                const chip = document.createElement('button');
                const colors = getTagStyles(tagObj.name);
                
                chip.className = "text-xs font-bold px-2 py-1 rounded transition border border-gray-200 dark:border-gray-600";
                chip.style.backgroundColor = colors.light.bg;
                chip.style.color = colors.light.text;
                chip.setAttribute('data-dark-bg', colors.dark.bg);
                chip.setAttribute('data-dark-text', colors.dark.text);
                
                chip.innerText = tagObj.name;
                chip.onclick = () => addTagToInput(tagObj.name);
                container.appendChild(chip);
            });
            applyDarkModeStyles(container);
        }

        function addTagToInput(tag) {
            const input = document.getElementById('cardTagsInput');
            let current = input.value.split(',').map(t => t.trim().toLowerCase()).filter(t => t);

            const newTag = tag.trim().toLowerCase();
            if (newTag && !current.includes(newTag)) {
                current.push(newTag);
                input.value = current.join(', ');

                // Se a tag for nova, adiciona no systemTags e define escopo para o projeto atual
                if (!systemTags.find(t => t.name === newTag)) {
                    systemTags.push({ name: newTag, color: null, scope: 'project', projectIds: [currentBoard.id] });
                    systemTags.sort((a, b) => a.name.localeCompare(b.name));
                    // Persiste o escopo no backend
                    if (currentBoard.id) {
                        fetchAPI('update_tag_scope', 'POST', { name: newTag, scope: 'project', projectIds: [currentBoard.id] });
                    }
                }

                renderTagEditor();
                renderTagSuggestions();
            }

            const entryInput = document.getElementById('tag-entry-input');
            if(entryInput) entryInput.value = '';
        }

        async function copyDescription() {
            const desc = document.getElementById('cardDescInput').value;
            if(!desc) return;

            try {
                await navigator.clipboard.writeText(desc);
                const btn = document.getElementById('btnCopyDesc');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
                btn.classList.replace('text-blue-500', 'text-green-500');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.replace('text-green-500', 'text-blue-500');
                }, 2000);
            } catch (err) {
                console.error('Erro ao copiar:', err);
            }
        }

        async function saveCard() {
            try {
                const id = document.getElementById('cardId').value;
                const colId = document.getElementById('cardColumnId').value;
                const title = document.getElementById('cardTitleInput').value.trim();
                const desc = document.getElementById('cardDescInput').value;
                const links = document.getElementById('cardLinksInput').value.split('\n').map(l => l.trim()).filter(l => l);
                const priorityInput = document.querySelector('input[name="cardPriority"]:checked');
                const priority = priorityInput ? priorityInput.value : '';
                const tags = document.getElementById('cardTagsInput').value.split(',').map(t=>t.trim().toLowerCase()).filter(t=>t);
                const now = new Date().toISOString();

                if(!title) {
                    alert('Título obrigatório');
                    return;
                }

                const afterSave = async () => {
                    await loadSystemTags();
                    if (isUnifiedView) {
                        await loadAllBoards();
                    } else {
                        renderBoard();
                    }
                };

                if (!id) { // Create
                    const col = currentBoard.columns.find(c => c.id === colId);
                    if(col) {
                        const uniqueId = currentBoard.nextCardId++;
                        col.cards.unshift({
                            id: 'card-' + Date.now(),
                            uniqueId,
                            title,
                            description: desc,
                            links,
                            tags,
                            priority,
                            createdAt: now,
                            updatedAt: null
                        });
                        await saveBoardState();
                        await afterSave();
                    }
                } else { // Update
                    let cardToUpdate = null;
                    let boardIdToSave = null;

                    for (const col of currentBoard.columns) {
                        const found = col.cards.find(c => c.id === id);
                        if (found) {
                            cardToUpdate = found;
                            boardIdToSave = isUnifiedView ? found.boardId : currentBoard.id;
                            break;
                        }
                    }

                    if (!cardToUpdate || !boardIdToSave) {
                        console.error("Card or Board not found", { id, isUnifiedView, boardIdToSave });
                        alert("Erro interno: Cartão não localizado para salvamento.");
                        return;
                    }

                    const cardData = {
                        title,
                        description: desc,
                        links,
                        tags,
                        priority,
                        updatedAt: now
                    };

                    // Optimistic update
                    Object.assign(cardToUpdate, cardData);

                    if (!isUnifiedView) {
                        // For regular boards, use the existing board-wide save which is well-tested
                        await saveBoardState();
                        await afterSave();
                    } else {
                        // For unified view, use the new granular save_card API
                        const res = await fetchAPI('save_card', 'POST', {
                            id: id,
                            boardId: boardIdToSave,
                            cardData: cardData
                        });

                        if (res && res.success) {
                            await afterSave();
                        } else {
                            throw new Error(res?.error || "Falha na comunicação com o servidor");
                        }
                    }
                }
                closeModal('cardModal');
            } catch (err) {
                console.error("Erro em saveCard:", err);
                alert("Erro ao salvar: " + err.message);
            }
        }

        async function deleteCard(cardId) {
            try {
                if (!confirm('Mover este cartão para a lixeira?')) return;

                let boardIdToSave = null;
                if (isUnifiedView) {
                    const card = findCardById(cardId);
                    if (card) boardIdToSave = card.boardId;
                } else {
                    boardIdToSave = currentBoard.id;
                }

                if (!boardIdToSave) {
                    alert("Erro interno: Projeto não identificado para exclusão.");
                    return;
                }

                const res = await fetchAPI('delete_card', 'POST', {
                    id: cardId,
                    boardId: boardIdToSave
                });

                if (res && res.success) {
                    if (isUnifiedView) {
                        await loadAllBoards();
                    } else {
                        // Remove localmente
                        for (const col of currentBoard.columns) {
                            const idx = col.cards.findIndex(c => c.id === cardId);
                            if (idx !== -1) {
                                col.cards.splice(idx, 1);
                                break;
                            }
                        }
                        renderBoard();
                        updateTrashIcon();
                    }
                } else {
                    throw new Error(res?.error || "Falha na comunicação com o servidor");
                }
                closeModal('cardModal');
            } catch (err) {
                console.error("Erro em deleteCard:", err);
                alert("Erro ao excluir: " + err.message);
            }
        }

        // --- TRASH FUNCTIONS ---
        function openTrashModal() {
            const list = document.getElementById('trashList');
            list.innerHTML = '';
            
            if (!currentBoard.trash || currentBoard.trash.length === 0) {
                list.innerHTML = '<div class="text-center text-gray-500 py-4">Lixeira vazia.</div>';
            } else {
                // Ordenar por data de exclusão (mais recente primeiro)
                const sortedTrash = [...currentBoard.trash].sort((a,b) => new Date(b.deletedAt) - new Date(a.deletedAt));
                
                sortedTrash.forEach(card => {
                    const el = document.createElement('div');
                    el.className = 'bg-white dark:bg-gray-800 p-3 rounded shadow border dark:border-gray-700 flex justify-between items-center';
                    el.innerHTML = `
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-gray-200">${card.title}</h4>
                            <p class="text-xs text-gray-500">Excluído em: ${new Date(card.deletedAt).toLocaleString()}</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="restoreCard('${card.id}')" class="text-green-600 hover:text-green-800 text-sm font-bold" title="Restaurar"><i class="fa-solid fa-rotate-left"></i></button>
                            <button onclick="deleteForever('${card.id}')" class="text-red-500 hover:text-red-700 text-sm" title="Excluir Permanentemente"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    `;
                    list.appendChild(el);
                });
            }
            
            document.getElementById('trashModal').classList.remove('hidden');
        }

        function restoreCard(cardId) {
            const idx = currentBoard.trash.findIndex(c => c.id === cardId);
            if (idx === -1) return;
            
            const card = currentBoard.trash[idx];
            // Remove from trash
            currentBoard.trash.splice(idx, 1);
            
            // Try to put back in original column, else first column
            let col = currentBoard.columns.find(c => c.id === card.originalColumn);
            if (!col && currentBoard.columns.length > 0) col = currentBoard.columns[0];
            
            if (col) {
                delete card.deletedAt;
                delete card.originalColumn;
                col.cards.push(card);
            }
            
            saveBoardState().then(() => {
                renderBoard();
                openTrashModal(); // Refresh list
            });
        }

        function deleteForever(cardId) {
            if(!confirm("Tem certeza? Essa ação não pode ser desfeita.")) return;
            
            currentBoard.trash = currentBoard.trash.filter(c => c.id !== cardId);
            saveBoardState().then(() => openTrashModal());
        }

        function emptyTrash() {
            if(!confirm("Esvaziar toda a lixeira?")) return;
            currentBoard.trash = [];
            saveBoardState().then(() => openTrashModal());
        }

        // --- COLUMN MODALS ---
        function openAddColumnModal() {
            document.getElementById('columnModalTitle').innerText = 'Nova Coluna';
            document.getElementById('columnId').value = '';
            document.getElementById('columnTitleInput').value = '';
            document.getElementById('btnDeleteColumn').classList.add('hidden');
            document.getElementById('columnModal').classList.remove('hidden');
            setTimeout(() => document.getElementById('columnTitleInput').focus(), 100);
        }

        function openEditColumnModal(colId) {
            const col = currentBoard.columns.find(c => c.id === colId);
            if (!col) return;

            document.getElementById('columnModalTitle').innerText = 'Editar Coluna';
            document.getElementById('columnId').value = colId;
            document.getElementById('columnTitleInput').value = col.title;
            document.getElementById('btnDeleteColumn').classList.remove('hidden');
            document.getElementById('columnModal').classList.remove('hidden');
        }

        function saveColumn() {
            const id = document.getElementById('columnId').value;
            const title = document.getElementById('columnTitleInput').value;
            if (!title) return alert('Título obrigatório');

            if (!id) { // Create
                currentBoard.columns.push({
                    id: 'col-' + Date.now(),
                    title,
                    cards: []
                });
            } else { // Update
                const col = currentBoard.columns.find(c => c.id === id);
                if (col) col.title = title;
            }
            saveBoardState().then(() => renderBoard());
            closeModal('columnModal');
        }

        function deleteColumn() {
            const id = document.getElementById('columnId').value;
            const col = currentBoard.columns.find(c => c.id === id);

            if (!col) return;

            const hasCards = col.cards.length > 0;
            const confirmMsg = hasCards
                ? `Excluir "${col.title}" e seus ${col.cards.length} cartão(ões)?`
                : `Excluir "${col.title}"?`;

            if (confirm(confirmMsg)) {
                currentBoard.columns = currentBoard.columns.filter(c => c.id !== id);
                saveBoardState().then(() => renderBoard());
                closeModal('columnModal');
            }
        }
    </script>

<?php endif; ?>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('[SW] Registered', reg))
            .catch(err => console.log('[SW] Registration failed', err));
    });
}
</script>

</body>
</html>