/* Filtros que se aplicam ao trocar a seleção */
document.querySelectorAll('[data-auto-enviar]').forEach((s) => s.addEventListener('change', () => s.form.submit()));
