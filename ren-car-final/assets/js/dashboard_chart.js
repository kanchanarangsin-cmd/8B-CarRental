// dashboard_chart.js
const ctx = document.getElementById('dashboardChart').getContext('2d');
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Available', 'Rented', 'Maintenance'],
        datasets: [{
            label: 'Cars',
            data: [available, rented, maintenance],
            backgroundColor: ['green', 'blue', 'red']
        }]
    }
});