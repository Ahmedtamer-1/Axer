document.addEventListener('DOMContentLoaded', () => {
    // State management
    let state = {
        blocks: Array.isArray(INITIAL_BUILDER_DATA) ? INITIAL_BUILDER_DATA : []
    };
    
    let undoStack = [];
    let redoStack = [];
    let activeBlockIndex = null;
    
    // Elements
    const activeBlocksContainer = document.getElementById('active-blocks-container');
    const propertiesContainer = document.getElementById('properties-container');
    const previewFrame = document.getElementById('preview-frame');
    const btnUndo = document.getElementById('btn-undo');
    const btnRedo = document.getElementById('btn-redo');
    const btnSave = document.getElementById('btn-save');
    const btnAddBlock = document.getElementById('btn-add-block');
    const libraryModal = document.getElementById('library-modal');
    const btnCloseModal = document.getElementById('btn-close-modal');
    
    // Definitions of block structures and fields
    const blockRegistry = {
        'hero': {
            name: 'Hero Banner',
            icon: 'image',
            defaultSettings: {
                title: 'Welcome to our store',
                subtitle: 'Discover our latest premium collection.',
                button_text: 'Shop Now',
                button_url: '/products',
                bg_color: '#6366f1',
                text_color: '#ffffff'
            },
            fields: [
                { name: 'title', label: 'Headline', type: 'text' },
                { name: 'subtitle', label: 'Sub-headline', type: 'textarea' },
                { name: 'button_text', label: 'Button Label', type: 'text' },
                { name: 'button_url', label: 'Button Link', type: 'text' },
                { name: 'bg_color', label: 'Background Color', type: 'color' },
                { name: 'text_color', label: 'Text Color', type: 'color' }
            ]
        },
        'featured-products': {
            name: 'Featured Products',
            icon: 'shopping-bag',
            defaultSettings: {
                title: 'Best Sellers',
                limit: 4,
                columns: 4
            },
            fields: [
                { name: 'title', label: 'Section Title', type: 'text' },
                { name: 'limit', label: 'Number of Products', type: 'number' },
                { name: 'columns', label: 'Columns Layout', type: 'select', options: [2, 3, 4] }
            ]
        },
        'rich-text': {
            name: 'Rich Text',
            icon: 'file-text',
            defaultSettings: {
                title: 'About us',
                content: 'We craft high quality products for premium lifestyles.',
                align: 'center'
            },
            fields: [
                { name: 'title', label: 'Section Title', type: 'text' },
                { name: 'content', label: 'Content Paragraph', type: 'textarea' },
                { name: 'align', label: 'Text Alignment', type: 'select', options: ['left', 'center', 'right'] }
            ]
        },
        'newsletter': {
            name: 'Newsletter Signup',
            icon: 'mail',
            defaultSettings: {
                title: 'Subscribe to newsletter',
                subtitle: 'Get 10% off your first purchase.'
            },
            fields: [
                { name: 'title', label: 'Heading', type: 'text' },
                { name: 'subtitle', label: 'Sub-heading', type: 'text' }
            ]
        }
    };

    // Save history state for undo/redo
    function pushState() {
        undoStack.push(JSON.stringify(state.blocks));
        redoStack = []; // Clear redo stack on new action
        updateHistoryButtons();
    }

    function updateHistoryButtons() {
        btnUndo.disabled = undoStack.length === 0;
        btnRedo.disabled = redoStack.length === 0;
        btnUndo.style.opacity = undoStack.length === 0 ? '0.5' : '1';
        btnRedo.style.opacity = redoStack.length === 0 ? '0.5' : '1';
    }

    // CSRF Token for fetch requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Live update of iframe preview
    function updatePreview() {
        fetch(`/admin/pages/builder/preview`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken 
            },
            body: JSON.stringify({ builder_data: state.blocks, _csrf: csrfToken })
        })
        .then(res => res.text())
        .then(html => {
            const doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();
        })
        .catch(err => console.error("Preview render failed: ", err));
    }

    // Render left-side list of active blocks
    function renderActiveBlocks() {
        activeBlocksContainer.innerHTML = '';
        state.blocks.forEach((block, index) => {
            const registryItem = blockRegistry[block.type];
            if (!registryItem) return;
            
            const el = document.createElement('div');
            el.className = `block-item ${activeBlockIndex === index ? 'active' : ''}`;
            el.style.borderColor = activeBlockIndex === index ? 'var(--primary)' : '';
            el.draggable = true;
            el.dataset.index = index;
            
            el.innerHTML = `
                <div class="block-info">
                    <span class="block-icon"><i data-lucide="${registryItem.icon || 'box'}"></i></span>
                    <span class="block-name">${registryItem.name}</span>
                </div>
                <div class="block-actions">
                    <button class="btn-icon delete-btn" style="border:none; padding:2px;"><i data-lucide="trash-2" style="width:16px; height:16px; color:var(--danger);"></i></button>
                </div>
            `;
            
            // Selection event
            el.addEventListener('click', (e) => {
                if (e.target.closest('.delete-btn')) {
                    deleteBlock(index);
                    return;
                }
                selectBlock(index);
            });

            // Drag and drop event listeners
            el.addEventListener('dragstart', handleDragStart);
            el.addEventListener('dragover', handleDragOver);
            el.addEventListener('drop', handleDrop);
            el.addEventListener('dragend', handleDragEnd);

            activeBlocksContainer.appendChild(el);
        });
        
        lucide.createIcons({ attrs: { class: 'lucide' } });
    }

    // Select block for editing properties
    function selectBlock(index) {
        activeBlockIndex = index;
        renderActiveBlocks();
        
        if (index === null || !state.blocks[index]) {
            propertiesContainer.innerHTML = `
                <div style="color: var(--text-muted); font-size: 0.875rem; text-align: center; margin-top: 2rem;">
                    Select a section to edit its settings.
                </div>
            `;
            return;
        }

        const block = state.blocks[index];
        const registryItem = blockRegistry[block.type];
        
        let html = `<h3 style="font-size:1rem; margin-bottom:1rem;">Edit ${registryItem.name}</h3>`;
        
        registryItem.fields.forEach(field => {
            const val = block.settings[field.name] !== undefined ? block.settings[field.name] : '';
            
            html += `<div class="prop-group">
                <label>${field.label}</label>`;
                
            if (field.type === 'textarea') {
                html += `<textarea class="prop-control" data-name="${field.name}">${val}</textarea>`;
            } else if (field.type === 'select') {
                html += `<select class="prop-control" data-name="${field.name}">`;
                field.options.forEach(opt => {
                    html += `<option value="${opt}" ${opt == val ? 'selected' : ''}>${opt}</option>`;
                });
                html += `</select>`;
            } else if (field.type === 'color') {
                html += `<input type="color" class="prop-control" style="height:40px; padding:2px;" data-name="${field.name}" value="${val}">`;
            } else if (field.type === 'number') {
                html += `<input type="number" class="prop-control" data-name="${field.name}" value="${val}">`;
            } else {
                html += `<input type="text" class="prop-control" data-name="${field.name}" value="${val}">`;
            }
            
            html += `</div>`;
        });
        
        propertiesContainer.innerHTML = html;
        
        // Setup change listeners to live-update properties
        propertiesContainer.querySelectorAll('.prop-control').forEach(ctrl => {
            ctrl.addEventListener('input', (e) => {
                const name = e.target.dataset.name;
                let val = e.target.value;
                if (e.target.type === 'number') val = parseInt(val) || 0;
                
                state.blocks[activeBlockIndex].settings[name] = val;
                updatePreview();
            });
            // Record state for undo when change is completed
            ctrl.addEventListener('change', () => {
                pushState();
            });
        });
    }

    // Drag and Drop implementation
    let dragSrcEl = null;

    function handleDragStart(e) {
        dragSrcEl = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.index);
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDrop(e) {
        e.stopPropagation();
        e.preventDefault();
        
        const srcIndex = parseInt(e.dataTransfer.getData('text/plain'));
        const targetIndex = parseInt(this.dataset.index);
        
        if (srcIndex !== targetIndex) {
            pushState();
            const draggedBlock = state.blocks.splice(srcIndex, 1)[0];
            state.blocks.splice(targetIndex, 0, draggedBlock);
            
            activeBlockIndex = targetIndex;
            renderActiveBlocks();
            updatePreview();
        }
        return false;
    }

    function handleDragEnd() {
        this.classList.remove('dragging');
    }

    // Add Block
    function addBlock(type) {
        const registryItem = blockRegistry[type];
        if (!registryItem) return;
        
        pushState();
        state.blocks.push({
            type: type,
            settings: { ...registryItem.defaultSettings }
        });
        
        libraryModal.classList.remove('active');
        activeBlockIndex = state.blocks.length - 1;
        renderActiveBlocks();
        selectBlock(activeBlockIndex);
        updatePreview();
    }

    // Delete Block
    function deleteBlock(index) {
        pushState();
        state.blocks.splice(index, 1);
        
        if (activeBlockIndex === index) {
            activeBlockIndex = null;
        } else if (activeBlockIndex > index) {
            activeBlockIndex--;
        }
        
        renderActiveBlocks();
        selectBlock(activeBlockIndex);
        updatePreview();
    }

    // Undo & Redo
    btnUndo.addEventListener('click', () => {
        if (undoStack.length > 0) {
            redoStack.push(JSON.stringify(state.blocks));
            state.blocks = JSON.parse(undoStack.pop());
            activeBlockIndex = null;
            renderActiveBlocks();
            selectBlock(null);
            updatePreview();
            updateHistoryButtons();
        }
    });

    btnRedo.addEventListener('click', () => {
        if (redoStack.length > 0) {
            undoStack.push(JSON.stringify(state.blocks));
            state.blocks = JSON.parse(redoStack.pop());
            activeBlockIndex = null;
            renderActiveBlocks();
            selectBlock(null);
            updatePreview();
            updateHistoryButtons();
        }
    });

    // Save Page Builder Data
    btnSave.addEventListener('click', () => {
        btnSave.disabled = true;
        btnSave.innerHTML = '<i data-lucide="loader"></i> Saving...';
        lucide.createIcons();

        fetch(`/admin/pages/builder/save/${PAGE_ID}`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ builder_data: state.blocks, _csrf: csrfToken })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Page saved successfully!');
            } else {
                alert('Save failed: ' + data.message);
            }
        })
        .catch(err => {
            alert('An error occurred while saving.');
            console.error(err);
        })
        .finally(() => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i data-lucide="save"></i> Save Page';
            lucide.createIcons();
        });
    });

    // Modal Control
    btnAddBlock.addEventListener('click', () => {
        libraryModal.classList.add('active');
    });

    btnCloseModal.addEventListener('click', () => {
        libraryModal.classList.remove('active');
    });

    libraryModal.querySelectorAll('.block-template-card').forEach(card => {
        card.addEventListener('click', () => {
            addBlock(card.dataset.type);
        });
    });

    // Initialization
    updateHistoryButtons();
    renderActiveBlocks();
    selectBlock(null);
    updatePreview();
});
