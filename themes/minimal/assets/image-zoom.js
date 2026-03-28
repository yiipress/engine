document.addEventListener('DOMContentLoaded', () => {
    const contentImages = document.querySelectorAll('.content img');
    
    if (contentImages.length === 0) return;
    
    const overlay = document.createElement('div');
    overlay.className = 'image-zoom-overlay';
    overlay.innerHTML = '<img class="image-zoom-content" alt=""><button class="image-zoom-close" aria-label="Close">&times;</button>';
    document.body.appendChild(overlay);
    
    const zoomImg = overlay.querySelector('.image-zoom-content');
    const closeBtn = overlay.querySelector('.image-zoom-close');
    
    contentImages.forEach(img => {
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', () => {
            zoomImg.src = img.src;
            zoomImg.alt = img.alt;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });
    
    const closeZoom = () => {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    };
    
    closeBtn.addEventListener('click', closeZoom);
    zoomImg.addEventListener('click', closeZoom);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeZoom();
    });
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            closeZoom();
        }
    });
});
