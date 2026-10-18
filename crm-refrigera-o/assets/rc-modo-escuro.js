/**
 * Modo Escuro — Refrigeração CRM
 * Alterna entre modo claro/escuro no admin do WordPress.
 * Preferência guardada no localStorage.
 *
 * @package Refrigeracao_CRM
 * @version 3.6.2
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'rc_dark_mode';
    var DARK_CLASS  = 'rc-dark-mode';

    /* Wrappers de localStorage */
    function lsGet(k) {
        try { return localStorage.getItem(k); } catch (e) { return null; }
    }
    function lsSet(k, v) {
        try { localStorage.setItem(k, v); } catch (e) { /* ignore */ }
    }

    function prefereSistemaEscuro() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    /* Aplica a classe ao <html> o mais cedo possível */
    function aplicarPreferencia() {
        var salvo = lsGet(STORAGE_KEY);
        var escuro = false;

        if (salvo === 'dark')        escuro = true;
        else if (salvo === 'light')  escuro = false;
        else                         escuro = prefereSistemaEscuro();

        document.documentElement.classList.toggle(DARK_CLASS, escuro);
    }

    aplicarPreferencia();

    /* Quando o DOM estiver pronto, criar o botão */
    function inicializar() {
        try {
            // Sincronizar <body> com <html>
            var escuroInicial = document.documentElement.classList.contains(DARK_CLASS);
            document.body.classList.toggle(DARK_CLASS, escuroInicial);

            // Se já existir, não duplicar
            if (document.getElementById('rc-dark-mode-toggle')) {
                console.log('ℹ️ [RC] Botão de modo escuro já existia');
                return;
            }

            // Criar botão toggle
            var btn = document.createElement('button');
            btn.id = 'rc-dark-mode-toggle';
            btn.type = 'button';
            btn.title = 'Alternar modo escuro';
            btn.setAttribute('aria-label', 'Alternar modo escuro');

            function atualizarIcone() {
                var escuro = document.body.classList.contains(DARK_CLASS);
                btn.textContent = escuro ? '☀️' : '🌙';
                btn.setAttribute('aria-pressed', escuro ? 'true' : 'false');
            }

            atualizarIcone();

            btn.addEventListener('click', function () {
                var escuro = document.body.classList.contains(DARK_CLASS);
                document.body.classList.toggle(DARK_CLASS, !escuro);
                document.documentElement.classList.toggle(DARK_CLASS, !escuro);
                lsSet(STORAGE_KEY, escuro ? 'light' : 'dark');
                atualizarIcone();
            });

            document.body.appendChild(btn);

            // Escutar mudanças no sistema
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                    if (!lsGet(STORAGE_KEY)) {
                        document.body.classList.toggle(DARK_CLASS, e.matches);
                        document.documentElement.classList.toggle(DARK_CLASS, e.matches);
                        atualizarIcone();
                    }
                });
            }

            console.log('✅ [RC] Botão de modo escuro criado com sucesso');

        } catch (err) {
            console.error('❌ [RC] Erro ao criar botão de modo escuro:', err);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
})();