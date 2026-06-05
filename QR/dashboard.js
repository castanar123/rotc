    // Cache-busting helper to avoid CDN/browser cached responses
    function cacheBust(url) {
        try {
            const sep = url.includes('?') ? '&' : '?';
            return `${url}${sep}_=${Date.now()}`;
        } catch (e) {
            return url;
        }
    }

document.addEventListener('DOMContentLoaded', function() {
    // Get DOM elements
    const tdSelector = document.getElementById('td-selector');
    const semesterSelector = document.getElementById('semesterFilter');
    const dateSelector = document.getElementById('dateFilter');
    const refreshBtn = document.getElementById('refresh-btn');
    const loadingElement = document.getElementById('loading-state');
    const noDataElement = document.getElementById('no-data-state');
    const dashboardContent = document.getElementById('dashboard-content');
    
    // Default to today's date
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    dateSelector.value = `${yyyy}-${mm}-${dd}`;
    
    // Populate TD selector
    function populateTDSelector() {
        // Fetch TDs from the training_days table
        fetch(cacheBust('session.php?action=get_training_days'))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.training_days) {
                    tdSelector.innerHTML = '';
                    data.training_days.forEach(td => {
                        const option = document.createElement('option');
                        option.value = td.td_id;
                        option.textContent = td.label;
                        tdSelector.appendChild(option);
                    });
                    
                    // Try to get saved TD from session
                    fetch(cacheBust('session.php?action=get_session'))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.td) {
                                tdSelector.value = data.td;
                            }
                        })
                        .catch(error => console.error('Error fetching session:', error));
                } else {
                    // Fallback to numeric values if API fails
                    tdSelector.innerHTML = '';
                    for (let i = 1; i <= 15; i++) {
                        const option = document.createElement('option');
                        option.value = i;
                        option.textContent = `${i}${i === 1 ? 'st' : i === 2 ? 'nd' : i === 3 ? 'rd' : 'th'} TD`;
                        tdSelector.appendChild(option);
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching training days:', error);
                // Fallback to numeric values
                tdSelector.innerHTML = '';
                for (let i = 1; i <= 15; i++) {
                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = `${i}${i === 1 ? 'st' : i === 2 ? 'nd' : i === 3 ? 'rd' : 'th'} TD`;
                    tdSelector.appendChild(option);
                }
            });
    }
    
    // Initialize semester selector from session
    function initializeSemesterSelector() {
        fetch(cacheBust('session.php?action=get_session'))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.semester) {
                    semesterSelector.value = data.semester;
                }
            })
            .catch(error => console.error('Error fetching session:', error));
    }
    
    // Fetch attendance data
    function fetchAttendanceData() {
        showLoading(true);
        
        const td = tdSelector.value;
        const semester = semesterSelector.value;
        const date = dateSelector.value;
        
        // Check if we have valid TD and semester values
        if (!td || !semester) {
            showLoading(false);
            return;
        }
        
        // Save current TD and semester to session
        fetch('session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_session&td=${encodeURIComponent(td)}&semester=${encodeURIComponent(semester)}`
        });
        
        // Fetch attendance statistics
        const apiUrl = cacheBust(`session.php?action=get_stats&td=${encodeURIComponent(td)}&semester=${encodeURIComponent(semester)}&date=${encodeURIComponent(date)}`);
        
        fetch(apiUrl)
            .then(response => {
                console.log('API Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                console.log('API Response text:', text);
                console.log('Response length:', text.length);
                console.log('First 10 chars:', JSON.stringify(text.substring(0, 10)));
                
                // Clean the response text of any potential BOM or whitespace
                const cleanText = text.trim().replace(/^\uFEFF/, '');
                console.log('Cleaned text length:', cleanText.length);
                console.log('Cleaned first 10 chars:', JSON.stringify(cleanText.substring(0, 10)));
                
                try {
                    const data = JSON.parse(cleanText);
                    console.log('Parsed API data:', data);
                    if (data.success) {
                        console.log('API success, calling updateDashboard with:', data.stats);
                        updateDashboard(data.stats);
                        fetchRecentAttendance(td, semester, date);
                    } else {
                        console.log('API returned error:', data.message);
                        showError(data.message || 'Failed to fetch attendance data');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Failed to parse text:', JSON.stringify(cleanText));
                    showError('Invalid response from server. Check console for details.');
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
        const url = cacheBust(`session.php?action=get_recent_attendance&td=${encodeURIComponent(td)}&semester=${encodeURIComponent(semester)}&date=${encodeURIComponent(date)}`);
        fetch(url)
            .then(response => response.text())
            .then(text => {
                const raw = typeof text === 'string' ? text : '';
                const clean = raw.trim().replace(/^\uFEFF/, '');
                if (!clean) {
                    console.warn('Recent attendance returned empty body');
                    return;
                }
                try {
                    const data = JSON.parse(clean);
                    if (data && data.success) {
                        updateRecentAttendance(data.records);
                    } else {
                        console.warn('Recent attendance API error:', data && data.message);
                    }
                } catch (e) {
                    console.error('Error parsing recent attendance JSON:', e);
                    console.error('Response text (first 300 chars):', clean.substring(0, 300));
                }
            })
            .catch(error => console.error('Error fetching recent attendance:', error));
    }
    
    // Update dashboard with attendance statistics
    function updateDashboard(stats) {
        console.log('updateDashboard called with stats:', stats);
        
        // Safety check for stats structure
        if (!stats || !stats.total) {
            console.log('No stats or stats.total, using defaults');
            stats = {
                total: {strength: 0, present: 0, absent: 0, percentage: 0},
                by_gender: {},
                by_platoon: {}
            };
        }
        
        // Update total statistics
        const totalStrengthEl = document.getElementById('total-strength');
        const totalPresentEl = document.getElementById('total-present');
        const totalAbsentEl = document.getElementById('total-absent');
        const attendanceRateEl = document.getElementById('attendance-rate');
        
        if (totalStrengthEl) totalStrengthEl.textContent = stats.total.strength;
        if (totalPresentEl) totalPresentEl.textContent = stats.total.present;
        if (totalAbsentEl) totalAbsentEl.textContent = stats.total.absent;
        if (attendanceRateEl) attendanceRateEl.textContent = stats.total.percentage + '%';
        
        // Update male/female statistics
        // Handle multiple possible keys: male/Male/M, female/Female/F
        const bg = stats.by_gender || {};
        const maleStats = bg.male || bg.Male || bg.M || bg.m || { strength: 0, present: 0, absent: 0, percentage: 0 };
        const femaleStats = bg.female || bg.Female || bg.F || bg.f || { strength: 0, present: 0, absent: 0, percentage: 0 };
        const fallbackMale = (stats.total && typeof stats.total.male_strength !== 'undefined') ? stats.total.male_strength : 0;
        const fallbackFemale = (stats.total && typeof stats.total.female_strength !== 'undefined') ? stats.total.female_strength : 0;
        const maleCount = Number(maleStats.strength || fallbackMale || 0);
        const femaleCount = Number(femaleStats.strength || fallbackFemale || 0);

        // Elements: top cards (Male Students / Female Students) use ids 'male-present' and 'female-present'.
        // We intentionally display TOTAL counts there (strength), not "present" counts.
        const maleStrengthEl = document.getElementById('male-strength'); // Gender card total male
        const maleTopCardEl = document.getElementById('male-present');    // Top card value
        const maleTopPctEl = document.getElementById('male-percentage');   // Top card percentage text

        const femaleStrengthEl = document.getElementById('female-strength'); // Gender card total female
        const femaleTopCardEl = document.getElementById('female-present');    // Top card value
        const femaleTopPctEl = document.getElementById('female-percentage');  // Top card percentage text

        // Set totals for gender card
        if (maleStrengthEl) maleStrengthEl.textContent = maleCount;
        if (femaleStrengthEl) femaleStrengthEl.textContent = femaleCount;

        // Compute roster-based percentages (constant, independent of date filters)
        const rosterTotal = maleCount + femaleCount;
        const maleRosterPct = rosterTotal > 0 ? Math.round((maleCount / rosterTotal) * 100) : 0;
        const femaleRosterPct = rosterTotal > 0 ? Math.round((femaleCount / rosterTotal) * 100) : 0;

        // Update top cards to show TOTAL counts and roster share
        if (maleTopCardEl) maleTopCardEl.textContent = maleCount;
        if (femaleTopCardEl) femaleTopCardEl.textContent = femaleCount;
        if (maleTopPctEl) maleTopPctEl.textContent = maleRosterPct + '%';
        if (femaleTopPctEl) femaleTopPctEl.textContent = femaleRosterPct + '%';
        
        // Update gender visual stats
        updateGenderStats(stats);
        
        // Update platoon statistics
        updatePlatoonStats(stats.by_platoon);
        
        // Update charts
        updateCharts(stats);
        
        // Update recent activity
        updateRecentActivity(stats.recent_activity || []);
    }
    
    function updatePlatoonStats(platoonData) {
    const container = document.getElementById('platoon-stats-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (platoonData && Object.keys(platoonData).length > 0) {
        Object.keys(platoonData).forEach(platoonName => {
            const platoon = platoonData[platoonName];
            const platoonItem = document.createElement('div');
            platoonItem.className = 'platoon-item';
            
            const platoonNameEl = document.createElement('span');
            platoonNameEl.className = 'platoon-name';
            platoonNameEl.textContent = platoonName;
            
            const platoonAttendance = document.createElement('span');
            platoonAttendance.className = 'platoon-attendance';
            platoonAttendance.textContent = `(${platoon.present}/${platoon.strength}) ${platoon.percentage}%`;
            
            platoonItem.appendChild(platoonNameEl);
            platoonItem.appendChild(platoonAttendance);
            container.appendChild(platoonItem);
        });
    } else {
        container.innerHTML = '<p class="no-data">No platoon data available</p>';
    }
}
    
    function updateGenderStats(stats) {
        // Default values
        const defaultStats = {strength: 0, present: 0, absent: 0};
        
        // Extract gender data - handle both cases (Male/Female or male/female)
        const maleStats = stats.by_gender?.Male || stats.by_gender?.male || defaultStats;
        const femaleStats = stats.by_gender?.Female || stats.by_gender?.female || defaultStats;
        
        // Update gender visual bars
        const maleBar = document.querySelector('.male-bar .bar-fill');
        const femaleBar = document.querySelector('.female-bar .bar-fill');
        
        if (maleBar && femaleBar) {
            const totalStudents = maleStats.strength + femaleStats.strength;
            
            const malePercentage = totalStudents > 0 ? (maleStats.strength / totalStudents) * 100 : 0;
            const femalePercentage = totalStudents > 0 ? (femaleStats.strength / totalStudents) * 100 : 0;
            
            maleBar.style.width = malePercentage + '%';
            femaleBar.style.width = femalePercentage + '%';
        }
        
        // Update all gender-related elements
        const elements = {
            'male-present-stat': maleStats.present || 0,
            'female-present-stat': femaleStats.present || 0,
            'male-strength': maleStats.strength || 0,
            'female-strength': femaleStats.strength || 0
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }
    
    // Create a row for platoon statistics
    function createPlatoonStatRow(label, value) {
        const row = document.createElement('p');
        
        const labelSpan = document.createElement('span');
        labelSpan.textContent = label;
        
        const valueSpan = document.createElement('span');
        valueSpan.className = 'percentage';
        valueSpan.textContent = value;
        
        row.appendChild(labelSpan);
        row.appendChild(valueSpan);
        
        return row;
    }
    
    // Chart instances
    let attendanceChart = null;
    let platoonChart = null;
    
    // Initialize charts
    function initializeCharts() {
        initializeAttendanceChart();
        initializePlatoonChart();
    }
    
    // Initialize Attendance Trends Chart
    function initializeAttendanceChart() {
        const ctx = document.getElementById('attendanceChart');
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (attendanceChart) {
            attendanceChart.destroy();
        }
        
        attendanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Attendance Rate',
                    data: [85, 92, 78, 88, 95, 82, 90],
                    borderColor: '#00ff88',
                    backgroundColor: 'rgba(0, 255, 136, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#ffffff',
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        min: 0,
                        max: 100
                    }
                }
            }
        });
    }
    
    // Initialize Platoon Breakdown Chart
    function initializePlatoonChart() {
        const ctx = document.getElementById('platoonChart');
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (platoonChart) {
            platoonChart.destroy();
        }
        
        platoonChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Alpha', 'Bravo', 'Charlie', 'Delta'],
                datasets: [{
                    data: [25, 30, 20, 25],
                    backgroundColor: [
                        '#00ff88',
                        '#0088ff',
                        '#ff8800',
                        '#ff0088'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#ffffff',
                            padding: 20
                        }
                    }
                }
            }
        });
    }
    
    // Update charts with real data
    function updateCharts(stats) {
        updateAttendanceChart(stats);
        updatePlatoonChart(stats.by_platoon);
    }
    
    // Update Attendance Chart with real data
    function updateAttendanceChart(stats) {
        if (!attendanceChart || !stats) return;
        
        // For now, use sample data - in a real implementation, 
        // you would fetch historical attendance data
        const currentRate = stats.total ? stats.total.percentage : 0;
        const weekData = [currentRate - 10, currentRate - 5, currentRate + 2, currentRate - 3, currentRate, currentRate + 5, currentRate - 2];
        
        attendanceChart.data.datasets[0].data = weekData;
        attendanceChart.update();
    }
    
    // Update Platoon Chart with real data
    function updatePlatoonChart(platoonData) {
        if (!platoonChart || !platoonData) return;
        
        const labels = Object.keys(platoonData);
        const data = Object.values(platoonData).map(p => p.present);
        const colors = ['#00ff88', '#0088ff', '#ff8800', '#ff0088', '#8800ff', '#ff4400'];
        
        if (labels.length > 0) {
            platoonChart.data.labels = labels;
            platoonChart.data.datasets[0].data = data;
            platoonChart.data.datasets[0].backgroundColor = colors.slice(0, labels.length);
            platoonChart.update();
        }
    }
    
    // Update recent attendance table
    function updateRecentAttendance(records) {
        const tableBody = document.getElementById('recent-attendance-table');
        if (!tableBody) return;
        
        tableBody.innerHTML = '';
        
        if (!records || records.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.className = 'no-data';
            cell.textContent = 'No attendance records for today';
            row.appendChild(cell);
            tableBody.appendChild(row);
            return;
        }
        
        records.forEach(record => {
            const row = document.createElement('tr');
            
            // Format timestamp
            const timestamp = new Date(record.timestamp);
            const formattedTime = timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            // Create cells
            const timeCell = document.createElement('td');
            timeCell.textContent = formattedTime;
            
            const idCell = document.createElement('td');
            idCell.textContent = record.student_id || 'N/A';
            
            const nameCell = document.createElement('td');
            nameCell.textContent = record.name || 'N/A';
            
            const platoonCell = document.createElement('td');
            platoonCell.textContent = record.platoon || 'Not assigned';
            
            const genderCell = document.createElement('td');
            genderCell.textContent = record.gender || 'N/A';
            
            const statusCell = document.createElement('td');
            statusCell.innerHTML = '<span class="status-badge present"><i class="fas fa-check"></i> Present</span>';
            
            // Append cells to row
            row.appendChild(timeCell);
            row.appendChild(idCell);
            row.appendChild(nameCell);
            row.appendChild(platoonCell);
            row.appendChild(genderCell);
            row.appendChild(statusCell);
            
            // Append row to table
            tableBody.appendChild(row);
        });
    }
    
    // Show/hide loading indicator
    function showLoading(show) {
        loadingElement.style.display = show ? 'block' : 'none';
        dashboardContent.style.display = show ? 'none' : 'block';
    }
    
    // Show error message
    function showError(message) {
        alert(message);
    }
    
    function updateRecentActivity(recentData) {
        const container = document.getElementById('recent-activity-list');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (recentData && recentData.length > 0) {
            recentData.forEach(record => {
                const activityItem = document.createElement('div');
                activityItem.className = 'activity-item';
                
                const time = new Date(record.timestamp).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                activityItem.innerHTML = `
                    <div class="activity-info">
                        <span class="activity-name">${record.name}</span>
                        <span class="activity-platoon">${record.platoon || 'N/A'}</span>
                    </div>
                    <div class="activity-time">${time}</div>
                `;
                
                container.appendChild(activityItem);
            });
        } else {
            container.innerHTML = '<div class="no-data">No recent activity</div>';
        }
    }
    
    // Event listeners
    refreshBtn.addEventListener('click', fetchAttendanceData);
    
    tdSelector.addEventListener('change', fetchAttendanceData);
    semesterSelector.addEventListener('change', fetchAttendanceData);
    dateSelector.addEventListener('change', fetchAttendanceData);
    

    
    // Initialize
    populateTDSelector();
    initializeSemesterSelector();
    initializeCharts();
    
    // Initial fetch without overriding selectors; relies on session and populated TD list
    function initializeAndFetch() {
        fetchAttendanceData();
    }
    
    // Initialize with delay to ensure DOM is ready
    setTimeout(initializeAndFetch, 500);
});