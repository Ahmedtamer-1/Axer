<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg-sidebar: #0f172a;
            --bg-panel: #1e293b;
            --border-color: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --danger: #ef4444;
            --font-main: 'Outfit', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background-color: #020617;
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
        header {
            height: 60px;
            background-color: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1.5rem;
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-title {
            font-weight: 600;
            font-size: 1.125rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.375rem;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--text-main);
        }

        .btn-icon {
            padding: 0.5rem;
            border-radius: 0.375rem;
            border: 1px solid var(--border-color);
            background-color: transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background-color: var(--border-color);
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-secondary {
            background-color: transparent;
            border-color: var(--border-color);
        }

        .btn-secondary:hover {
            background-color: var(--border-color);
        }

        /* Workspace Layout */
        .workspace {
            display: flex;
            flex-grow: 1;
            overflow: hidden;
        }

        /* Sidebars */
        .panel {
            width: 320px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .panel-right {
            border-right: none;
            border-left: 1px solid var(--border-color);
        }

        .panel-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .panel-body {
            flex-grow: 1;
            overflow-y: auto;
            padding: 1.25rem;
        }

        /* Blocks List / Drag and Drop */
        .block-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .block-item {
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: grab;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .block-item:hover {
            border-color: var(--primary);
        }

        .block-item.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }

        .block-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .block-icon {
            color: var(--primary);
        }

        .block-name {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .block-actions {
            display: flex;
            gap: 0.25rem;
        }

        /* Preview Canvas */
        .preview-canvas {
            flex-grow: 1;
            background-color: #020617;
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .preview-frame-wrapper {
            width: 100%;
            height: 100%;
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.5);
            overflow: hidden;
            position: relative;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background-color: white;
        }

        /* Properties Panel Form */
        .prop-group {
            margin-bottom: 1.25rem;
        }

        .prop-group label {
            display: block;
            font-size: 0.8125rem;
            color: var(--text-muted);
            margin-bottom: 0.375rem;
        }

        .prop-control {
            width: 100%;
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: var(--text-main);
            font-family: var(--font-main);
            font-size: 0.875rem;
        }

        .prop-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Modal for adding blocks */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--bg-sidebar);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            width: 480px;
            max-width: 90%;
            display: flex;
            flex-direction: column;
            max-height: 80vh;
        }

        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.25rem;
            overflow-y: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .block-template-card {
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
        }

        .block-template-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .block-template-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .block-template-name {
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <a href="/admin/pages" class="btn-icon" title="Exit Builder">
                <i data-lucide="x"></i>
            </a>
            <span class="header-title"><?= htmlspecialchars($page['title']) ?> <span style="font-weight:400; color:var(--text-muted); font-size:0.875rem;">(Builder)</span></span>
        </div>
        <div class="header-actions">
            <button id="btn-undo" class="btn-icon" title="Undo"><i data-lucide="undo-2"></i></button>
            <button id="btn-redo" class="btn-icon" title="Redo"><i data-lucide="redo-2"></i></button>
            <button id="btn-save" class="btn btn-primary"><i data-lucide="save"></i> Save Page</button>
        </div>
    </header>

    <div class="workspace">
        <!-- Left Sidebar: Block Hierarchy -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Sections / Blocks</span>
                <button id="btn-add-block" class="btn btn-primary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                    <i data-lucide="plus"></i> Add Block
                </button>
            </div>
            <div class="panel-body">
                <div class="block-list" id="active-blocks-container">
                    <!-- Dynamic Blocks go here -->
                </div>
            </div>
        </div>

        <!-- Center: Iframe Preview Canvas -->
        <div class="preview-canvas">
            <div class="preview-frame-wrapper">
                <iframe id="preview-frame" src="about:blank"></iframe>
            </div>
        </div>

        <!-- Right Sidebar: Properties panel -->
        <div class="panel panel-right">
            <div class="panel-header">
                <span class="panel-title">Properties</span>
            </div>
            <div class="panel-body" id="properties-container">
                <div style="color: var(--text-muted); font-size: 0.875rem; text-align: center; margin-top: 2rem;">
                    Select a section to edit its settings.
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Library -->
    <div class="modal" id="library-modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="panel-title">Add Section Block</span>
                <button class="btn-icon" id="btn-close-modal"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <div class="block-template-card" data-type="hero">
                    <div class="block-template-icon"><i data-lucide="image"></i></div>
                    <div class="block-template-name">Hero Banner</div>
                </div>
                <div class="block-template-card" data-type="featured-products">
                    <div class="block-template-icon"><i data-lucide="shopping-bag"></i></div>
                    <div class="block-template-name">Featured Products</div>
                </div>
                <div class="block-template-card" data-type="rich-text">
                    <div class="block-template-icon"><i data-lucide="file-text"></i></div>
                    <div class="block-template-name">Rich Text</div>
                </div>
                <div class="block-template-card" data-type="newsletter">
                    <div class="block-template-icon"><i data-lucide="mail"></i></div>
                    <div class="block-template-name">Newsletter Signup</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pass backend page data to JS -->
    <script>
        const PAGE_ID = <?= (int)$page['id'] ?>;
        const INITIAL_BUILDER_DATA = <?= empty($page['builder_data']) ? '[]' : $page['builder_data'] ?>;
    </script>
    <script src="/admin/assets/js/builder.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
