<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

clearstatcache(true);

 $rootRealPath = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs')), '/');
 $scriptPath = rtrim(str_replace('\\', '/', __DIR__), '/');

 $desktopPath = $scriptPath . '/Desktop';
if (!is_dir($desktopPath)) {
    mkdir($desktopPath, 0777, true);
}
 $bgUploadDir = $scriptPath . '/bg_uploads';
if (!is_dir($bgUploadDir)) {
    mkdir($bgUploadDir, 0777, true);
}
 $editDir = $scriptPath . '/edit';
if (!is_dir($editDir)) {
    mkdir($editDir, 0777, true);
}

function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $path = $dir. "/" .$object;
                if (is_dir($path) && !is_link($path)) {
                    rrmdir($path);
                } else {
                    @chmod($path, 0666);
                    @unlink($path);
                }
            }
        }
        @chmod($dir, 0777);
        @rmdir($dir);
    }
}

function rcopy($src, $dst) {
    if (is_dir($src)) {
        mkdir($dst);
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") rcopy("$src/$file", "$dst/$file");
        }
    } else if (file_exists($src)) {
        copy($src, $dst);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'get_content') {
        $file = rtrim(str_replace('\\', '/', realpath($_POST['path'] ?? '')), '/');
        if (stripos($file, $rootRealPath) === 0 && is_file($file)) {
            $response = ['success' => true, 'content' => file_get_contents($file), 'filename' => basename($file)];
        }
    } elseif ($action === 'save_file') {
        $file = rtrim(str_replace('\\', '/', realpath($_POST['path'] ?? '')), '/');
        if (stripos($file, $rootRealPath) === 0 && is_file($file)) {
            $backupFile = $editDir . '/' . basename($file) . '_' . date('Y-m-d_H-i-s');
            copy($file, $backupFile);
            file_put_contents($file, $_POST['content'] ?? '');
            $response = ['success' => true, 'message' => 'File saved'];
        }
    } elseif ($action === 'delete') {
        $target = str_replace('\\', '/', $_POST['path'] ?? '');
        $target = rtrim($target, '/');
        
        $rootNorm = str_replace('\\', '/', $rootRealPath);
        $rootNorm = rtrim($rootNorm, '/');

        if (stripos($target, $rootNorm) === 0 && $target !== $rootNorm) {
            if (is_file($target)) {
                @chmod($target, 0666);
                if (@unlink($target)) {
                    $response = ['success' => true];
                } else {
                    $response = ['success' => false, 'message' => 'Permission denied'];
                }
            } elseif (is_dir($target)) {
                @chmod($target, 0777);
                rrmdir($target);
                if (!file_exists($target)) {
                    $response = ['success' => true];
                } else {
                    $response = ['success' => false, 'message' => 'Cannot delete folder (Locked)'];
                }
            }
        } else {
            $response = ['success' => false, 'message' => 'Invalid path'];
        }
    } elseif ($action === 'create_file') {
        $dir = rtrim(str_replace('\\', '/', realpath($_POST['path'] ?? '')), '/');
        $name = $_POST['name'] ?? 'new_file.txt';
        $newFilePath = $dir . '/' . $name;
        if (stripos($dir, $rootRealPath) === 0 && !file_exists($newFilePath)) {
            file_put_contents($newFilePath, '');
            $response = ['success' => true];
        }
    } elseif ($action === 'create_folder') {
        $dir = rtrim(str_replace('\\', '/', realpath($_POST['path'] ?? '')), '/');
        $name = $_POST['name'] ?? 'new_folder';
        $newDirPath = $dir . '/' . $name;
        if (stripos($dir, $rootRealPath) === 0 && !file_exists($newDirPath)) {
            mkdir($newDirPath, 0777, true);
            $response = ['success' => true];
        }
    } elseif ($action === 'rename') {
        $target = rtrim(str_replace('\\', '/', realpath($_POST['path'] ?? '')), '/');
        $newName = trim($_POST['new_name'] ?? '');
        $newPath = dirname($target) . '/' . $newName;
        if (stripos($target, $rootRealPath) === 0 && file_exists($target) && $newName !== '') {
            if (rename($target, $newPath)) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'message' => 'Rename failed'];
            }
        }
    } elseif ($action === 'copy' || $action === 'cut') {
        $source = rtrim(str_replace('\\', '/', realpath($_POST['source'] ?? '')), '/');
        $destDir = rtrim(str_replace('\\', '/', realpath($_POST['dest_dir'] ?? '')), '/');
        $dest = $destDir . '/' . basename($source);
        
        if (stripos($source, $rootRealPath) === 0 && stripos($destDir, $rootRealPath) === 0 && file_exists($source) && $source !== $dest) {
            if (is_dir($source)) rcopy($source, $dest);
            else copy($source, $dest);
            
            if ($action === 'cut') {
                if (is_dir($source)) rrmdir($source);
                else unlink($source);
            }
            $response = ['success' => true];
        }
    } elseif ($action === 'move') {
        $source = rtrim(str_replace('\\', '/', realpath($_POST['source'] ?? '')), '/');
        $destDir = rtrim(str_replace('\\', '/', realpath($_POST['dest_dir'] ?? '')), '/');
        $dest = $destDir . '/' . basename($source);
        if (stripos($source, $rootRealPath) === 0 && stripos($destDir, $rootRealPath) === 0 && file_exists($source)) {
            if ($source === $dest) {
                $response = ['success' => true, 'message' => 'Same location'];
            } elseif (stripos($dest, $source . '/') === 0) {
                $response = ['success' => false, 'message' => 'Cannot move folder into itself'];
            } else {
                if (rename($source, $dest)) {
                    $response = ['success' => true];
                } else {
                    $response = ['success' => false, 'message' => 'Move failed'];
                }
            }
        }
    } elseif ($action === 'get_properties') {
        $target = rtrim(str_replace('\\', '/', realpath($_POST['path'] ?? '')), '/');
        if (stripos($target, $rootRealPath) === 0 && file_exists($target)) {
            $stat = stat($target);
            $type = is_dir($target) ? 'Folder' : 'File';
            $size = is_dir($target) ? '-' : formatSizeUnits($stat['size']);
            $perms = substr(sprintf('%o', fileperms($target)), -4);
            $response = [
                'success' => true,
                'name' => basename($target),
                'path' => $target,
                'type' => $type,
                'size' => $size,
                'modified' => date('Y-m-d H:i:s', $stat['mtime']),
                'perms' => $perms
            ];
        }
    } elseif ($action === 'get_preview') {
        $target = rtrim(str_replace('\\', '/', realpath($_POST['path'] ?? '')), '/');
        if (stripos($target, $rootRealPath) === 0 && is_file($target)) {
            $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            $images = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $web = ['html', 'htm', 'php'];
            $texts = ['txt', 'log', 'css', 'js', 'json', 'xml', 'md'];
            if (in_array($ext, $images)) {
                $url = '/' . ltrim(str_replace($rootRealPath, '', $target), '/');
                $response = ['success' => true, 'type' => 'image', 'url' => $url];
            } elseif (in_array($ext, $web)) {
                $url = '/' . ltrim(str_replace($rootRealPath, '', $target), '/');
                $response = ['success' => true, 'type' => 'web', 'url' => $url];
            } elseif (in_array($ext, $texts)) {
                $content = file_get_contents($target);
                $response = ['success' => true, 'type' => 'text', 'content' => $content];
            } else {
                $response = ['success' => true, 'type' => 'unsupported', 'msg' => 'No preview available for this file type.'];
            }
        }
    } elseif ($action === 'upload_bg') {
        if (isset($_FILES['bg_file'])) {
            $file = $_FILES['bg_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $filename = 'bg_' . date('Ymd_His') . '.' . $ext;
                $dest = $bgUploadDir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $url = '/' . ltrim(str_replace($rootRealPath, '', $dest), '/');
                    $response = ['success' => true, 'url' => $url];
                } else {
                    $response = ['success' => false, 'message' => 'Move failed'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid file type'];
            }
        }
    }

    echo json_encode($response);
    exit;
}

function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) { $bytes = number_format($bytes / 1073741824, 2) . ' GB'; }
    elseif ($bytes >= 1048576) { $bytes = number_format($bytes / 1048576, 2) . ' MB'; }
    elseif ($bytes >= 1024) { $bytes = number_format($bytes / 1024, 2) . ' KB'; }
    elseif ($bytes > 1) { $bytes = $bytes . ' bytes'; }
    elseif ($bytes == 1) { $bytes = $bytes . ' byte'; }
    else { $bytes = '0 bytes'; }
    return $bytes;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    
    $reqPath = $_GET['path'] ?? $rootRealPath;
    $realPath = rtrim(str_replace('\\', '/', realpath($reqPath)), '/');
    
    if ($realPath === '' || stripos($realPath, $rootRealPath) !== 0) {
        $realPath = $rootRealPath;
    }

    $allItems = [];

    if (is_dir($realPath)) {
        $items = scandir($realPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $realPath . '/' . $item;
            if (is_dir($fullPath)) {
                $allItems[] = ['name' => $item, 'path' => $fullPath, 'type' => 'folder'];
            } else {
                $allItems[] = ['name' => $item, 'path' => $fullPath, 'type' => 'file', 'url' => '/' . ltrim(str_replace($rootRealPath, '', $fullPath), '/')];
            }
        }
    }

    usort($allItems, function($a, $b) {
        if ($a['type'] === $b['type']) {
            return strnatcasecmp($a['name'], $b['name']);
        }
        return $a['type'] === 'folder' ? -1 : 1;
    });

    echo json_encode([
        'path' => $realPath,
        'parent' => dirname($realPath),
        'isRoot' => ($realPath === $rootRealPath),
        'items' => array_values($allItems)
    ]);
    exit;
}

 $rootName = basename($rootRealPath);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OS File Explorer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-tap-highlight-color: transparent; overflow: hidden; transition: background 0.3s ease; }
        .window-shadow { box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .glass { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); background: rgba(243, 243, 243, 0.9); }
        .no-select { user-select: none; -webkit-user-select: none; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2); border-radius: 3px; }
        .menu-item:hover { background-color: #e5e5e5; }
        .file-item.selected { background-color: #bfdbfe; }
        kbd { background-color: #eee; border: 1px solid #ccc; border-radius: 3px; padding: 2px 5px; font-size: 11px; font-family: monospace; }
        .explorer-window { position: absolute; display: flex; flex-direction: column; background: #f3f3f3; border-radius: 8px; overflow: hidden; }
        .selection-box { position: absolute; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.7); pointer-events: none; z-index: 5; }
        .droplet {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #00bcf2, #0078d4);
            border-radius: 50% 50% 50% 10%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3), inset 0 1px 2px rgba(255,255,255,0.4);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: transform 0.2s; animation: float 3s ease-in-out infinite;
        }
        .droplet:hover { transform: scale(1.1) translateY(-2px); }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
        .desktop-icon { position: absolute; touch-action: none; }
        #desktop { z-index: 0; }
        #windowsContainer { z-index: 10; }
    </style>
</head>
<body class="h-screen w-screen relative" style="background: linear-gradient(135deg, #0078d4 0%, #00bcf2 50%, #005a9e 100%);">

    <div id="desktop" class="absolute top-0 left-0 right-0 bottom-0 p-4 overflow-hidden">
        <div id="htdocsIcon" class="desktop-icon flex flex-col items-center w-24 p-2 rounded hover:bg-white/20 active:bg-white/30 cursor-pointer no-select" style="left: 20px; top: 20px;" data-action="open-root">
            <svg class="w-12 h-12" viewBox="0 0 24 24" fill="#f5c542" stroke="#d4a818" stroke-width="0.5"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
            <span class="text-white text-xs mt-1 text-center drop-shadow-md">Root</span>
        </div>
        <div id="settingsIcon" class="desktop-icon flex flex-col items-center w-24 p-2 rounded hover:bg-white/20 active:bg-white/30 cursor-pointer no-select" style="left: 20px; top: 130px;">
            <svg class="w-12 h-12" viewBox="0 0 24 24" fill="#9ca3af" stroke="#6b7280" stroke-width="0.5"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.63,9.08,2.67,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>
            <span class="text-white text-xs mt-1 text-center drop-shadow-md">Settings</span>
        </div>
        <div id="musicIcon" class="desktop-icon flex flex-col items-center w-24 p-2 rounded hover:bg-white/20 active:bg-white/30 cursor-pointer no-select" style="left: 20px; top: 240px;">
            <svg class="w-12 h-12" viewBox="0 0 24 24" fill="#e4010b" stroke="#a30008" stroke-width="0.5"><path d="M12,3v10.55c-0.59-0.34-1.27-0.55-2-0.55c-2.21,0-4,1.79-4,4s1.79,4,4,4s4-1.79,4-4V7h4V3H12z"/></svg>
            <span class="text-white text-xs mt-1 text-center drop-shadow-md">Music</span>
        </div>
    </div>

    <div class="absolute top-4 right-4 z-[100] flex flex-col items-end gap-2">
        <div id="clockWidget" class="text-white bg-black/40 backdrop-blur-md p-3 rounded-xl text-right shadow-lg pointer-events-none w-full">
            <div id="clock" class="text-lg font-bold tracking-wide">00:00:00</div>
            <div id="date" class="text-xs opacity-80 mt-1">-</div>
        </div>
        <button type="button" onclick="toggleShortcutsModal(true)" class="bg-black/40 text-white p-2 rounded-lg backdrop-blur-md hover:bg-black/60 text-sm w-full flex items-center justify-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
            คีย์ลัด
        </button>
    </div>

    <div id="windowsContainer"></div>

    <div id="dropletContainer" class="hidden absolute bottom-4 right-4 z-[90] flex flex-row-reverse gap-3"></div>

    <div id="contextMenu" class="hidden absolute z-[110] w-56 bg-white rounded-md shadow-xl py-1 no-select">
        <div id="menuCreateFile" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="createItem('file')">สร้างไฟล์</div>
        <div id="menuCreateFolder" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="createItem('folder')">สร้างโฟลเดอร์</div>
        <div id="menuEdit" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="editFile()">แก้ไข</div>
        <div id="menuPreview" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="previewFile()">พรีวิว</div>
        <div id="menuProperties" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="showProperties()">ข้อมูล (Properties)</div>
        <div id="menuSendToDesktop" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="sendToDesktop()">ส่งไปยังเดสก์ท็อป</div>
        <div id="menuSendToExplorer" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="sendToExplorer()">ย้ายไปยังหน้าต่างปัจจุบัน</div>
        <div id="menuRename" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="renameItems()">เปลี่ยนชื่อ (Rename)</div>
        <div id="menuCopy" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="copyItem()">คัดลอก (Copy)</div>
        <div id="menuCut" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="cutItem()">ตัด (Cut)</div>
        <div id="menuPaste" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="pasteItem()">วาง (Paste)</div>
        <div id="menuDelete" class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer hidden" onclick="showDeleteModal()">ลบ</div>
        <div class="border-t border-gray-100 my-1"></div>
        <div class="menu-item px-4 py-2 text-sm text-gray-700 cursor-pointer" onclick="location.reload()">รีหน้าเว็บ (Refresh)</div>
    </div>

    <div id="shortcutsModal" class="hidden fixed inset-0 bg-black/50 z-[120] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl p-6 max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">คีย์ลัด (Keyboard Shortcuts)</h3>
                <button type="button" onclick="toggleShortcutsModal(false)" class="text-gray-500 hover:text-black text-xl w-8 h-8 flex items-center justify-center rounded hover:bg-gray-100">✕</button>
            </div>
            <ul class="space-y-3 text-sm text-gray-600">
                <li class="flex justify-between"><span>เปลี่ยนชื่อรายการ</span> <kbd>F2</kbd></li>
                <li class="flex justify-between"><span>ลบรายการ</span> <kbd>Delete</kbd></li>
                <li class="flex justify-between"><span>คัดลอก</span> <kbd>Ctrl</kbd> + <kbd>C</kbd></li>
                <li class="flex justify-between"><span>ตัด</span> <kbd>Ctrl</kbd> + <kbd>X</kbd></li>
                <li class="flex justify-between"><span>วาง</span> <kbd>Ctrl</kbd> + <kbd>V</kbd></li>
                <li class="flex justify-between"><span>รีเฟรชหน้า</span> <kbd>F5</kbd></li>
                <li class="flex justify-between"><span>บันทึกไฟล์ (ในหน้าแก้ไข)</span> <kbd>Ctrl</kbd> + <kbd>S</kbd></li>
            </ul>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="toggleShortcutsModal(false)" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">เข้าใจแล้ว</button>
            </div>
        </div>
    </div>

    <script>
        const rootRealPath = '<?php echo $rootRealPath; ?>';
        const desktopPath = '<?php echo $desktopPath; ?>';
        const rootName = '<?php echo $rootName; ?>';
        let winCount = 0;
        const openWindows = {};
        let activeWinId = null;
        let selectedItems = [];
        let clipboard = null;
        let currentContextPath = '';
        let currentContextType = '';
        let currentContextWinId = null;
        let createType = '';

        function toggleShortcutsModal(state) {
            document.getElementById('shortcutsModal').classList.toggle('hidden', !state);
        }

        function saveState() {
            const state = Object.keys(openWindows).map(id => {
                const w = openWindows[id];
                if (w.type === 'modal' || w.type === 'app') return null;
                const rect = w.el.getBoundingClientRect();
                return {
                    id: id, path: w.path, type: w.type,
                    x: rect.left, y: rect.top, w: w.el.offsetWidth, h: w.el.offsetHeight,
                    z: w.el.style.zIndex, isMax: w.el.dataset.isMax === 'true', isMin: w.el.classList.contains('hidden')
                };
            }).filter(Boolean);
            localStorage.setItem('os_windows', JSON.stringify(state));
        }

        function loadState() {
            const stateStr = localStorage.getItem('os_windows');
            if (stateStr) {
                const state = JSON.parse(stateStr);
                if (state.length > 0) {
                    state.forEach(w => {
                        if (w.type === 'explorer') {
                            createWindow(w.path, w.id, w.x, w.y, w.w, w.h, w.z, w.isMax);
                            if (w.isMin) minimizeWindow(w.id);
                        }
                    });
                    return true;
                }
            }
            return false;
        }

        function createWindow(path, id = null, x = null, y = null, w = null, h = null, z = null, isMax = false) {
            if (!id) {
                winCount++;
                id = 'win-' + winCount;
            } else {
                const num = parseInt(id.split('-')[1]);
                if (num > winCount) winCount = num;
            }

            if (x === null) {
                if (window.innerWidth < 768) {
                    x = 0; y = 0; w = window.innerWidth; h = window.innerHeight;
                } else {
                    x = 50 + (winCount * 30); y = 50 + (winCount * 30); w = 800; h = 500;
                }
            }
            if (z === null) z = 40 + winCount;

            const winEl = document.createElement('div');
            winEl.className = 'explorer-window window-shadow';
            winEl.id = id;
            winEl.style.left = x + 'px'; winEl.style.top = y + 'px';
            winEl.style.width = w + 'px'; winEl.style.height = h + 'px';
            winEl.style.zIndex = z;
            winEl.dataset.isMax = isMax ? 'true' : 'false';

            winEl.innerHTML = `
                <div class="window-header h-10 bg-[#f3f3f3] flex items-center justify-between px-4 cursor-move no-select border-b border-gray-200">
                    <div class="flex items-center text-gray-800 font-medium text-sm truncate">
                        <svg class="w-4 h-4 mr-2 flex-shrink-0" viewBox="0 0 24 24" fill="#f5c542"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        <span class="path-span truncate">Loading...</span>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button type="button" class="min-btn w-8 h-6 flex items-center justify-center hover:bg-gray-300 text-gray-700 text-xs rounded font-bold">_</button>
                        <button type="button" class="max-btn w-8 h-6 flex items-center justify-center hover:bg-gray-300 text-gray-700 text-xs rounded font-bold">▢</button>
                        <button type="button" class="close-btn w-8 h-6 flex items-center justify-center hover:bg-red-500 hover:text-white text-gray-700 text-xs rounded font-bold">✕</button>
                    </div>
                </div>
                <div class="h-12 bg-[#f3f3f3] border-b border-gray-200 flex items-center px-2 gap-2 no-select">
                    <button type="button" class="back-btn p-2 hover:bg-gray-200 rounded text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </button>
                    <div class="flex-1 bg-white border border-gray-300 rounded px-3 py-1.5 text-sm text-gray-600 overflow-hidden whitespace-nowrap flex items-center gap-1">
                        <span>📁</span><span class="path-url truncate"></span>
                    </div>
                </div>
                <div class="file-grid flex-1 overflow-y-auto p-2 sm:p-4 bg-white custom-scroll relative" data-win-id="${id}" data-path="">
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2">
                        <div class="col-span-full text-center text-gray-500 py-10">กำลังโหลด...</div>
                    </div>
                </div>
                <div class="h-7 bg-[#f3f3f3] border-t border-gray-200 flex items-center px-4 text-xs text-gray-500 no-select">
                    <span class="footer-count">0 รายการ</span>
                </div>
            `;
            document.getElementById('windowsContainer').appendChild(winEl);

            openWindows[id] = { el: winEl, path: path, type: 'explorer', prevRect: null };
            setupWindowEvents(id);
            loadDirectory(id, path);
            updateDroplets();
            saveState();

            if (isMax) maximizeWindow(id);
            return id;
        }

        function createAppWindow(id, title, contentHtml, width = 500, height = 400) {
            if (openWindows[id]) {
                focusWindow(id);
                openWindows[id].el.classList.remove('hidden');
                updateDroplets();
                return;
            }
            
            winCount++;
            const winEl = document.createElement('div');
            winEl.className = 'explorer-window window-shadow';
            winEl.id = id;
            
            if (window.innerWidth < 768) {
                winEl.style.left = '0px'; winEl.style.top = '0px';
                winEl.style.width = '100vw'; winEl.style.height = '100vh';
            } else {
                winEl.style.left = (100 + winCount * 20) + 'px'; winEl.style.top = (100 + winCount * 20) + 'px';
                winEl.style.width = width + 'px'; winEl.style.height = height + 'px';
            }
            winEl.style.zIndex = 40 + winCount;
            winEl.dataset.isMax = 'false';

            winEl.innerHTML = `
                <div class="window-header h-10 bg-[#f3f3f3] flex items-center justify-between px-4 cursor-move no-select border-b border-gray-200">
                    <div class="flex items-center text-gray-800 font-medium text-sm truncate">
                        <span class="truncate">${title}</span>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button type="button" class="min-btn w-8 h-6 flex items-center justify-center hover:bg-gray-300 text-gray-700 text-xs rounded font-bold">_</button>
                        <button type="button" class="max-btn w-8 h-6 flex items-center justify-center hover:bg-gray-300 text-gray-700 text-xs rounded font-bold">▢</button>
                        <button type="button" class="close-btn w-8 h-6 flex items-center justify-center hover:bg-red-500 hover:text-white text-gray-700 text-xs rounded font-bold">✕</button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto bg-white custom-scroll">${contentHtml}</div>
            `;
            
            document.getElementById('windowsContainer').appendChild(winEl);
            openWindows[id] = { el: winEl, path: title, type: 'app', prevRect: null };
            setupWindowEvents(id);
            updateDroplets();
        }

        function setupWindowEvents(id) {
            const winData = openWindows[id];
            const winEl = winData.el;
            const header = winEl.querySelector('.window-header');

            winEl.addEventListener('mousedown', () => focusWindow(id));
            header.addEventListener('mousedown', (e) => startWindowDrag(e, id));
            header.addEventListener('touchstart', (e) => startWindowDrag(e, id), { passive: false });

            winEl.querySelector('.close-btn').onclick = (e) => { e.stopPropagation(); closeWindow(id); };
            winEl.querySelector('.min-btn').onclick = (e) => { e.stopPropagation(); minimizeWindow(id); };
            winEl.querySelector('.max-btn').onclick = (e) => { e.stopPropagation(); maximizeWindow(id); };
            
            if (winData.type === 'explorer') {
                winEl.querySelector('.back-btn').onclick = () => {
                    const parent = dirname(winData.path);
                    if (parent && parent !== winData.path) loadDirectory(id, parent);
                };

                const grid = winEl.querySelector('.file-grid');
                grid.addEventListener('mousedown', (e) => handleGridMouseDown(e, id));
                grid.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    currentContextWinId = id;
                    const item = e.target.closest('.file-item');
                    if (item) {
                        if (!item.classList.contains('selected')) {
                            document.querySelectorAll('.file-item.selected').forEach(i => i.classList.remove('selected'));
                            item.classList.add('selected');
                            selectedItems = [{ path: item.dataset.path, type: item.dataset.type, winId: id }];
                        }
                        currentContextPath = item.dataset.path;
                        currentContextType = item.dataset.type;
                        showContextMenu(e.clientX, e.clientY, 'item');
                    } else {
                        document.querySelectorAll('.file-item.selected').forEach(i => i.classList.remove('selected'));
                        selectedItems = [];
                        currentContextPath = grid.dataset.path;
                        currentContextType = 'empty';
                        showContextMenu(e.clientX, e.clientY, 'empty');
                    }
                });
            }
        }

        function startWindowDrag(e, id) {
            if (e.target.tagName === 'BUTTON' || e.target.closest('BUTTON')) return;
            if (openWindows[id].el.dataset.isMax === 'true') return;
            
            focusWindow(id);
            const winEl = openWindows[id].el;
            const rect = winEl.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            
            const offsetX = clientX - rect.left;
            const offsetY = clientY - rect.top;

            function move(e) {
                const cx = e.touches ? e.touches[0].clientX : e.clientX;
                const cy = e.touches ? e.touches[0].clientY : e.clientY;
                let nx = cx - offsetX;
                let ny = cy - offsetY;
                if (nx < 0) nx = 0;
                if (ny < 0) ny = 0;
                if (nx + winEl.offsetWidth > window.innerWidth) nx = window.innerWidth - winEl.offsetWidth;
                if (ny + winEl.offsetHeight > window.innerHeight) ny = window.innerHeight - winEl.offsetHeight;
                winEl.style.left = nx + 'px';
                winEl.style.top = ny + 'px';
                if (e.touches) e.preventDefault();
            }

            function up() {
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
                document.removeEventListener('touchmove', move);
                document.removeEventListener('touchend', up);
                saveState();
            }

            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('touchend', up);
        }

        function focusWindow(id) {
            activeWinId = id;
            let maxZ = 40;
            Object.values(openWindows).forEach(w => {
                if (parseInt(w.el.style.zIndex) > maxZ) maxZ = parseInt(w.el.style.zIndex);
            });
            openWindows[id].el.style.zIndex = maxZ + 1;
            openWindows[id].el.classList.remove('opacity-60');
        }

        function closeWindow(id) {
            delete openWindows[id];
            document.getElementById(id).remove();
            updateDroplets();
            saveState();
        }

        function minimizeWindow(id) {
            openWindows[id].el.classList.add('hidden');
            updateDroplets();
            saveState();
        }

        function maximizeWindow(id) {
            const winEl = openWindows[id].el;
            if (winEl.dataset.isMax === 'true') {
                winEl.dataset.isMax = 'false';
                const r = openWindows[id].prevRect;
                winEl.style.left = r.left + 'px'; winEl.style.top = r.top + 'px';
                winEl.style.width = r.w + 'px'; winEl.style.height = r.h + 'px';
            } else {
                openWindows[id].prevRect = { left: winEl.offsetLeft, top: winEl.offsetTop, w: winEl.offsetWidth, h: winEl.offsetHeight };
                winEl.dataset.isMax = 'true';
                winEl.style.left = '0px'; winEl.style.top = '0px';
                winEl.style.width = '100vw'; winEl.style.height = '100vh';
            }
            saveState();
        }

        function updateDroplets() {
            const container = document.getElementById('dropletContainer');
            container.innerHTML = '';
            let hasMinimized = false;
            Object.keys(openWindows).forEach(id => {
                const w = openWindows[id];
                if (w.el.classList.contains('hidden')) {
                    hasMinimized = true;
                    const drop = document.createElement('div');
                    drop.className = 'droplet group relative';
                    drop.innerHTML = `
                        <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        <div class="absolute bottom-full mb-2 right-0 bg-black/80 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">${w.path.split('/').pop()}</div>
                    `;
                    drop.onclick = () => {
                        w.el.classList.remove('hidden');
                        focusWindow(id);
                        updateDroplets();
                        saveState();
                    };
                    drop.oncontextmenu = (e) => { e.preventDefault(); closeWindow(id); };
                    container.appendChild(drop);
                }
            });
            container.classList.toggle('hidden', !hasMinimized);
        }

        function loadDirectory(winId, path) {
            const win = openWindows[winId];
            if (!win) return;
            win.path = path;
            const grid = win.el.querySelector('.file-grid');
            grid.dataset.path = path;
            grid.innerHTML = '<div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2"><div class="col-span-full text-center text-gray-500 py-10">กำลังโหลด...</div></div>';
            
            fetch(`?ajax=list&path=${encodeURIComponent(path)}`)
                .then(res => res.json())
                .then(data => {
                    let displayPath = data.path.replace(rootRealPath, rootName);
                    win.el.querySelector('.path-span').textContent = displayPath;
                    win.el.querySelector('.path-url').textContent = displayPath;
                    
                    const backBtn = win.el.querySelector('.back-btn');
                    if (data.isRoot) backBtn.classList.add('opacity-30', 'pointer-events-none');
                    else backBtn.classList.remove('opacity-30', 'pointer-events-none');

                    let html = '<div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2">';
                    
                    data.items.forEach(item => {
                        let iconSvg = '';
                        if (item.type === 'folder') {
                            iconSvg = `<svg class="w-10 h-10 sm:w-12 sm:h-12 mb-1 group-hover:scale-105 transition-transform" viewBox="0 0 24 24" fill="#f5c542" stroke="#d4a818" stroke-width="0.5"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>`;
                            html += `<div class="file-item flex flex-col items-center p-2 sm:p-3 rounded hover:bg-blue-50 active:bg-blue-100 group cursor-pointer" data-path="${item.path}" data-type="folder" ondblclick="openItem('${item.path}', 'folder', '${winId}')" onclick="selectItem(event, this, '${winId}')">${iconSvg}<span class="text-[10px] sm:text-xs text-gray-800 text-center break-all line-clamp-2">${item.name}</span></div>`;
                        } else {
                            let ext = item.name.split('.').pop().toLowerCase();
                            let fileColor = '#95a5a6';
                            if (ext == 'php') fileColor = '#8993be';
                            if (['html', 'htm'].includes(ext)) fileColor = '#e44d26';
                            if (ext == 'js') fileColor = '#f0db4f';
                            if (ext == 'css') fileColor = '#2965f1';
                            if (['jpg', 'png', 'gif', 'jpeg'].includes(ext)) fileColor = '#16a085';
                            if (['txt', 'log'].includes(ext)) fileColor = '#7f8c8d';
                            if (['zip', 'rar', '7z'].includes(ext)) fileColor = '#f39c12';
                            
                            iconSvg = `<svg class="w-10 h-10 sm:w-12 sm:h-12 mb-1 group-hover:scale-105 transition-transform" viewBox="0 0 24 24" fill="${fileColor}" stroke="#bdc3c7" stroke-width="0.5"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z" opacity="0.4"/><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>`;
                            html += `<div class="file-item flex flex-col items-center p-2 sm:p-3 rounded hover:bg-blue-50 active:bg-blue-100 group cursor-pointer" data-path="${item.path}" data-type="file" ondblclick="openItem('${item.url}', 'file', '${winId}')" onclick="selectItem(event, this, '${winId}')">${iconSvg}<span class="text-[10px] sm:text-xs text-gray-800 text-center break-all line-clamp-2">${item.name}</span></div>`;
                        }
                    });

                    if (data.items.length === 0) html += '<div class="col-span-full text-center text-gray-500 py-10">โฟลเดอร์นี้ว่างเปล่า</div>';
                    html += '</div>';
                    grid.innerHTML = html;
                    
                    win.el.querySelector('.footer-count').textContent = `${data.items.length} รายการ`;
                    saveState();
                })
                .catch(err => {
                    grid.innerHTML = '<div class="col-span-full text-center text-red-500 py-10">โหลดข้อมูลไม่สำเร็จ</div>';
                });
        }

        function loadDesktop() {
            fetch(`?ajax=list&path=${encodeURIComponent(desktopPath)}`)
                .then(res => res.json())
                .then(data => {
                    const desktop = document.getElementById('desktop');
                    desktop.querySelectorAll('.desktop-dynamic-icon').forEach(icon => icon.remove());

                    let topOffset = 350;
                    const leftOffset = 20;

                    data.items.forEach(item => {
                        let iconSvg = '';
                        if (item.type === 'folder') {
                            iconSvg = `<svg class="w-12 h-12" viewBox="0 0 24 24" fill="#f5c542" stroke="#d4a818" stroke-width="0.5"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>`;
                        } else {
                            let ext = item.name.split('.').pop().toLowerCase();
                            let color = '#95a5a6';
                            if (ext == 'php') color = '#8993be';
                            if (['html', 'htm'].includes(ext)) color = '#e44d26';
                            if (ext == 'js') color = '#f0db4f';
                            if (ext == 'css') color = '#2965f1';
                            if (['jpg', 'png', 'gif', 'jpeg'].includes(ext)) color = '#16a085';
                            if (['txt', 'log'].includes(ext)) color = '#7f8c8d';
                            if (['zip', 'rar', '7z'].includes(ext)) color = '#f39c12';
                            iconSvg = `<svg class="w-12 h-12" viewBox="0 0 24 24" fill="${color}" stroke="#bdc3c7" stroke-width="0.5"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z" opacity="0.4"/><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>`;
                        }

                        const clickAction = item.type === 'folder' ? `openItem('${item.path}', 'folder', 'desktop')` : `openItem('${item.url}', 'file', 'desktop')`;
                        const div = document.createElement('div');
                        div.className = 'desktop-icon desktop-dynamic-icon file-item flex flex-col items-center w-24 p-2 rounded hover:bg-white/20 active:bg-white/30 cursor-pointer no-select';
                        div.style.left = leftOffset + 'px';
                        div.style.top = topOffset + 'px';
                        div.dataset.path = item.path;
                        div.dataset.type = item.type;
                        div.setAttribute('ondblclick', clickAction);
                        div.innerHTML = `${iconSvg}<span class="text-white text-xs mt-1 text-center drop-shadow-md line-clamp-2">${item.name}</span>`;
                        desktop.appendChild(div);

                        topOffset += 110;
                    });
                });
        }

        let activeDesktopIcon = null, dIconStartX, dIconStartY, dIconOrigLeft, dIconOrigTop, dIconHasMoved = false;

        document.getElementById('desktop').addEventListener('mousedown', (e) => {
            const icon = e.target.closest('.desktop-icon');
            if (icon) startDesktopIconDrag(e, icon);
        });
        document.getElementById('desktop').addEventListener('touchstart', (e) => {
            const icon = e.target.closest('.desktop-icon');
            if (icon) startDesktopIconDrag(e, icon);
        }, { passive: false });

        function startDesktopIconDrag(e, icon) {
            activeDesktopIcon = icon;
            const touch = e.touches ? e.touches[0] : e;
            dIconStartX = touch.clientX;
            dIconStartY = touch.clientY;
            dIconOrigLeft = parseInt(activeDesktopIcon.style.left) || 0;
            dIconOrigTop = parseInt(activeDesktopIcon.style.top) || 0;
            dIconHasMoved = false;
            if (e.touches) e.preventDefault();
        }

        function moveDesktopIconDrag(e) {
            if (!activeDesktopIcon) return;
            const touch = e.touches ? e.touches[0] : e;
            const dx = touch.clientX - dIconStartX;
            const dy = touch.clientY - dIconStartY;
            if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
                dIconHasMoved = true;
                activeDesktopIcon.style.left = (dIconOrigLeft + dx) + 'px';
                activeDesktopIcon.style.top = (dIconOrigTop + dy) + 'px';
            }
            if (e.touches) e.preventDefault();
        }

        function endDesktopIconDrag(e) {
            if (!activeDesktopIcon) return;
            if (!dIconHasMoved) {
                if (e.type === 'touchend') {
                    activeDesktopIcon.dispatchEvent(new MouseEvent('dblclick', {}));
                } else if (e.type === 'mouseup') {
                    document.querySelectorAll('.file-item.selected').forEach(i => i.classList.remove('selected'));
                    activeDesktopIcon.classList.add('selected');
                    selectedItems = [{ path: activeDesktopIcon.dataset.path, type: activeDesktopIcon.dataset.type, winId: 'desktop' }];
                    currentContextPath = activeDesktopIcon.dataset.path;
                    currentContextType = activeDesktopIcon.dataset.type;
                    currentContextWinId = 'desktop';
                }
            }
            activeDesktopIcon = null;
        }

        document.addEventListener('mousemove', moveDesktopIconDrag);
        document.addEventListener('touchmove', moveDesktopIconDrag, { passive: false });
        document.addEventListener('mouseup', endDesktopIconDrag);
        document.addEventListener('touchend', endDesktopIconDrag);

        function selectItem(e, el, winId) {
            e.stopPropagation();
            if (e.ctrlKey || e.metaKey) {
                el.classList.toggle('selected');
            } else {
                const grid = el.parentElement;
                grid.querySelectorAll('.file-item.selected').forEach(i => i.classList.remove('selected'));
                el.classList.add('selected');
            }
            selectedItems = [];
            document.querySelectorAll('.file-item.selected').forEach(i => {
                selectedItems.push({ path: i.dataset.path, type: i.dataset.type, winId: winId });
            });
            if (selectedItems.length > 0) {
                currentContextPath = selectedItems[0].path;
                currentContextType = selectedItems[0].type;
                currentContextWinId = winId;
            }
        }

        function openItem(path, type, winId) {
            if (type === 'folder') {
                if (winId === 'desktop') createWindow(path);
                else loadDirectory(winId, path);
            } else {
                window.open(path, '_blank');
            }
        }

        function moveItem(source, destDir, callback) {
            if (!source || !destDir) return;
            if (source === destDir) return;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=move&source=' + encodeURIComponent(source) + '&dest_dir=' + encodeURIComponent(destDir)
            }).then(res => res.json()).then(data => {
                if (data.success) { if (callback) callback(); }
                else alert('ย้ายไม่สำเร็จ: ' + (data.message || ''));
            });
        }

        function sendToDesktop() {
            document.getElementById('contextMenu').classList.add('hidden');
            selectedItems.forEach(item => {
                moveItem(item.path, desktopPath, () => {
                    if (item.winId && openWindows[item.winId]) loadDirectory(item.winId, openWindows[item.winId].path);
                    loadDesktop();
                });
            });
            selectedItems = [];
        }

        function sendToExplorer() {
            document.getElementById('contextMenu').classList.add('hidden');
            const dest = openWindows[currentContextWinId].path;
            selectedItems.forEach(item => {
                moveItem(item.path, dest, () => {
                    if (item.winId && openWindows[item.winId]) loadDirectory(item.winId, openWindows[item.winId].path);
                    if (currentContextWinId && openWindows[currentContextWinId]) loadDirectory(currentContextWinId, openWindows[currentContextWinId].path);
                    if (item.winId === 'desktop' || currentContextWinId === 'desktop') loadDesktop();
                });
            });
            selectedItems = [];
        }

        function showContextMenu(x, y, type) {
            const menu = document.getElementById('contextMenu');
            menu.classList.remove('hidden');
            
            const showCreate = (type === 'empty' || type === 'desktop');
            document.getElementById('menuCreateFile').classList.toggle('hidden', !showCreate);
            document.getElementById('menuCreateFolder').classList.toggle('hidden', !showCreate);
            document.getElementById('menuPaste').classList.toggle('hidden', !showCreate || !clipboard);
            
            const isMultiple = selectedItems.length > 1;
            document.getElementById('menuEdit').classList.toggle('hidden', type !== 'item' || currentContextType !== 'file' || isMultiple);
            document.getElementById('menuPreview').classList.toggle('hidden', type !== 'item' || currentContextType !== 'file' || isMultiple);
            document.getElementById('menuProperties').classList.toggle('hidden', type !== 'item' || isMultiple);
            
            const isDesktopItem = type === 'item' && currentContextPath.startsWith(desktopPath);
            document.getElementById('menuSendToDesktop').classList.toggle('hidden', type !== 'item' || isDesktopItem || !openWindows[currentContextWinId]);
            document.getElementById('menuSendToExplorer').classList.toggle('hidden', type !== 'item' || !isDesktopItem || !openWindows[currentContextWinId]);
            
            document.getElementById('menuRename').classList.toggle('hidden', type !== 'item');
            document.getElementById('menuCopy').classList.toggle('hidden', type !== 'item');
            document.getElementById('menuCut').classList.toggle('hidden', type !== 'item');
            document.getElementById('menuDelete').classList.toggle('hidden', type !== 'item');

            const menuWidth = 224;
            const menuHeight = 250;
            if (x + menuWidth > window.innerWidth) x = window.innerWidth - menuWidth;
            if (y + menuHeight > window.innerHeight) y = window.innerHeight - menuHeight;
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#contextMenu')) {
                document.getElementById('contextMenu').classList.add('hidden');
            }
            if (!e.target.closest('.file-item')) {
                document.querySelectorAll('.file-item.selected').forEach(i => i.classList.remove('selected'));
                selectedItems = [];
            }
        });

        function editFile() {
            document.getElementById('contextMenu').classList.add('hidden');
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_content&path=' + encodeURIComponent(currentContextPath)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const content = `
                        <div class="flex flex-col h-full">
                            <div class="p-4 border-b flex justify-between items-center no-select">
                                <span class="font-bold text-gray-800">แก้ไขไฟล์: ${data.filename}</span>
                            </div>
                            <textarea class="flex-1 p-4 font-mono text-sm border-none focus:ring-0 resize-none outline-none bg-gray-50 text-gray-800 custom-scroll" spellcheck="false"></textarea>
                            <div class="p-4 border-t flex justify-end gap-2 no-select">
                                <span class="text-xs text-gray-400 self-center mr-auto">กด Ctrl+S เพื่อบันทึก</span>
                                <button type="button" onclick="closeWindow('win-edit')" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 text-gray-800">ยกเลิก</button>
                                <button type="button" onclick="saveFile()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">บันทึก (Save)</button>
                            </div>
                        </div>
                    `;
                    createAppWindow('win-edit', 'Edit File', content, 800, 600);
                    document.querySelector('#win-edit textarea').value = data.content;
                } else {
                    alert('ไม่สามารถเปิดไฟล์ได้');
                }
            });
        }

        function saveFile() {
            const content = document.querySelector('#win-edit textarea').value;
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=save_file&path=' + encodeURIComponent(currentContextPath) + '&content=' + encodeURIComponent(content)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeWindow('win-edit');
                    if (currentContextWinId && openWindows[currentContextWinId]) loadDirectory(currentContextWinId, openWindows[currentContextWinId].path);
                    if (currentContextPath.includes(desktopPath)) loadDesktop();
                } else {
                    alert('บันทึกไม่สำเร็จ');
                }
            });
        }

        function previewFile() {
            document.getElementById('contextMenu').classList.add('hidden');
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_preview&path=' + encodeURIComponent(currentContextPath)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const filename = currentContextPath.split('/').pop();
                    let contentHtml = '';
                    if (data.type === 'image') {
                        contentHtml = `<div class="flex items-center justify-center h-full"><img src="${data.url}" class="max-w-full max-h-full object-contain"></div>`;
                    } else if (data.type === 'web') {
                        contentHtml = `<iframe src="${data.url}" class="w-full h-full bg-white border-0"></iframe>`;
                    } else if (data.type === 'text') {
                        contentHtml = `<pre class="text-sm text-gray-800 whitespace-pre-wrap p-4 h-full overflow-auto">${data.content.replace(/</g, '&lt;')}</pre>`;
                    } else {
                        contentHtml = `<div class="flex items-center justify-center h-full text-gray-500">${data.msg}</div>`;
                    }
                    createAppWindow('win-preview', 'Preview: ' + filename, contentHtml, 800, 600);
                } else {
                    alert('ไม่สามารถพรีวิวไฟล์ได้');
                }
            });
        }

        function showProperties() {
            document.getElementById('contextMenu').classList.add('hidden');
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_properties&path=' + encodeURIComponent(currentContextPath)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let html = `<div class="p-6 space-y-2 text-sm text-gray-600 mb-4">`;
                    html += `<div><strong>ชื่อ:</strong> ${data.name}</div>`;
                    html += `<div><strong>ตำแหน่ง:</strong> ${data.path}</div>`;
                    html += `<div><strong>ประเภท:</strong> ${data.type}</div>`;
                    html += `<div><strong>ขนาด:</strong> ${data.size}</div>`;
                    html += `<div><strong>แก้ไขล่าสุด:</strong> ${data.modified}</div>`;
                    html += `<div><strong>สิทธิ์:</strong> ${data.perms}</div>`;
                    html += `</div><div class="p-4 border-t flex justify-end"><button type="button" onclick="closeWindow('win-props')" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">ปิด</button></div>`;
                    createAppWindow('win-props', 'Properties', html, 400, 350);
                }
            });
        }

        function showDeleteModal() {
            document.getElementById('contextMenu').classList.add('hidden');
            if (selectedItems.length === 0 && currentContextPath) {
                selectedItems = [{ path: currentContextPath, type: currentContextType, winId: currentContextWinId }];
            }
            if (selectedItems.length === 0) return;

            if (confirm(`คุณต้องการลบ ${selectedItems.length} รายการใช่หรือไม่?`)) {
                confirmDelete();
            }
        }

        function confirmDelete() {
            let promises = selectedItems.map(item => {
                return fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=delete&path=' + encodeURIComponent(item.path)
                }).then(res => res.json());
            });

            Promise.all(promises).then(results => {
                let needsDesktopReload = false;
                let windowsToReload = new Set();
                let errorMsg = '';
                results.forEach((r, i) => {
                    if (r.success) {
                        if (selectedItems[i].winId === 'desktop' || selectedItems[i].path.includes(desktopPath)) needsDesktopReload = true;
                        if (selectedItems[i].winId && openWindows[selectedItems[i].winId]) windowsToReload.add(selectedItems[i].winId);
                    } else {
                        errorMsg += 'ลบไม่สำเร็จ: ' + (r.message || '') + '\n';
                    }
                });
                if (errorMsg) alert(errorMsg);
                windowsToReload.forEach(winId => loadDirectory(winId, openWindows[winId].path));
                if (needsDesktopReload) loadDesktop();
                selectedItems = [];
            });
        }

        function createItem(type) {
            document.getElementById('contextMenu').classList.add('hidden');
            createType = type;
            const title = type === 'file' ? 'สร้างไฟล์ใหม่' : 'สร้างโฟลเดอร์ใหม่';
            const html = `
                <div class="p-6 flex flex-col h-full">
                    <h3 class="text-lg font-bold mb-4 text-gray-800">${title}</h3>
                    <input type="text" id="createInput" class="w-full px-3 py-2 border border-gray-300 rounded mb-4 outline-none focus:border-blue-500" placeholder="ชื่อไฟล์หรือโฟลเดอร์">
                    <div class="flex justify-end gap-2 mt-auto">
                        <button type="button" onclick="closeWindow('win-create')" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 text-gray-800">ยกเลิก</button>
                        <button type="button" onclick="confirmCreate()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">สร้าง</button>
                    </div>
                </div>
            `;
            createAppWindow('win-create', title, html, 350, 250);
            setTimeout(() => document.getElementById('createInput').focus(), 100);
        }

        function confirmCreate() {
            const name = document.getElementById('createInput').value.trim();
            if (!name) return;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=create_' + createType + '&path=' + encodeURIComponent(currentContextPath) + '&name=' + encodeURIComponent(name)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeWindow('win-create');
                    if (currentContextPath === desktopPath) loadDesktop();
                    else if (currentContextWinId && openWindows[currentContextWinId]) loadDirectory(currentContextWinId, currentContextPath);
                } else {
                    alert('สร้างไม่สำเร็จ');
                }
            });
        }

        function renameItems() {
            document.getElementById('contextMenu').classList.add('hidden');
            if (selectedItems.length === 0) return;
            
            let promises = selectedItems.map(item => {
                const oldName = item.path.split('/').pop();
                const newName = prompt(`เปลี่ยนชื่อ "${oldName}" เป็น:`, oldName);
                if (newName && newName !== oldName) {
                    return fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=rename&path=' + encodeURIComponent(item.path) + '&new_name=' + encodeURIComponent(newName)
                    }).then(res => res.json());
                }
                return Promise.resolve({ success: false });
            });

            Promise.all(promises).then(results => {
                let needsDesktopReload = false;
                let windowsToReload = new Set();
                results.forEach((r, i) => {
                    if (r.success) {
                        if (selectedItems[i].winId === 'desktop' || selectedItems[i].path.includes(desktopPath)) needsDesktopReload = true;
                        if (selectedItems[i].winId && openWindows[selectedItems[i].winId]) windowsToReload.add(selectedItems[i].winId);
                    }
                });
                windowsToReload.forEach(winId => loadDirectory(winId, openWindows[winId].path));
                if (needsDesktopReload) loadDesktop();
                selectedItems = [];
            });
        }

        function copyItem() {
            clipboard = { items: [...selectedItems], mode: 'copy' };
            document.getElementById('contextMenu').classList.add('hidden');
        }

        function cutItem() {
            clipboard = { items: [...selectedItems], mode: 'cut' };
            document.getElementById('contextMenu').classList.add('hidden');
        }

        function pasteItem() {
            if (!clipboard) return;
            document.getElementById('contextMenu').classList.add('hidden');
            
            let promises = clipboard.items.map(item => {
                return fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=' + clipboard.mode + '&source=' + encodeURIComponent(item.path) + '&dest_dir=' + encodeURIComponent(currentContextPath)
                }).then(res => res.json());
            });

            Promise.all(promises).then(results => {
                let needsDesktopReload = false;
                let windowsToReload = new Set();
                
                if (currentContextWinId && openWindows[currentContextWinId]) windowsToReload.add(currentContextWinId);
                if (currentContextPath === desktopPath) needsDesktopReload = true;

                if (clipboard.mode === 'cut') {
                    clipboard.items.forEach(item => {
                        if (item.winId === 'desktop') needsDesktopReload = true;
                        if (item.winId && openWindows[item.winId]) windowsToReload.add(item.winId);
                    });
                    clipboard = null;
                }

                windowsToReload.forEach(winId => loadDirectory(winId, openWindows[winId].path));
                if (needsDesktopReload) loadDesktop();
            });
        }

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                if (openWindows['win-edit']) saveFile();
                return;
            }

            if (e.key === 'F5') {
                e.preventDefault();
                if (activeWinId && openWindows[activeWinId] && openWindows[activeWinId].type === 'explorer') {
                    loadDirectory(activeWinId, openWindows[activeWinId].path);
                }
                return;
            }

            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            if (selectedItems.length === 0) return;

            if (e.key === 'F2') { e.preventDefault(); renameItems(); } 
            else if (e.key === 'Delete') { e.preventDefault(); showDeleteModal(); } 
            else if ((e.ctrlKey || e.metaKey) && e.key === 'c') { e.preventDefault(); copyItem(); } 
            else if ((e.ctrlKey || e.metaKey) && e.key === 'x') { e.preventDefault(); cutItem(); } 
            else if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
                e.preventDefault();
                if (clipboard && activeWinId && openWindows[activeWinId]) {
                    currentContextPath = openWindows[activeWinId].path;
                    currentContextWinId = activeWinId;
                    pasteItem();
                }
            }
        });

        document.getElementById('desktop').addEventListener('contextmenu', (e) => {
            e.preventDefault();
            if (e.target.closest('.file-item') || e.target.closest('.desktop-icon')) return;
            currentContextWinId = 'desktop';
            currentContextPath = desktopPath;
            currentContextType = 'empty';
            showContextMenu(e.clientX, e.clientY, 'desktop');
        });

        function handleGridMouseDown(e, winId) {
            if (e.target.closest('.file-item')) return;
            if (e.button !== 0) return;
            
            const grid = e.currentTarget;
            const rect = grid.getBoundingClientRect();
            const startX = e.clientX;
            const startY = e.clientY;

            document.querySelectorAll('.file-item.selected').forEach(i => i.classList.remove('selected'));
            selectedItems = [];

            const selBox = document.createElement('div');
            selBox.className = 'selection-box';
            selBox.style.left = (startX - rect.left) + 'px';
            selBox.style.top = (startY - rect.top) + 'px';
            grid.appendChild(selBox);

            function move(e) {
                const cx = e.clientX;
                const cy = e.clientY;
                const left = Math.min(cx, startX) - rect.left;
                const top = Math.min(cy, startY) - rect.top;
                const w = Math.abs(cx - startX);
                const h = Math.abs(cy - startY);
                
                selBox.style.left = left + 'px';
                selBox.style.top = top + 'px';
                selBox.style.width = w + 'px';
                selBox.style.height = h + 'px';

                grid.querySelectorAll('.file-item').forEach(item => {
                    const itemRect = item.getBoundingClientRect();
                    const isIntersecting = !(itemRect.right < Math.min(cx, startX) || itemRect.left > Math.max(cx, startX) || itemRect.bottom < Math.min(cy, startY) || itemRect.top > Math.max(cy, startY));
                    if (isIntersecting) item.classList.add('selected');
                    else item.classList.remove('selected');
                });
            }

            function up() {
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
                selBox.remove();
                
                selectedItems = [];
                grid.querySelectorAll('.file-item.selected').forEach(item => {
                    selectedItems.push({ path: item.dataset.path, type: item.dataset.type, winId: winId });
                });
                if (selectedItems.length > 0) {
                    currentContextPath = selectedItems[0].path;
                    currentContextType = selectedItems[0].type;
                    currentContextWinId = winId;
                }
            }

            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        }

        document.getElementById('htdocsIcon').addEventListener('dblclick', () => createWindow(rootRealPath));
        document.getElementById('settingsIcon').addEventListener('dblclick', openSettings);
        document.getElementById('musicIcon').addEventListener('dblclick', openMusic);

        function openSettings() {
            const html = `
                <div class="p-6 flex flex-col h-full">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">ฟอนต์ (Font Family)</label>
                        <select id="fontSelector" class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:border-blue-500">
                            <option value="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif">Segoe UI (Default)</option>
                            <option value="Arial, sans-serif">Arial</option>
                            <option value="'Courier New', Courier, monospace">Courier New</option>
                            <option value="Georgia, serif">Georgia</option>
                            <option value="'Times New Roman', Times, serif">Times New Roman</option>
                            <option value="'Trebuchet MS', Helvetica, sans-serif">Trebuchet MS</option>
                            <option value="Verdana, Geneva, sans-serif">Verdana</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">พื้นหลัง (Background)</label>
                        <div class="flex flex-col gap-2 mb-3">
                            <label class="flex items-center gap-2"><input type="radio" name="bgType" value="color"> สีพื้นหลัง</label>
                            <input type="color" id="bgColorPicker" class="w-full h-10 p-1 border border-gray-300 rounded cursor-pointer">
                        </div>
                        <div class="flex flex-col gap-2 mb-3">
                            <label class="flex items-center gap-2"><input type="radio" name="bgType" value="url"> URL รูปภาพ</label>
                            <input type="text" id="bgUrlInput" class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:border-blue-500" placeholder="https://...">
                        </div>
                        <div class="flex flex-col gap-2 mb-3">
                            <label class="flex items-center gap-2"><input type="radio" name="bgType" value="upload"> อัพโหลดรูปภาพ</label>
                            <input type="file" id="bgFileInput" class="w-full px-3 py-2 border border-gray-300 rounded outline-none" accept="image/*">
                            <span id="bgUploadStatus" class="text-xs text-gray-500"></span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">YouTube API Key (ถ้ามี)</label>
                        <input type="text" id="ytApiKeyInput" class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:border-blue-500" placeholder="AIza...">
                    </div>
                    <div class="mt-auto pt-4 border-t flex justify-end gap-2 no-select">
                        <button type="button" onclick="closeWindow('win-settings')" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 text-gray-800">ยกเลิก</button>
                        <button type="button" onclick="saveSettings()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">บันทึกตั้งค่า</button>
                    </div>
                </div>
            `;
            createAppWindow('win-settings', 'Settings', html, 500, 600);
            applySettingsForm();
        }

        function applySettingsForm() {
            const bgType = localStorage.getItem('os_bg_type') || 'color';
            document.querySelector(`input[name="bgType"][value="${bgType}"]`).checked = true;
            document.getElementById('bgColorPicker').value = localStorage.getItem('os_bg_color') || '#1e3a8a';
            document.getElementById('bgUrlInput').value = bgType === 'url' ? (localStorage.getItem('os_bg_url') || '') : '';
            document.getElementById('fontSelector').value = localStorage.getItem('os_font') || "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
            document.getElementById('ytApiKeyInput').value = localStorage.getItem('os_yt_key') || '';
        }

        function saveSettings() {
            const font = document.getElementById('fontSelector').value;
            const bgType = document.querySelector('input[name="bgType"]:checked').value;
            
            localStorage.setItem('os_font', font);
            localStorage.setItem('os_bg_type', bgType);

            if (bgType === 'color') {
                const bgColor = document.getElementById('bgColorPicker').value;
                localStorage.setItem('os_bg_color', bgColor);
                localStorage.removeItem('os_bg_url');
                applySettings();
                closeWindow('win-settings');
            } else if (bgType === 'url') {
                const bgUrl = document.getElementById('bgUrlInput').value.trim();
                if (bgUrl) {
                    localStorage.setItem('os_bg_url', bgUrl);
                    applySettings();
                    closeWindow('win-settings');
                } else {
                    alert('กรุณาใส่ URL รูปภาพ');
                    return;
                }
            } else if (bgType === 'upload') {
                const fileInput = document.getElementById('bgFileInput');
                if (fileInput.files.length > 0) {
                    const formData = new FormData();
                    formData.append('action', 'upload_bg');
                    formData.append('bg_file', fileInput.files[0]);
                    
                    document.getElementById('bgUploadStatus').textContent = 'กำลังอัพโหลด...';
                    fetch('', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            localStorage.setItem('os_bg_url', data.url);
                            document.getElementById('bgUploadStatus').textContent = 'อัพโหลดสำเร็จ';
                            applySettings();
                            closeWindow('win-settings');
                        } else {
                            document.getElementById('bgUploadStatus').textContent = 'อัพโหลดล้มเหลว: ' + data.message;
                        }
                    })
                    .catch(err => {
                        document.getElementById('bgUploadStatus').textContent = 'เกิดข้อผิดพลาดในการอัพโหลด';
                    });
                } else {
                    applySettings();
                    closeWindow('win-settings');
                }
            }

            const ytKey = document.getElementById('ytApiKeyInput').value.trim();
            localStorage.setItem('os_yt_key', ytKey);
        }

        function applySettings() {
            const font = localStorage.getItem('os_font') || "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
            const bgType = localStorage.getItem('os_bg_type') || 'color';
            const bgColor = localStorage.getItem('os_bg_color') || '#1e3a8a';
            const bgUrl = localStorage.getItem('os_bg_url') || '';

            document.body.style.fontFamily = font;
            if ((bgType === 'url' || bgType === 'upload') && bgUrl) {
                const img = new Image();
                img.onload = () => {
                    document.body.style.background = `url('${bgUrl}') no-repeat center center fixed`;
                    document.body.style.backgroundSize = 'cover';
                };
                img.onerror = () => {
                    document.body.style.background = `linear-gradient(135deg, ${bgColor} 0%, ${shadeColor(bgColor, -20)} 100%)`;
                };
                img.src = bgUrl;
            } else {
                document.body.style.background = `linear-gradient(135deg, ${bgColor} 0%, ${shadeColor(bgColor, -20)} 100%)`;
            }
        }

        function shadeColor(color, percent) {
            let R = parseInt(color.substring(1,3),16);
            let G = parseInt(color.substring(3,5),16);
            let B = parseInt(color.substring(5,7),16);
            R = parseInt(R * (100 + percent) / 100);
            G = parseInt(G * (100 + percent) / 100);
            B = parseInt(B * (100 + percent) / 100);
            R = (R<255)?R:255; G = (G<255)?G:255; B = (B<255)?B:255;
            R = Math.max(0,R); G = Math.max(0,G); B = Math.max(0,B);
            let RR = R.toString(16).padStart(2, '0');
            let GG = G.toString(16).padStart(2, '0');
            let BB = B.toString(16).padStart(2, '0');
            return "#"+RR+GG+BB;
        }

        function openMusic() {
            const html = `
                <div class="p-6 flex flex-col h-full gap-4">
                    <div class="flex gap-2">
                        <input type="text" id="ytVideoInput" class="flex-1 px-3 py-2 border border-gray-300 rounded outline-none focus:border-blue-500" placeholder="ใส่รหัสเพลง YouTube (เช่น dQw4w9WgXcQ)">
                        <button type="button" onclick="playMusic()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">เล่น</button>
                    </div>
                    <div id="ytPlayerContainer" class="flex-1 min-h-[300px] bg-black rounded flex items-center justify-center text-gray-400">
                        ยังไม่มีเพลงเล่น
                    </div>
                </div>
            `;
            createAppWindow('win-music', 'Music Player', html, 600, 500);
        }

        function playMusic() {
            let videoId = document.getElementById('ytVideoInput').value.trim();
            if (!videoId) return;
            
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            const match = videoId.match(regExp);
            if (match && match[2].length == 11) {
                videoId = match[2];
            }

            const container = document.getElementById('ytPlayerContainer');
            container.innerHTML = `<iframe width="100%" height="100%" src="https://www.youtube.com/embed/${videoId}?autoplay=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
        }

        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds}`;
            
            const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            const day = now.getDate();
            const month = months[now.getMonth()];
            const year = now.getFullYear() + 543;
            document.getElementById('date').textContent = `${day} ${month} ${year}`;
        }

        function dirname(path) {
            return path.split('/').slice(0, -1).join('/') || '/';
        }

        setInterval(updateClock, 1000);
        updateClock();

        window.addEventListener('resize', () => {
            Object.values(openWindows).forEach(w => {
                if (w.el.dataset.isMax !== 'true') {
                    let left = parseInt(w.el.style.left);
                    let top = parseInt(w.el.style.top);
                    let width = w.el.offsetWidth;
                    let height = w.el.offsetHeight;
                    if (left + width > window.innerWidth) w.el.style.left = Math.max(0, window.innerWidth - width) + 'px';
                    if (top + height > window.innerHeight) w.el.style.top = Math.max(0, window.innerHeight - height) + 'px';
                }
            });
        });

        window.addEventListener('load', () => {
            applySettings();
            if (!loadState()) {
                createWindow(rootRealPath);
            }
            loadDesktop();
        });
    </script>
</body>
</html>