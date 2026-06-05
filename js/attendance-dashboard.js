document.addEventListener('DOMContentLoaded', function() {
    // DOM elements
    const tdSelector = document.getElementById('td-selector');
    const semesterSelector = document.getElementById('semester-selector');
    const dateSelector = document.getElementById('date-selector');
    const refreshBtn = document.getElementById('refresh-btn');
    const loadingElement = document.getElementById('loading');
    const dashboardContent = document.getElementById('dashboard-content');
    
    // Set today's date as default
    const today = new Date();
    const formattedDate = today.toISOString().split('T')[0];
    dateSelector.value = formattedDate;
    
    // Initialize selectors and fetch data
    function initialize() {
        // Try to get saved TD and semester from session
        fetch('../attendance/api_proxy.php?action=get_session')
            .then(response => response.json())
            .then(data => {
                if (data.td) {
                    tdSelector.value = data.td;
                }
                if (data.semester) {
                    semesterSelector.value = data.semester;
                }
                // Fetch data after initializing selectors
                setTimeout(fetchAttendanceData, 100);
            })
            .catch(error => {
                console.error('Error fetching session:', error);
                // Fetch data anyway
                setTimeout(fetchAttendanceData, 100);
            });
    }
    
    // Fetch attendance data
    function fetchAttendanceData() {
        showLoading(true);
        
        const td = tdSelector.value;
        const semester = semesterSelector.value;
        const date = dateSelector.value;
        
        // Save current TD and semester to session
        fetch('../attendance/api_proxy.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=save_session&td=${encodeURIComponent(td)}&semester=${encodeURIComponent(semester)}`
        });
        
        // Fetch attendance statistics
        fetch(`../attendance/api_proxy.php?action=get_stats&td=${encodeURIComponent(td)}&semester=${encodeURIComponent(semester)}&date=${encodeURIComponent(date)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTotalStats(data.stats.total);
                    updateMaleStats(data.stats.male);
                    updateFemaleStats(data.stats.female);
                    updatePlatoonStats(data.stats.platoons);
                    fetchRecentAttendance(td, semester, date);
                } else {
                    showError(data.message || 'Failed to fetch attendance data');
                }
                showLoading(false);
            })
            .catch(error => {
                console.error('Error fetching attendance data:', error);
                showError('Network error. Please try again.');
                showLoading(false);
            });
    }
    
    // Fetch recent attendance records
    function fetchRecentAttendance(td, semester, date) {
        fetch(`../attendance/api_proxy.php?action=get_recent&td=${encodeURIComponent(td)}&semester=${encodeURIComponent(semester)}&date=${encodeURIComponent(date)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRecentAttendance(data.records);
                }
            })
            .catch(error => console.error('Error fetching recent attendance:', error));
    }
    
    // Update total statistics
    function updateTotalStats(total) {
        document.getElementById('total-strength').textContent = total.strength;
        document.getElementById('total-present').textContent = total.present;
        document.getElementById('total-absent').textContent = total.absent;
        document.getElementById('total-percentage').textContent = total.percentage + '%';
        document.getElementById('total-progress').style.width = total.percentage + '%';
    }
    
    // Update male statistics
    function updateMaleStats(male) {
        document.getElementById('male-strength').textContent = male.strength;
        document.getElementById('male-present').textContent = male.present;
        document.getElementById('male-absent').textContent = male.absent;
        document.getElementById('male-percentage').textContent = male.percentage + '%';
        document.getElementById('male-progress').style.width = male.percentage + '%';
    }
    
    // Update female statistics
    function updateFemaleStats(female) {
        document.getElementById('female-strength').textContent = female.strength;
        document.getElementById('female-present').textContent = female.present;
        document.getElementById('female-absent').textContent = female.absent;
        document.getElementById('female-percentage').textContent = female.percentage + '%';
        document.getElementById('female-progress').style.width = female.percentage + '%';
    }
    
    // Update platoon statistics
    function updatePlatoonStats(platoons) {
        const platoonStatsContainer = document.getElementById('platoon-stats');
        const noPlatoonDataElement = document.getElementById('no-platoon-data');
        
        // Clear previous platoon cards
        platoonStatsContainer.innerHTML = '';
        
        if (platoons && platoons.length > 0) {
            noPlatoonDataElement.style.display = 'none';
            
            platoons.forEach(platoon => {
                const platoonCard = document.createElement('div');
                platoonCard.className = 'col-xl-3 col-md-6 mb-4';
                
                const percentage = platoon.strength > 0 ? Math.round((platoon.present / platoon.strength) * 100) : 0;
                
                platoonCard.innerHTML = `
                    <div class="card shadow h-100">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">${platoon.name}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center mb-3">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Attendance Rate</div>
                                    <div class="row no-gutters align-items-center">
                                        <div class="col-auto">
                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">${percentage}%</div>
                                        </div>
                                        <div class="col">
                                            <div class="progress progress-sm mr-2">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: ${percentage}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <div class="mt-3">
                                        <h4>${platoon.strength}</h4>
                                        <p class="text-muted">Total</p>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="mt-3">
                                        <h4>${platoon.present}</h4>
                                        <p class="text-muted">Present</p>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="mt-3">
                                        <h4>${platoon.absent}</h4>
                                        <p class="text-muted">Absent</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                platoonStatsContainer.appendChild(platoonCard);
            });
        } else {
            noPlatoonDataElement.style.display = 'block';
        }
    }
    
    // Update recent attendance records
    function updateRecentAttendance(records) {
        const tableBody = document.getElementById('recent-attendance-table');
        const noDataElement = document.getElementById('no-recent-data');
        
        // Clear previous records
        tableBody.innerHTML = '';
        
        if (records && records.length > 0) {
            noDataElement.style.display = 'none';
            
            records.forEach(record => {
                const row = document.createElement('tr');
                
                // Format timestamp
                const timestamp = new Date(record.timestamp);
                const formattedTime = timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                row.innerHTML = `
                    <td>${formattedTime}</td>
                    <td>${record.student_id}</td>
                    <td>${record.name}</td>
                    <td>${record.platoon}</td>
                    <td>${record.gender}</td>
                `;
                
                tableBody.appendChild(row);
            });
        } else {
            noDataElement.style.display = 'block';
        }
    }
    
    // Show/hide loading indicator
    function showLoading(show) {
        if (show) {
            loadingElement.style.display = 'block';
            dashboardContent.style.opacity = '0.5';
        } else {
            loadingElement.style.display = 'none';
            dashboardContent.style.opacity = '1';
        }
    }
    
    // Show error message
    function showError(message) {
        // You can implement a more sophisticated error display here
        console.error(message);
        alert('Error: ' + message);
    }
    
    // Event listeners
    refreshBtn.addEventListener('click', fetchAttendanceData);
    tdSelector.addEventListener('change', fetchAttendanceData);
    semesterSelector.addEventListener('change', fetchAttendanceData);
    dateSelector.addEventListener('change', fetchAttendanceData);
    
    // Initialize the dashboard
    initialize();
});