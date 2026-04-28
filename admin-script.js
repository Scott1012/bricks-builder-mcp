// Bricks MCP Admin Script
function toggleTool(event, contentId) {
    event.preventDefault();
    const content = document.getElementById(contentId);
    const btn = event.currentTarget.querySelector('.toggle-tool');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        btn.classList.add('active');
    } else {
        content.style.display = 'none';
        btn.classList.remove('active');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('✓ Bricks MCP Admin loaded');

    // Validate JSON before form submission
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const schemas = document.querySelectorAll('.tool-schema-input');
            let isValid = true;

            schemas.forEach(input => {
                const value = input.value.trim();
                if (value) {
                    try {
                        JSON.parse(value);
                    } catch (error) {
                        isValid = false;
                        input.style.borderColor = '#ef4444';
                        alert('JSON invalide:\n\n' + error.message);
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
            }
        });
    }

    // Pretty print JSON
    document.querySelectorAll('.tool-schema-input').forEach(textarea => {
        const value = textarea.value.trim();
        if (value) {
            try {
                const parsed = JSON.parse(value);
                textarea.value = JSON.stringify(parsed, null, 2);
            } catch (e) {
                console.warn('Invalid JSON:', e);
            }
        }
    });

    // Character counter for descriptions
    document.querySelectorAll('.tool-description-input').forEach(textarea => {
        const initialCount = textarea.value.length;
        const counter = document.createElement('small');
        counter.style.cssText = 'color: #9ca3af; display: block; margin-top: 6px; font-size: 13px;';
        counter.innerHTML = 'Caractères: <strong>' + initialCount + '</strong>';
        textarea.parentNode.insertBefore(counter, textarea.nextSibling);

        textarea.addEventListener('input', function() {
            counter.querySelector('strong').textContent = this.value.length;
        });
    });

    // Test MCP API
    const endpoint = window.location.origin + '/wp-json/bricks-mcp/v1/tools-config';
    fetch(endpoint)
        .then(response => response.json())
        .then(data => {
            console.log('✓ MCP Server actif:', data);
        })
        .catch(error => {
            console.error('✗ Erreur MCP:', error);
        });
});