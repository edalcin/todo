<?php
// api.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    require_once 'config.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro de configuração: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

// --- LOGIN ---
if ($action === 'login' && $method === 'POST') {
    $input = getJsonInput();
    if (($input['pin'] ?? '') === PIN_CODE) {
        $_SESSION['auth'] = true;
        sendResponse(['success' => true]);
    }
    sendResponse(['success' => false, 'error' => 'PIN Incorreto'], 401);
}

// --- VERIFICAÇÃO DE SEGURANÇA ---
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    sendResponse(['error' => 'Não autorizado'], 401);
}

try {
    // --- DOWNLOAD BACKUP ---
    if ($method === 'GET' && $action === 'download_backup') {
        $file = 'todo.sqlite';
        if (file_exists($file)) {
            $date = date('Y-m-d');
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="todo-backup-' . $date . '.sqlite"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
        sendResponse(['error' => 'Arquivo de banco de dados não encontrado'], 404);
    }

    // --- RESTORE BACKUP ---
    if ($method === 'POST' && $action === 'restore_backup') {
        if (!isset($_FILES['backup_file'])) {
            sendResponse(['error' => 'Nenhum arquivo enviado'], 400);
        }

        $file = $_FILES['backup_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendResponse(['error' => 'Erro no upload: ' . $file['error']], 500);
        }

        // Verifica extensão básica
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($ext !== 'sqlite') {
            sendResponse(['error' => 'Apenas arquivos .sqlite são permitidos'], 400);
        }

        try {
            // Fecha a conexão PDO antes de substituir o arquivo
            $db = null; 
            
            // Backup do atual antes de sobrescrever (segurança)
            if (file_exists('todo.sqlite')) {
                copy('todo.sqlite', 'todo.sqlite.bak');
            }

            if (move_uploaded_file($file['tmp_name'], 'todo.sqlite')) {
                sendResponse(['success' => true]);
            } else {
                // Restaura se falhar
                if (file_exists('todo.sqlite.bak')) {
                    rename('todo.sqlite.bak', 'todo.sqlite');
                }
                sendResponse(['error' => 'Erro ao mover arquivo para o destino'], 500);
            }
        } catch (Exception $e) {
            sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    $db = getDB();

    if ($method === 'OPTIONS') exit(0);

    // --- LISTAR BOARDS ---
    if ($method === 'GET' && $action === 'list_boards') {
        $stmt = $db->prepare("SELECT id, name, display_order as `order` FROM boards WHERE archived = 0 ORDER BY display_order ASC, name ASC");
        $stmt->execute();
        $boards = $stmt->fetchAll();
        sendResponse($boards);
    }

    // --- OBTER TUDO (PARA VIEW UNIFICADA) ---
    if ($method === 'GET' && $action === 'get_all_data') {
        $stmt = $db->prepare("SELECT id, name, columns, trash, display_order FROM boards WHERE archived = 0 ORDER BY display_order ASC, name ASC");
        $stmt->execute();
        $boards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($boards as $b) {
            $b['columns'] = json_decode($b['columns'] ?? '[]', true) ?: [];
            $b['trash'] = json_decode($b['trash'] ?? '[]', true) ?: [];
            $b['order'] = $b['display_order'];
            $result[] = $b;
        }
        sendResponse($result);
    }

    // --- REORDENAR BOARDS ---
    if ($method === 'POST' && $action === 'reorder_boards') {
        $input = getJsonInput();
        $ids = $input['ids'] ?? [];

        if (is_array($ids) && count($ids) > 0) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE boards SET display_order = ? WHERE id = ?");
                foreach ($ids as $index => $id) {
                    $stmt->execute([$index, $id]);
                }
                $db->commit();
                sendResponse(['success' => true]);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                logError("Erro ao reordenar boards: " . $e->getMessage());
                sendResponse(['error' => $e->getMessage()], 500);
            }
        } else {
            sendResponse(['error' => 'Lista de IDs inválida'], 400);
        }
    }

    // --- LISTAR BOARDS ARQUIVADOS ---
    if ($method === 'GET' && $action === 'list_archived_boards') {
        $stmt = $db->prepare("SELECT id, name, display_order as `order` FROM boards WHERE archived = 1 ORDER BY name ASC");
        $stmt->execute();
        $boards = $stmt->fetchAll();
        sendResponse($boards);
    }

    // --- ARQUIVAR BOARD ---
    if ($method === 'POST' && $action === 'archive_board') {
        $input = getJsonInput();
        if (isset($input['id'])) {
            $stmt = $db->prepare("UPDATE boards SET archived = 1 WHERE id = ?");
            $stmt->execute([$input['id']]);
            sendResponse(['success' => true]);
        }
        sendResponse(['error' => 'ID inválido'], 400);
    }

    // --- DESARQUIVAR BOARD ---
    if ($method === 'POST' && $action === 'unarchive_board') {
        $input = getJsonInput();
        if (isset($input['id'])) {
            $stmt = $db->prepare("UPDATE boards SET archived = 0 WHERE id = ?");
            $stmt->execute([$input['id']]);
            sendResponse(['success' => true]);
        }
        sendResponse(['error' => 'ID inválido'], 400);
    }

    // --- OBTER TODAS AS TAGS DO SISTEMA ---
    if ($method === 'GET' && $action === 'get_all_tags') {
        try {
            $boardIdFilter = $_GET['board_id'] ?? null;

            // 1. Pega todas as tags que existem nos cartões (em todos os boards)
            $stmt = $db->prepare("SELECT columns FROM boards");
            $stmt->execute();
            $cardTags = [];
            while ($row = $stmt->fetch()) {
                $columns = json_decode($row['columns'], true) ?? [];
                foreach ($columns as $column) {
                    if (isset($column['cards'])) {
                        foreach ($column['cards'] as $card) {
                            if (isset($card['tags'])) {
                                foreach ($card['tags'] as $tag) {
                                    $cardTags[] = strtolower($tag);
                                }
                            }
                        }
                    }
                }
            }
            $uniqueCardTags = array_unique($cardTags);

            // 2. Tenta pegar tags com configurações de cores e escopo
            $stmtTags = $db->prepare("SELECT * FROM tags");
            $stmtTags->execute();
            $tagConfigs = [];
            while ($tagDoc = $stmtTags->fetch()) {
                $tagConfigs[$tagDoc['name']] = $tagDoc;
            }

            // 3. Junta as informações
            $allTags = [];
            foreach($uniqueCardTags as $tagName) {
                if (isset($tagConfigs[$tagName])) {
                    $config = $tagConfigs[$tagName];
                    $projectIds = json_decode($config['project_ids'] ?? '[]', true);
                    $allTags[] = [
                        'name' => $tagName,
                        'color' => $config['color'] ?? null,
                        'scope' => $config['scope'] ?? 'global',
                        'projectIds' => $projectIds
                    ];
                    unset($tagConfigs[$tagName]);
                } else {
                    $allTags[] = ['name' => $tagName, 'color' => null, 'scope' => 'global', 'projectIds' => []];
                }
            }

            // Adiciona tags que existem na tabela de tags mas não estão em nenhum cartão
            foreach($tagConfigs as $config) {
                $allTags[] = [
                    'name' => $config['name'],
                    'color' => $config['color'] ?? null,
                    'scope' => $config['scope'] ?? 'global',
                    'projectIds' => json_decode($config['project_ids'] ?? '[]', true)
                ];
            }

            // 4. Filtra por board_id se fornecido
            if ($boardIdFilter) {
                $allTags = array_values(array_filter($allTags, function($tag) use ($boardIdFilter) {
                    return $tag['scope'] === 'global' || in_array($boardIdFilter, $tag['projectIds']);
                }));
            }

            sendResponse($allTags);

        } catch (Exception $e) {
            logError("Erro em get_all_tags: " . $e->getMessage());
            sendResponse([], 200);
        }
    }
    
    // --- ATUALIZAR COR DE UMA TAG ---
    if ($method === 'POST' && $action === 'update_tag_color') {
        $input = getJsonInput();
        $tagName = $input['name'] ?? null;
        $color = $input['color'] ?? null;

        if ($tagName && $color) {
            $tagName = strtolower($tagName);
            $stmt = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?) ON CONFLICT(name) DO UPDATE SET color = excluded.color");
            $stmt->execute([$tagName, $color]);
            sendResponse(['success' => true]);
        }
        sendResponse(['error' => 'Dados incompletos'], 400);
    }

    // --- RENOMEAR TAG ---
    if ($method === 'POST' && $action === 'rename_tag') {
        $input = getJsonInput();
        $oldName = strtolower(trim($input['oldName'] ?? ''));
        $newName = strtolower(trim($input['newName'] ?? ''));

        if (!$oldName || !$newName) {
            sendResponse(['error' => 'Dados incompletos'], 400);
        }
        if ($oldName === $newName) {
            sendResponse(['success' => true]);
        }

        try {
            $db->beginTransaction();

            // Verifica se já existe uma tag com o novo nome
            $stmt = $db->prepare("SELECT name FROM tags WHERE name = ?");
            $stmt->execute([$newName]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma tag com o nome "' . $newName . '"');
            }

            // Atualiza na tabela tags
            $stmt = $db->prepare("UPDATE tags SET name = ? WHERE name = ?");
            $stmt->execute([$newName, $oldName]);

            // Atualiza todos os boards que usam essa tag
            $stmt = $db->prepare("SELECT id, columns FROM boards");
            $stmt->execute();
            $updateStmt = $db->prepare("UPDATE boards SET columns = ? WHERE id = ?");

            while ($board = $stmt->fetch()) {
                $changed = false;
                $columns = json_decode($board['columns'], true) ?? [];

                foreach ($columns as &$col) {
                    if (!isset($col['cards'])) continue;
                    foreach ($col['cards'] as &$card) {
                        if (!isset($card['tags'])) continue;
                        foreach ($card['tags'] as &$tag) {
                            if (strtolower($tag) === $oldName) {
                                $tag = $newName;
                                $changed = true;
                            }
                        }
                    }
                }

                if ($changed) {
                    $updateStmt->execute([json_encode($columns), $board['id']]);
                }
            }

            $db->commit();
            sendResponse(['success' => true]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            logError("Erro ao renomear tag: " . $e->getMessage());
            sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    // --- DELETAR TAG ---
    if ($method === 'POST' && $action === 'delete_tag') {
        $input = getJsonInput();
        $tagName = strtolower(trim($input['name'] ?? ''));

        if (!$tagName) {
            sendResponse(['error' => 'Nome da tag é obrigatório'], 400);
        }

        try {
            $db->beginTransaction();

            // Remove da tabela tags
            $stmt = $db->prepare("DELETE FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);

            // Remove de todos os cartões em todos os boards
            $stmt = $db->prepare("SELECT id, columns FROM boards");
            $stmt->execute();
            $updateStmt = $db->prepare("UPDATE boards SET columns = ? WHERE id = ?");

            while ($board = $stmt->fetch()) {
                $changed = false;
                $columns = json_decode($board['columns'], true) ?? [];

                foreach ($columns as &$col) {
                    if (!isset($col['cards'])) continue;
                    foreach ($col['cards'] as &$card) {
                        if (!isset($card['tags'])) continue;
                        $before = count($card['tags']);
                        $card['tags'] = array_values(array_filter($card['tags'], function($t) use ($tagName) {
                            return strtolower($t) !== $tagName;
                        }));
                        if (count($card['tags']) !== $before) $changed = true;
                    }
                }

                if ($changed) {
                    $updateStmt->execute([json_encode($columns), $board['id']]);
                }
            }

            $db->commit();
            sendResponse(['success' => true]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            logError("Erro ao deletar tag: " . $e->getMessage());
            sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    // --- ATUALIZAR ESCOPO DA TAG ---
    if ($method === 'POST' && $action === 'update_tag_scope') {
        $input = getJsonInput();
        $tagName = strtolower(trim($input['name'] ?? ''));
        $scope = $input['scope'] ?? 'global';
        $projectIds = $input['projectIds'] ?? [];

        if (!$tagName) {
            sendResponse(['error' => 'Nome da tag é obrigatório'], 400);
        }
        if (!in_array($scope, ['global', 'project'])) {
            sendResponse(['error' => 'Escopo inválido'], 400);
        }

        $stmt = $db->prepare("INSERT INTO tags (name, scope, project_ids) VALUES (?, ?, ?) ON CONFLICT(name) DO UPDATE SET scope = excluded.scope, project_ids = excluded.project_ids");
        $stmt->execute([$tagName, $scope, json_encode($projectIds)]);
        sendResponse(['success' => true]);
    }

    // --- OBTER UM BOARD ---
    if ($method === 'GET' && $action === 'get_board') {
        $boardId = $_GET['id'] ?? null;
        
        if (!$boardId) {
             $stmt = $db->prepare("SELECT * FROM boards LIMIT 1");
             $stmt->execute();
        } else {
             $stmt = $db->prepare("SELECT * FROM boards WHERE id = ?");
             $stmt->execute([$boardId]);
        }

        $board = $stmt->fetch();

        if ($board) {
            $board['columns'] = json_decode($board['columns'], true) ?? [];
            $board['trash'] = json_decode($board['trash'], true) ?? [];
            $board['order'] = $board['display_order'];
            sendResponse($board);
        } else {
            // Cria board padrão se não existir nenhum
            if (!$boardId) {
                $newId = bin2hex(random_bytes(12)); // Simula ObjectId (24 chars hex)
                $defaultColumns = [
                    ['id' => 'col-1', 'title' => 'A Fazer', 'cards' => []],
                    ['id' => 'col-2', 'title' => 'Em Progresso', 'cards' => []],
                    ['id' => 'col-3', 'title' => 'Concluído', 'cards' => []]
                ];
                $stmt = $db->prepare("INSERT INTO boards (id, name, columns, trash) VALUES (?, ?, ?, ?)");
                $stmt->execute([$newId, 'Meu Primeiro Projeto', json_encode($defaultColumns), json_encode([])]);
                
                sendResponse([
                    'id' => $newId,
                    'name' => 'Meu Primeiro Projeto',
                    'columns' => $defaultColumns,
                    'trash' => [],
                    'order' => 0
                ]);
            } else {
                sendResponse(['error' => 'Board não encontrado'], 404);
            }
        }
    }

    // --- CRIAR NOVO BOARD ---
    if ($method === 'POST' && $action === 'create_board') {
        $input = getJsonInput();
        $newId = bin2hex(random_bytes(12));
        $name = $input['name'] ?? 'Novo Projeto';
        $defaultColumns = [
            ['id' => 'col-' . uniqid(), 'title' => 'A Fazer', 'cards' => []],
            ['id' => 'col-' . uniqid(), 'title' => 'Em Progresso', 'cards' => []],
            ['id' => 'col-' . uniqid(), 'title' => 'Concluído', 'cards' => []]
        ];
        
        $stmt = $db->prepare("INSERT INTO boards (id, name, columns, trash) VALUES (?, ?, ?, ?)");
        $stmt->execute([$newId, $name, json_encode($defaultColumns), json_encode([])]);
        
        sendResponse(['success' => true, 'id' => $newId]);
    }
    
    // --- EXCLUIR BOARD ---
    if ($method === 'POST' && $action === 'delete_board') {
        $input = getJsonInput();
        if (isset($input['id'])) {
            $stmt = $db->prepare("DELETE FROM boards WHERE id = ?");
            $stmt->execute([$input['id']]);
            sendResponse(['success' => true]);
        }
        sendResponse(['error' => 'ID inválido'], 400);
    }

    // --- SALVAR BOARD (UPDATE) ---
    if ($method === 'POST' && $action === 'save_board') {
        $input = getJsonInput();
        $boardId = $input['id'] ?? null;
        $data = $input['data'] ?? null;

        if ($boardId && $data) {
            try {
                $columns = json_encode($data['columns']);
                $trash = json_encode($data['trash'] ?? []);
                
                $stmt = $db->prepare("UPDATE boards SET name = ?, columns = ?, trash = ? WHERE id = ?");
                $stmt->execute([$data['name'], $columns, $trash, $boardId]);
                
                sendResponse(['success' => true]);
            } catch (Exception $e) {
                logError("Erro ao salvar board: " . $e->getMessage());
                sendResponse(['error' => $e->getMessage()], 500);
            }
        } else {
            sendResponse(['error' => 'Dados incompletos'], 400);
        }
    }

    // --- SALVAR UM CARTÃO (UPDATE) ---
    if ($method === 'POST' && $action === 'save_card') {
        $input = getJsonInput();
        $cardId = $input['id'] ?? null;
        $boardId = $input['boardId'] ?? null;
        $cardData = $input['cardData'] ?? null;

        if ($cardId && $boardId && $cardData) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT columns FROM boards WHERE id = ?");
                $stmt->execute([$boardId]);
                $board = $stmt->fetch();
                if (!$board) throw new Exception("Projeto de origem não encontrado");
                
                $columns = json_decode($board['columns'], true);
                $found = false;
                foreach ($columns as &$col) {
                    if (!isset($col['cards'])) continue;
                    foreach ($col['cards'] as &$card) {
                        if ($card['id'] === $cardId) {
                            // Atualiza apenas os campos permitidos
                            $allowedFields = ['title', 'description', 'links', 'tags', 'priority', 'updatedAt', 'uniqueId'];
                            foreach ($allowedFields as $field) {
                                if (isset($cardData[$field])) {
                                    $card[$field] = $cardData[$field];
                                }
                            }
                            $found = true;
                            break;
                        }
                    }
                    if ($found) break;
                }

                if ($found) {
                    $stmt = $db->prepare("UPDATE boards SET columns = ? WHERE id = ?");
                    $stmt->execute([json_encode($columns), $boardId]);
                    $db->commit();
                    sendResponse(['success' => true]);
                } else {
                    throw new Exception("Cartão não encontrado no projeto");
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                logError("Erro ao salvar cartão: " . $e->getMessage());
                sendResponse(['error' => $e->getMessage()], 500);
            }
        } else {
            sendResponse(['error' => 'Dados incompletos'], 400);
        }
    }

    // --- DELETAR UM CARTÃO (MOVER PARA LIXEIRA) ---
    if ($method === 'POST' && $action === 'delete_card') {
        $input = getJsonInput();
        $cardId = $input['id'] ?? null;
        $boardId = $input['boardId'] ?? null;

        if ($cardId && $boardId) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT columns, trash FROM boards WHERE id = ?");
                $stmt->execute([$boardId]);
                $board = $stmt->fetch();
                if (!$board) throw new Exception("Projeto não encontrado");
                
                $columns = json_decode($board['columns'], true);
                $trash = json_decode($board['trash'] ?? '[]', true);
                
                $cardToDelete = null;
                $originalColId = null;
                foreach ($columns as &$col) {
                    if (!isset($col['cards'])) continue;
                    foreach ($col['cards'] as $idx => $card) {
                        if ($card['id'] === $cardId) {
                            $cardToDelete = array_splice($col['cards'], $idx, 1)[0];
                            $originalColId = $col['id'];
                            break;
                        }
                    }
                    if ($cardToDelete) break;
                }

                if ($cardToDelete) {
                    $cardToDelete['deletedAt'] = date('c');
                    $cardToDelete['originalColumn'] = $originalColId;
                    $trash[] = $cardToDelete;
                    
                    $stmt = $db->prepare("UPDATE boards SET columns = ?, trash = ? WHERE id = ?");
                    $stmt->execute([json_encode($columns), json_encode($trash), $boardId]);
                    $db->commit();
                    sendResponse(['success' => true]);
                } else {
                    throw new Exception("Cartão não encontrado");
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                logError("Erro ao deletar cartão: " . $e->getMessage());
                sendResponse(['error' => $e->getMessage()], 500);
            }
        } else {
            sendResponse(['error' => 'Dados incompletos'], 400);
        }
    }

    // --- MOVER CARTÃO ENTRE BOARDS ---
    if ($method === 'POST' && $action === 'move_card') {
        $input = getJsonInput();
        $cardId = $input['cardId'] ?? null;
        $fromBoardId = $input['fromBoardId'] ?? null;
        $toBoardId = $input['toBoardId'] ?? null;
        $toColumnId = $input['toColumnId'] ?? null;
        $toColumnTitle = $input['toColumnTitle'] ?? null;

        if ($cardId && $fromBoardId && $toBoardId) {
            try {
                $db->beginTransaction();

                // 1. Pega o board de origem
                $stmt = $db->prepare("SELECT columns FROM boards WHERE id = ?");
                $stmt->execute([$fromBoardId]);
                $fromBoard = $stmt->fetch();
                if (!$fromBoard) throw new Exception("Board de origem não encontrado");
                $fromColumns = json_decode($fromBoard['columns'], true);

                $cardToMove = null;
                foreach ($fromColumns as &$col) {
                    $idx = -1;
                    if (isset($col['cards']) && is_array($col['cards'])) {
                        foreach ($col['cards'] as $i => $c) {
                            if ($c['id'] === $cardId) { $idx = $i; break; }
                        }
                    }
                    if ($idx !== -1) {
                        $cardToMove = array_splice($col['cards'], $idx, 1)[0];
                        break;
                    }
                }

                if (!$cardToMove) throw new Exception("Cartão não encontrado no board de origem");

                // Atualiza timestamp
                $cardToMove['updatedAt'] = date('c');

                // 2. Pega o board de destino
                $stmt->execute([$toBoardId]);
                $toBoard = $stmt->fetch();
                if (!$toBoard) throw new Exception("Board de destino não encontrado");
                $toColumns = json_decode($toBoard['columns'], true);

                // 3. Adiciona o cartão no board de destino
                $targetColId = $toColumnId;
                if (!$targetColId && $toColumnTitle) {
                    foreach ($toColumns as $col) {
                        if (trim($col['title']) === trim($toColumnTitle)) {
                            $targetColId = $col['id'];
                            break;
                        }
                    }
                }

                if ($targetColId) {
                    $foundCol = false;
                    foreach ($toColumns as &$col) {
                        if ($col['id'] === $targetColId) {
                            if (!isset($col['cards'])) $col['cards'] = [];
                            array_unshift($col['cards'], $cardToMove);
                            $foundCol = true;
                            break;
                        }
                    }
                    if (!$foundCol) array_unshift($toColumns[0]['cards'], $cardToMove);
                } else {
                    // Adiciona na primeira coluna se não especificada
                    if (!isset($toColumns[0]['cards'])) $toColumns[0]['cards'] = [];
                    array_unshift($toColumns[0]['cards'], $cardToMove);
                }

                // 4. Salva ambos
                $upd = $db->prepare("UPDATE boards SET columns = ? WHERE id = ?");
                $upd->execute([json_encode($fromColumns), $fromBoardId]);
                $upd->execute([json_encode($toColumns), $toBoardId]);

                $db->commit();
                sendResponse(['success' => true]);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                logError("Erro ao mover cartão: " . $e->getMessage());
                sendResponse(['error' => $e->getMessage()], 500);
            }
        } else {
            sendResponse(['error' => 'Dados incompletos'], 400);
        }
    }

} catch (Exception $e) {
    logError("Erro Geral API: " . $e->getMessage());
    sendResponse(['error' => $e->getMessage()], 500);
}
