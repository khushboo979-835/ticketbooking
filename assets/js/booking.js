/**
 * assets/js/booking.js - Millennium India Group Ticket Booking Engine
 * Handles real-time pricing calculations, quantity selectors & validations
 */

document.addEventListener('DOMContentLoaded', function() {
    const qtyInputs = document.querySelectorAll('.qty-input');
    const grandTotalEl = document.getElementById('grand-total-display');
    const totalQtyEl = document.getElementById('total-qty-display');
    const totalPeopleEl = document.getElementById('total-people-display');
    const proceedBtn = document.getElementById('proceed-btn');
    const summaryItemsContainer = document.getElementById('summary-items-list');

    function calculateTotals() {
        let grandTotal = 0;
        let totalTickets = 0;
        let totalPeople = 0;
        let itemsHtml = '';

        qtyInputs.forEach(input => {
            const qty = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price) || 0;
            const peoplePerTicket = parseInt(input.dataset.people) || 1;
            const name = input.dataset.name || 'Pass';
            const max = parseInt(input.dataset.max) || 10;

            if (qty > 0) {
                const subtotal = qty * price;
                grandTotal += subtotal;
                totalTickets += qty;
                totalPeople += (qty * peoplePerTicket);

                itemsHtml += `
                    <div class="summary-row">
                        <div>
                            <strong>${name}</strong>
                            <div style="font-size:0.85rem; color:#aaa;">Qty: ${qty} &times; ₹${price.toFixed(2)} (${qty * peoplePerTicket} Persons)</div>
                        </div>
                        <div style="font-weight:700; color:#ffd966;">₹${subtotal.toFixed(2)}</div>
                    </div>
                `;
            }
        });

        if (grandTotalEl) grandTotalEl.textContent = '₹' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        if (totalQtyEl) totalQtyEl.textContent = totalTickets;
        if (totalPeopleEl) totalPeopleEl.textContent = totalPeople;
        
        if (summaryItemsContainer) {
            summaryItemsContainer.innerHTML = itemsHtml || '<div style="color:#aaa; text-align:center; padding:10px 0;">No passes selected yet. Use the + button above.</div>';
        }

        if (proceedBtn) {
            if (totalTickets > 0) {
                proceedBtn.disabled = false;
                proceedBtn.classList.remove('disabled');
            } else {
                proceedBtn.disabled = true;
                proceedBtn.classList.add('disabled');
            }
        }
    }

    // Attach listeners to increment/decrement buttons
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const action = this.dataset.action;
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);

            if (!input) return;

            let val = parseInt(input.value) || 0;
            const min = parseInt(input.min) || 0;
            const max = parseInt(input.max) || 10;

            if (action === 'increase' && val < max) {
                input.value = val + 1;
            } else if (action === 'decrease' && val > min) {
                input.value = val - 1;
            }

            // Trigger change event
            input.dispatchEvent(new Event('change'));
        });
    });

    qtyInputs.forEach(input => {
        input.addEventListener('change', calculateTotals);
        input.addEventListener('keyup', calculateTotals);
    });

    // Run initial calculation
    calculateTotals();
});
