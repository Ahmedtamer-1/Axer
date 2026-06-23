/**
 * Axer CMS Media Picker
 * Usage:
 * 1. Include this script in your page.
 * 2. Add `<div id="media-picker-modal" class="..."></div>` to the bottom of the body. (We inject it dynamically if not found).
 * 3. Call `openMediaPicker((selectedMediaUrl) => { ... })`
 */

document.addEventListener('DOMContentLoaded', () => {
    // Inject CSS for the modal
    const style = document.createElement('style');
    style.innerHTML = `
        .mp-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .mp-modal.active { display: flex; }
        .mp-content {
            background: var(--bg-card, #fff);
            border-radius: 0.75rem;
            width: 800px;
            max-width: 90%;
            height: 600px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            color: var(--text-main, #1e293b);
        }
        .mp-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .mp-header h2 { margin: 0; font-size: 1.25rem; font-weight: 600; }
        .mp-close { cursor: pointer; background: none; border: none; font-size: 1.5rem; color: var(--text-muted, #64748b); }
        .mp-body {
            flex-grow: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
            background: var(--bg-content, #f8fafc);
        }
        .mp-item {
            aspect-ratio: 1;
            border-radius: 0.5rem;
            border: 2px solid transparent;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            background: #e2e8f0;
            transition: all 0.2s;
        }
        .mp-item:hover { border-color: var(--primary, #6366f1); transform: scale(1.02); }
        .mp-item.selected { border-color: var(--primary, #6366f1); }
        .mp-item img { width: 100%; height: 100%; object-fit: cover; }
        .mp-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border-color, #e2e8f0);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        .mp-loading { grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted, #64748b); }
    `;
    document.head.appendChild(style);

    // Inject Modal HTML
    const modalHtml = `
        <div class="mp-modal" id="Axer-media-picker">
            <div class="mp-content">
                <div class="mp-header">
                    <h2>Select Media</h2>
                    <button class="mp-close" id="mp-btn-close">&times;</button>
                </div>
                <div class="mp-body" id="mp-grid">
                    <div class="mp-loading">Loading media...</div>
                </div>
                <div class="mp-footer">
                    <button class="btn btn-secondary" id="mp-btn-cancel">Cancel</button>
                    <button class="btn btn-primary" id="mp-btn-select" disabled>Select Image</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const modal = document.getElementById('Axer-media-picker');
    const grid = document.getElementById('mp-grid');
    const btnClose = document.getElementById('mp-btn-close');
    const btnCancel = document.getElementById('mp-btn-cancel');
    const btnSelect = document.getElementById('mp-btn-select');
    
    let mediaList = [];
    let selectedUrl = null;
    let currentCallback = null;

    function renderGrid() {
        if (mediaList.length === 0) {
            grid.innerHTML = '<div class="mp-loading">No media files found. Upload some in the Media Library first.</div>';
            return;
        }

        grid.innerHTML = '';
        mediaList.forEach(item => {
            if (!item.mime_type.startsWith('image/')) return; // Only show images for now
            
            const el = document.createElement('div');
            el.className = 'mp-item';
            el.innerHTML = `<img src="${item.path}" alt="${item.original_name}">`;
            
            el.addEventListener('click', () => {
                document.querySelectorAll('.mp-item').forEach(i => i.classList.remove('selected'));
                el.classList.add('selected');
                selectedUrl = item.path;
                btnSelect.disabled = false;
            });
            
            grid.appendChild(el);
        });
    }

    function closeModal() {
        modal.classList.remove('active');
        selectedUrl = null;
        btnSelect.disabled = true;
    }

    btnClose.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);

    btnSelect.addEventListener('click', () => {
        if (selectedUrl && currentCallback) {
            currentCallback(selectedUrl);
        }
        closeModal();
    });

    window.openMediaPicker = function(callback) {
        currentCallback = callback;
        modal.classList.add('active');
        grid.innerHTML = '<div class="mp-loading">Loading media...</div>';
        
        fetch('/admin/api/media')
            .then(res => res.json())
            .then(data => {
                mediaList = data;
                renderGrid();
            })
            .catch(err => {
                grid.innerHTML = '<div class="mp-loading" style="color:red;">Error loading media.</div>';
                console.error(err);
            });
    };
});
