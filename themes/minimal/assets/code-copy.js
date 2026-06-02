document.addEventListener('DOMContentLoaded', () => {
    const codeBlocks = document.querySelectorAll('.content pre');

    codeBlocks.forEach((pre) => {
        if (pre.closest('.code-block') !== null) {
            return;
        }

        const code = pre.querySelector('code');
        const wrapper = document.createElement('div');
        const button = document.createElement('button');

        wrapper.className = 'code-block';
        button.className = 'code-copy-button';
        button.type = 'button';
        button.setAttribute('aria-label', 'Copy Code');
        button.title = 'Copy Code';
        button.textContent = 'Copy';

        pre.parentNode?.insertBefore(wrapper, pre);
        wrapper.appendChild(pre);
        wrapper.appendChild(button);

        button.addEventListener('click', async () => {
            try {
                await copyText((code ?? pre).textContent ?? '');
                button.classList.add('copied');
                button.textContent = 'Copied';
                button.setAttribute('aria-label', 'Copied');
                button.title = 'Copied';
                setTimeout(() => {
                    button.classList.remove('copied');
                    button.textContent = 'Copy';
                    button.setAttribute('aria-label', 'Copy Code');
                    button.title = 'Copy Code';
                }, 1600);
            } catch {
                button.classList.add('copy-failed');
                button.textContent = 'Failed';
                setTimeout(() => {
                    button.classList.remove('copy-failed');
                    button.textContent = 'Copy';
                }, 1600);
            }
        });
    });

    async function copyText(text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            await navigator.clipboard.writeText(text);
            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '-1000px';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
        } finally {
            textarea.remove();
        }
    }
});
