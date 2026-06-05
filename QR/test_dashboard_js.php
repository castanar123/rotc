<?php
// Test dashboard JavaScript functionality
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard JS Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-result { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .info { background-color: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <h1>Dashboard JavaScript Test</h1>
    
    <div id="test-results"></div>
    
    <!-- Mock dashboard elements -->
    <div style="display: none;">
        <select id="td-selector"><option value="1">1st TD</option></select>
        <select id="semester-selector"><option value="1">1st Semester</option></select>
        <input type="date" id="date-selector" value="2025-08-08">
        
        <div id="total-strength">0</div>
        <div id="total-present">0</div>
        <div id="total-absent">0</div>
        <div id="attendance-rate">0%</div>
        
        <div id="male-strength">0</div>
        <div id="male-present">0</div>
        <div id="male-percentage">0%</div>
        
        <div id="female-strength">0</div>
        <div id="female-present">0</div>
        <div id="female-percentage">0%</div>
        
        <div id="platoon-stats-container"></div>
        <div id="loading-indicator" style="display: none;">Loading...</div>
    </div>
    
    <script>
        function addTestResult(message, type = 'info') {
            const div = document.createElement('div');
            div.className = `test-result ${type}`;
            div.textContent = new Date().toLocaleTimeString() + ': ' + message;
            document.getElementById('test-results').appendChild(div);
        }
        
        // Test API endpoint
        addTestResult('Testing API endpoint...', 'info');
        
        fetch('session.php?action=get_stats&td=1&semester=1&date=2025-08-08')
            .then(response => {
                addTestResult(`API Response status: ${response.status}`, response.ok ? 'success' : 'error');
                return response.text();
            })
            .then(text => {
                addTestResult(`API Response received: ${text.length} characters`, 'info');
                try {
                    const data = JSON.parse(text);
                    addTestResult(`JSON parsed successfully`, 'success');
                    
                    if (data.success) {
                        addTestResult(`API returned success: true`, 'success');
                        addTestResult(`Total strength: ${data.stats.total.strength}`, 'info');
                        addTestResult(`Total present: ${data.stats.total.present}`, 'info');
                        addTestResult(`Attendance rate: ${data.stats.total.percentage}%`, 'info');
                        
                        // Test dashboard update function
                        if (typeof updateDashboard === 'function') {
                            updateDashboard(data.stats);
                            addTestResult('Dashboard update function called successfully', 'success');
                        } else {
                            addTestResult('updateDashboard function not found', 'error');
                        }
                    } else {
                        addTestResult(`API returned error: ${data.message}`, 'error');
                    }
                } catch (e) {
                    addTestResult(`JSON parse error: ${e.message}`, 'error');
                    addTestResult(`Raw response: ${text}`, 'error');
                }
            })
            .catch(error => {
                addTestResult(`Network error: ${error.message}`, 'error');
            });
    </script>
</body>
</html>