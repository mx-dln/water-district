document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
});

function initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('-translate-x-full');
        });
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 1024) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.add('-translate-x-full');
                }
            }
        });
    }
}

function flashMessage() {
    return {
        show: false,
        message: '',
        type: 'info',
        bgClass: 'bg-blue-500',
        init() {
            const flashEl = document.querySelector('[x-data="flashMessage()"]');
            if (flashEl) {
                const msg = flashEl.getAttribute('data-message');
                const type = flashEl.getAttribute('data-type');
                if (msg) {
                    this.message = msg;
                    this.type = type || 'info';
                    this.setBg();
                    this.show = true;
                    setTimeout(() => { this.show = false; }, 5000);
                }
            }
        },
        setBg() {
            const map = { success: 'bg-green-500', danger: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
            this.bgClass = map[this.type] || 'bg-blue-500';
        }
    };
}
