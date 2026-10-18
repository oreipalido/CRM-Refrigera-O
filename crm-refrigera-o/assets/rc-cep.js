/**
 * Refrigeração CRM — CEP / Geocodificação
 * - CEP → Endereço + Cidade + Coordenadas (GeoAPI.pt)
 * - Endereço + Cidade → CEP + Coordenadas (Nominatim)
 *
 * @package Refrigeracao_CRM
 * @version 3.5.2
 */
document.addEventListener('DOMContentLoaded', function () {

    'use strict';

    /* ============================================================
       1. CEP → Endereço + Cidade + Coordenadas (via GeoAPI.pt)
       ============================================================ */
    const cepInput = document.getElementById('rc_cep');

    if (cepInput) {
        cepInput.addEventListener('blur', function () {
            const cep = this.value.replace(/\D/g, '');
            if (!/^\d{7}$/.test(cep)) return;

            const cepFormatado = cep.slice(0, 4) + '-' + cep.slice(4);

            fetch('https://json.geoapi.pt/cp/' + cepFormatado, {
                headers: { 'User-Agent': 'RefrigeracaoCRM/1.0' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const cidadeInput   = document.getElementById('rc_cidade');
                const enderecoInput = document.getElementById('rc_endereco');

                if (cidadeInput && data['Designação Postal']) {
                    cidadeInput.value = data['Designação Postal'];
                }
                if (enderecoInput && data['Artéria']) {
                    enderecoInput.value = data['Artéria'];
                }
                if (data['Latitude'] && data['Longitude']) {
                    rc_guardar_coordenadas(data['Latitude'], data['Longitude'], cepInput);
                }
            })
            .catch(function (error) {
                console.error('Erro ao consultar CEP:', error);
            });
        });
    }

    /* ============================================================
       2. Endereço + Cidade → CEP + Coordenadas (via Nominatim)
       ============================================================ */
    const btnBuscarCep = document.getElementById('rc-buscar-cep');

    if (btnBuscarCep) {
        btnBuscarCep.addEventListener('click', function (e) {
            e.preventDefault();

            const enderecoInput = document.getElementById('rc_endereco');
            const cidadeInput   = document.getElementById('rc_cidade');

            const endereco = enderecoInput ? enderecoInput.value.trim() : '';
            const cidade   = cidadeInput   ? cidadeInput.value.trim()   : '';

            if (!endereco || !cidade) {
                alert('Preencha o endereço e a cidade primeiro.');
                return;
            }

            btnBuscarCep.disabled = true;
            btnBuscarCep.textContent = '⏳ A procurar...';

            const query = encodeURIComponent(endereco + ', ' + cidade + ', Portugal');
            const url   = 'https://nominatim.openstreetmap.org/search?q=' + query + '&format=json&limit=1&addressdetails=1';

            fetch(url, {
                headers: { 'User-Agent': 'RefrigeracaoCRM/1.0' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btnBuscarCep.disabled = false;
                btnBuscarCep.textContent = '🔍 Procurar CEP pelo endereço';

                if (!data || !data[0]) {
                    alert('Endereço não encontrado. Verifique os dados introduzidos.');
                    return;
                }

                const resultado = data[0];
                const address   = resultado.address || {};

                const cepField = document.getElementById('rc_cep');
                if (cepField && address.postcode) {
                    cepField.value = address.postcode;
                }

                if (cidadeInput && !cidadeInput.value) {
                    cidadeInput.value = address.city || address.town || address.village || '';
                }

                rc_guardar_coordenadas(resultado.lat, resultado.lon, btnBuscarCep);
            })
            .catch(function (err) {
                btnBuscarCep.disabled = false;
                btnBuscarCep.textContent = '🔍 Procurar CEP pelo endereço';
                console.error('Erro na geocodificação:', err);
                alert('Erro ao procurar o endereço. Tente novamente.');
            });
        });
    }

    /* ============================================================
       Auxiliar: guardar coordenadas em campos hidden
       ============================================================ */
    function rc_guardar_coordenadas(lat, lon, elementoReferencia) {
        if (!lat || !lon || !elementoReferencia) return;

        const form = elementoReferencia.closest('form');
        if (!form) return;

        let latField = document.getElementById('rc_latitude');
        let lonField = document.getElementById('rc_longitude');

        if (!latField) {
            latField = document.createElement('input');
            latField.type = 'hidden';
            latField.id   = 'rc_latitude';
            latField.name = 'latitude';
            form.appendChild(latField);
        }
        if (!lonField) {
            lonField = document.createElement('input');
            lonField.type = 'hidden';
            lonField.id   = 'rc_longitude';
            lonField.name = 'longitude';
            form.appendChild(lonField);
        }

        latField.value = lat;
        lonField.value = lon;
    }
});