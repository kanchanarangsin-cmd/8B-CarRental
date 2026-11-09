function debounce(func, delay) {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func(...args), delay);
    };
}

function translatePaymentType(type) {
    return (window.paymentTypeLabelMap && window.paymentTypeLabelMap[type]) || type;
}

function formatMoneyTH(amount) {
    return '฿' + new Intl.NumberFormat('th-TH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(amount || 0));
}

function formatDateTH(dateString) {
    if (!dateString) return '-';
    const parts = String(dateString).split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateString;
}

async function searchPayments(searchQuery) {
    const loadingSpan = document.getElementById('searchLoading');
    const totalIncomeSpan = document.getElementById('totalIncomeValue');
    const latestPaymentDateSpan = document.getElementById('latestPaymentDateValue');

    loadingSpan.style.display = 'inline';

    try {
        const response = await fetch(`../api/search_payment.php?search=${encodeURIComponent(searchQuery)}`);
        const data = await response.json();

        totalIncomeSpan.textContent = new Intl.NumberFormat('th-TH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(data.totalIncome);

        if (latestPaymentDateSpan) {
            latestPaymentDateSpan.textContent = data.latestPaymentDate ? formatDateTH(data.latestPaymentDate) : '-';
        }

        updateTable(data.payments);
        updateChart(data.incomeByType);
    } catch (error) {
        console.error('Error fetching payment data:', error);
    }

    loadingSpan.style.display = 'none';
}

function updateTable(payments) {
    const tableBody = document.getElementById('paymentTableBody');
    tableBody.innerHTML = '';

    payments.forEach(payment => {
        const customerText = `${payment.customer_id || '-'}${payment.customer_name ? ' - ' + payment.customer_name : ''}`;
        const carText = `${payment.car_id || '-'}${payment.car_name ? ' - ' + payment.car_name : ''}`;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(payment.payment_id)}</td>
            <td>${escapeHtml(payment.rental_id || '-')}</td>
            <td>${escapeHtml(formatDateTH(payment.payment_date))}</td>
            <td class="text-currency">${escapeHtml(formatMoneyTH(payment.amount))}</td>
            <td>${escapeHtml(translatePaymentType(payment.payment_type))}</td>
            <td>${escapeHtml(customerText)}</td>
            <td>${escapeHtml(carText)}</td>
        `;
        tableBody.appendChild(row);
    });

    if (payments.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="7" class="text-center empty-state">ไม่พบข้อมูลการชำระเงิน</td>';
        tableBody.appendChild(row);
    }
}

function updateChart(incomeByType) {
    const chart = window.paymentChart;
    const labels = incomeByType.map(item => translatePaymentType(item.payment_type));
    const data = incomeByType.map(item => parseFloat(item.total));
    const backgroundColor = incomeByType.map(item => window.paymentTypeColorMap[item.payment_type] || '#6B55B4');

    chart.data.labels = labels;
    chart.data.datasets[0].data = data;
    chart.data.datasets[0].backgroundColor = backgroundColor;
    chart.update('active');
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#6B55B4;'
    };
    return String(text ?? '').replace(/[&<>"']/g, char => map[char]);
}

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('paymentSearch');
    if (!searchInput) return;

    const debouncedSearch = debounce(function () {
        searchPayments(searchInput.value.trim());
    }, 400);

    searchInput.addEventListener('input', debouncedSearch);
    searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchPayments(searchInput.value.trim());
        }
    });
});
