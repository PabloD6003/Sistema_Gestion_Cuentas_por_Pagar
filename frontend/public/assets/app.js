(function () {
    'use strict';

    const currency = document.body.dataset.currency || '₡';
    // Mismo formato que PHP number_format: 1,130.50
    const numberFormatter = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function formatMoney(value) {
        return currency + numberFormatter.format(Number(value) || 0);
    }

    function initLineItems() {
        const container = document.getElementById('detalle-lineas');
        const template = document.getElementById('linea-template');
        const addButton = document.getElementById('agregar-linea');

        if (!container || !template || !addButton) {
            return;
        }

        let index = container.querySelectorAll('.linea').length;

        function recalc() {
            let subtotal = 0;
            let tax = 0;

            container.querySelectorAll('.linea').forEach(function (row) {
                const quantity = parseFloat(row.querySelector('.cantidad')?.value || '0') || 0;
                const price = parseFloat(row.querySelector('.precio')?.value || '0') || 0;
                const taxPct = parseFloat(row.querySelector('.impuesto')?.value || '0') || 0;
                const lineSubtotal = quantity * price;
                const lineTax = lineSubtotal * (taxPct / 100);
                const lineTotal = lineSubtotal + lineTax;

                subtotal += lineSubtotal;
                tax += lineTax;

                const totalCell = row.querySelector('.linea-total');
                if (totalCell) {
                    totalCell.textContent = lineTotal > 0 ? formatMoney(lineTotal) : '—';
                }
            });

            const subtotalNode = document.getElementById('resumen-subtotal');
            const taxNode = document.getElementById('resumen-impuesto');
            const totalNode = document.getElementById('resumen-total');

            if (subtotalNode) subtotalNode.textContent = formatMoney(subtotal);
            if (taxNode) taxNode.textContent = formatMoney(tax);
            if (totalNode) totalNode.textContent = formatMoney(subtotal + tax);
        }

        function bind(row) {
            row.querySelectorAll('input').forEach(function (input) {
                input.addEventListener('input', recalc);
            });

            const removeButton = row.querySelector('.remove-linea');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    if (container.querySelectorAll('.linea').length > 1) {
                        row.remove();
                    } else {
                        row.querySelectorAll('input').forEach(function (input) {
                            input.value = input.classList.contains('impuesto') ? '13' : '';
                        });
                        row.querySelector('.cantidad').value = '1';
                    }
                    recalc();
                });
            }
        }

        container.querySelectorAll('.linea').forEach(bind);

        addButton.addEventListener('click', function () {
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            index += 1;
            container.insertAdjacentHTML('beforeend', html);
            bind(container.lastElementChild);
            recalc();
        });

        recalc();
    }

    function initPaymentDiscount() {
        const form = document.getElementById('pago-form');
        if (!form) {
            return;
        }

        const checkbox = document.getElementById('aplicar_descuento');
        const amount = document.getElementById('monto-pago');

        if (!checkbox || !amount) {
            return;
        }

        const saldo = Number(form.dataset.saldo || '0');
        const net = Number(form.dataset.net || '0');

        checkbox.addEventListener('change', function () {
            amount.value = checkbox.checked ? net.toFixed(2) : saldo.toFixed(2);
        });
    }

    function initReportTabs() {
        const tabs = document.querySelectorAll('.reporte-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (other) {
                    other.parentElement?.classList.remove('is-active');
                });
                tab.parentElement?.classList.add('is-active');
            });
        });
    }

    function initFlashClose() {
        document.querySelectorAll('.notification .delete').forEach(function (button) {
            button.addEventListener('click', function () {
                button.parentElement?.remove();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLineItems();
        initPaymentDiscount();
        initReportTabs();
        initFlashClose();
    });
})();
