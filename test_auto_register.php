<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automated Registration Tester</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .test-controls {
            margin-bottom: 30px;
            padding: 20px;
            background: #e8f4fd;
            border-radius: 5px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background: #0056b3;
        }
        .log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Automated Registration Tester</h1>
        
        <div class="test-controls">
            <h3>Test Controls</h3>
            <button onclick="runSingleTest()">Run Single Registration Test</button>
            <button onclick="runMultipleTests()">Run 5 Registration Tests</button>
            <button onclick="clearLog()">Clear Log</button>
            <button onclick="viewRegistrations()">View All Registrations</button>
        </div>
        
        <div id="log" class="log">
            <div class="info">Ready to run tests...</div>
        </div>
    </div>

    <script>
        let testCounter = 1;
        
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = type;
            logEntry.innerHTML = `[${timestamp}] ${message}`;
            logDiv.appendChild(logEntry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }
        
        function clearLog() {
            document.getElementById('log').innerHTML = '<div class="info">Log cleared...</div>';
        }
        
        function generateTestData() {
            const firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa', 'James', 'Maria'];
            const lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
            const middleNames = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
            const courses = ['BSIT', 'BSCS', 'BSCE', 'BSME', 'BSEE', 'BSBA', 'BSED', 'BSHRM'];
            const sections = ['A', 'B', 'C', 'D'];
            const religions = ['Catholic', 'Protestant', 'Islam', 'Buddhist', 'Other'];
            const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
            const skinColors = ['Fair', 'Medium', 'Dark', 'Olive'];
            const platoons = ['Alpha', 'Bravo', 'Charlie', 'Delta'];
            
            const randomFirst = firstNames[Math.floor(Math.random() * firstNames.length)];
            const randomLast = lastNames[Math.floor(Math.random() * lastNames.length)];
            const randomMiddle = middleNames[Math.floor(Math.random() * middleNames.length)];
            const randomNum = Math.floor(Math.random() * 9000) + 1000;
            
            return {
                student_number: `2024${randomNum}`,
                full_name: `${randomFirst} ${randomMiddle}. ${randomLast}`,
                gender: Math.random() > 0.5 ? 'Male' : 'Female',
                email: `test${testCounter}_${randomFirst.toLowerCase()}${randomNum}@test.com`,
                address: `${Math.floor(Math.random() * 999) + 1} Test Street, Test City`,
                contact_number: `09${Math.floor(Math.random() * 900000000) + 100000000}`,
                course: courses[Math.floor(Math.random() * courses.length)],
                section: sections[Math.floor(Math.random() * sections.length)],
                religion: religions[Math.floor(Math.random() * religions.length)],
                birthdate: `199${Math.floor(Math.random() * 10)}-${String(Math.floor(Math.random() * 12) + 1).padStart(2, '0')}-${String(Math.floor(Math.random() * 28) + 1).padStart(2, '0')}`,
                place_of_birth: 'Test City, Test Province',
                height: `${Math.floor(Math.random() * 30) + 150}`,
                weight: `${Math.floor(Math.random() * 40) + 50}`,
                skin_color: skinColors[Math.floor(Math.random() * skinColors.length)],
                blood_type: bloodTypes[Math.floor(Math.random() * bloodTypes.length)],
                father_name: `Father ${randomLast}`,
                father_occupation: 'Engineer',
                mother_name: `Mother ${randomLast}`,
                mother_occupation: 'Teacher',
                guardian_name: `Guardian ${randomLast}`,
                guardian_contact: `09${Math.floor(Math.random() * 900000000) + 100000000}`,
                guardian_relationship: 'Parent',
                guardian_address: `${Math.floor(Math.random() * 999) + 1} Guardian Street, Test City`,
                platoon: platoons[Math.floor(Math.random() * platoons.length)],
                username: `test_user_${testCounter}_${randomNum}`,
                password: 'TestPass123!',
                confirm_password: 'TestPass123!'
            };
        }
        
        async function submitRegistration(testData) {
            const formData = new FormData();
            
            // Add all test data to form
            Object.keys(testData).forEach(key => {
                formData.append(key, testData[key]);
            });
            
            // Create dummy files for photo and signature
            const dummyFile = new File(['dummy'], 'test.jpg', { type: 'image/jpeg' });
            formData.append('photo', dummyFile);
            formData.append('signature', dummyFile);
            
            try {
                const response = await fetch('register.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.text();
                return { success: response.ok, result: result };
            } catch (error) {
                return { success: false, result: error.message };
            }
        }
        
        async function runSingleTest() {
            log(`Starting test #${testCounter}...`, 'info');
            
            const testData = generateTestData();
            log(`Generated test data for: ${testData.full_name} (${testData.email})`, 'info');
            
            const result = await submitRegistration(testData);
            
            if (result.success) {
                if (result.result.includes('Registration successful') || result.result.includes('success')) {
                    log(`✓ Test #${testCounter} PASSED - Registration successful`, 'success');
                } else if (result.result.includes('error') || result.result.includes('Error')) {
                    log(`✗ Test #${testCounter} FAILED - ${result.result.substring(0, 200)}...`, 'error');
                } else {
                    log(`? Test #${testCounter} UNCLEAR - Response: ${result.result.substring(0, 200)}...`, 'info');
                }
            } else {
                log(`✗ Test #${testCounter} FAILED - Network error: ${result.result}`, 'error');
            }
            
            testCounter++;
        }
        
        async function runMultipleTests() {
            log('Starting batch test (5 registrations)...', 'info');
            
            for (let i = 0; i < 5; i++) {
                await runSingleTest();
                // Small delay between tests
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
            
            log('Batch test completed!', 'info');
        }
        
        function viewRegistrations() {
            window.open('view_registrations.php', '_blank');
        }
    </script>
</body>
</html>