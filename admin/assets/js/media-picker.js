window.AxerMediaPicker = {
    open: function(options) {
        options = options || {};
        const onSelect = options.onSelect || function(url) {};

        let modal = document.getElementById('axerMediaPickerModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'axerMediaPickerModal';
            modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;padding:2rem;';
            modal.innerHTML = `
                <div style="background:#ffffff;border-radius:0.75rem;width:100%;max-width:850px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
                    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
                        <h3 style="margin:0;font-size:1.1rem;font-weight:600;">Select Media Asset</h3>
                        <button type="button" id="closeMediaPicker" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b;">&times;</button>
                    </div>
                    <div id="mediaPickerGrid" style="padding:1.5rem;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill, minmax(130px,1fr));gap:1rem;flex-grow:1;">
                        <p style="color:#64748b;grid-column:1/-1;">Loading media files...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            document.getElementById('closeMediaPicker').addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        modal.style.display = 'flex';

        fetch('/admin/api/media')
            .then(res => res.json())
            .then(data => {
                const grid = document.getElementById('mediaPickerGrid');
                grid.innerHTML = '';
                if (!data || !data.length) {
                    grid.innerHTML = '<p style="color:#64748b;grid-column:1/-1;">No media files uploaded yet.</p>';
                    return;
                }
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.style.cssText = 'height:110px;border-radius:0.375rem;background:#f1f5f9;cursor:pointer;overflow:hidden;border:2px solid transparent;position:relative;';
                    div.innerHTML = `<img src="${item.path}" style="width:100%;height:100%;object-fit:cover;">`;
                    div.addEventListener('click', function() {
                        onSelect(item.path, item);
                        modal.style.display = 'none';
                    });
                    grid.appendChild(div);
                });
            });
    }
};
