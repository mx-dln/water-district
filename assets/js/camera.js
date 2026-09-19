let selectedFile = null;

function setupFileInput(inputId, previewId, onSelect) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input) return;
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        compressImageFile(file, function(compressedBlob) {
            selectedFile = new File([compressedBlob], 'selfie.jpg', { type: 'image/jpeg' });
            if (preview) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    preview.src = ev.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(compressedBlob);
            }
            if (onSelect) onSelect(selectedFile);
        });
    });
}

function compressImageFile(file, callback) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    const reader = new FileReader();
    reader.onload = function(e) {
        img.onload = function() {
            let w = img.width, h = img.height;
            const maxW = 1280, maxH = 1280;
            if (w > maxW || h > maxH) {
                const ratio = Math.min(maxW / w, maxH / h);
                w = Math.round(w * ratio);
                h = Math.round(h * ratio);
            }
            canvas.width = w;
            canvas.height = h;
            ctx.drawImage(img, 0, 0, w, h);
            canvas.toBlob(function(blob) {
                callback(blob);
            }, 'image/jpeg', 0.6);
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function getSelectedFile() {
    return selectedFile;
}

function resetFileInput(inputId) {
    const input = document.getElementById(inputId);
    if (input) input.value = '';
    selectedFile = null;
}
