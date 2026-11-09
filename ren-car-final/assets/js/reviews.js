function debounce(func, delay) {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func(...args), delay);
    };
}

function formatDateTH(dateString) {
    if (!dateString) return '-';
    const parts = String(dateString).split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateString;
}

async function searchReviews(searchQuery) {
    const loadingSpan = document.getElementById('searchLoading');
    const averageSpan = document.getElementById('averageRatingValue');
    loadingSpan.style.display = 'inline';

    try {
        const response = await fetch(`../api/search_review.php?search=${encodeURIComponent(searchQuery)}`);
        const data = await response.json();
        averageSpan.textContent = parseFloat(data.averageRating || 0).toFixed(1);
        updateReviewTable(data.reviews);
        updateRatingChart(data.ratingCounts);
    } catch (error) {
        console.error('Error fetching review data:', error);
    }

    loadingSpan.style.display = 'none';
}

function updateReviewTable(reviews) {
    const tableBody = document.getElementById('reviewTableBody');
    tableBody.innerHTML = '';

    reviews.forEach(review => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(review.reviews_id)}</td>
            <td>${escapeHtml(review.comment)}</td>
            <td>${escapeHtml(review.rating)}/5</td>
            <td>${escapeHtml(formatDateTH(review.review_date))}</td>
        `;
        tableBody.appendChild(row);
    });

    if (reviews.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="4" class="text-center empty-state">ไม่พบข้อมูลรีวิว</td>';
        tableBody.appendChild(row);
    }
}

function updateRatingChart(ratingCounts) {
    const chart = window.ratingChart;
    chart.data.labels = ratingCounts.map(item => `คะแนน ${item.rating}`);
    chart.data.datasets[0].data = ratingCounts.map(item => parseInt(item.count, 10));
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
    const searchInput = document.getElementById('reviewSearch');
    if (!searchInput) return;

    const debouncedSearch = debounce(function () {
        searchReviews(searchInput.value.trim());
    }, 400);

    searchInput.addEventListener('input', debouncedSearch);
    searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchReviews(searchInput.value.trim());
        }
    });
});
