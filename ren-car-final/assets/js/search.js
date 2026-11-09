// search.js
document.getElementById('searchInput').addEventListener('input', function() {
    const query = this.value;
    const path = window.location.pathname;
    let apiUrl = '';

    if (path.includes('cars.php')) {
        apiUrl = '../api/search_car.php';
    } else if (path.includes('customers.php')) {
        apiUrl = '../api/search_customer.php';
    } else if (path.includes('employees.php')) {
        apiUrl = '../api/search_employee.php';
    }

    if (apiUrl) {
        fetch(`${apiUrl}?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.querySelector('tbody');
                tbody.innerHTML = '';
                data.forEach(item => {
                    let row = '';
                    if (path.includes('cars.php')) {
                        row = `<tr>
                            <td>${item.car_id}</td>
                            <td>${item.license_plate}</td>
                            <td>${item.brand}</td>
                            <td>${item.model}</td>
                            <td>${item.year}</td>
                            <td>${item.color}</td>
                            <td>${item.seats}</td>
                            <td>${item.daily_rate}</td>
                            <td>${item.status}</td>
                            <td>${item.mileage}</td>
                            <td>
                                <button class='btn btn-sm btn-warning' onclick='editCar(${item.car_id})'>Edit</button>
                                <a href='?delete=${item.car_id}' class='btn btn-sm btn-danger' onclick='return confirm("Delete?")'>Delete</a>
                            </td>
                        </tr>`;
                    } else if (path.includes('customers.php')) {
                        row = `<tr>
                            <td>${item.customer_id}</td>
                            <td>${item.name}</td>
                            <td>${item.phone}</td>
                            <td>${item.id_card}</td>
                            <td>${item.address}</td>
                            <td>
                                <button class='btn btn-sm btn-warning' onclick='editCustomer(${item.customer_id})'>Edit</button>
                                <a href='?delete=${item.customer_id}' class='btn btn-sm btn-danger' onclick='return confirm("Delete?")'>Delete</a>
                            </td>
                        </tr>`;
                    } else if (path.includes('employees.php')) {
                        row = `<tr>
                            <td>${item.employee_id}</td>
                            <td>${item.name}</td>
                            <td>${item.position}</td>
                            <td>${item.status}</td>
                            <td>
                                <button class='btn btn-sm btn-warning' onclick='editEmployee(${item.employee_id})'>Edit</button>
                                <a href='?delete=${item.employee_id}' class='btn btn-sm btn-danger' onclick='return confirm("Delete?")'>Delete</a>
                            </td>
                        </tr>`;
                    }
                    tbody.innerHTML += row;
                });
                filterRows();
            });
    }
});

function filterRows() {
    const path = window.location.pathname;
    if (path.includes('cars.php')) {
        filterByStatus();
    } else if (path.includes('employees.php')) {
        filterByPosition();
    }
}

function filterByStatus() {
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const statusCell = row.cells[8].textContent;
        if (status === '' || statusCell === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterByPosition() {
    const position = document.getElementById('positionFilter').value;
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const positionCell = row.cells[2].textContent;
        if (position === '' || positionCell === position) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

if (document.getElementById('statusFilter')) {
    document.getElementById('statusFilter').addEventListener('change', filterByStatus);
}
if (document.getElementById('positionFilter')) {
    document.getElementById('positionFilter').addEventListener('change', filterByPosition);
}