@extends('layouts.app')

@section('title', 'Session Expiry Test')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">🧪 Session Expiry Test Page</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle me-2"></i>Test Mode Active</h5>
                        <p class="mb-0">
                            This page is configured for testing session expiry with a 2-minute timeout.
                            The warning will appear 30 seconds before expiry.
                        </p>
                        <hr>
                        <p class="mb-0">
                            <strong>To test session expiry:</strong><br>
                            1. First log in through the main application (<a href="{{ url('/') }}" target="_blank">Login here</a>)<br>
                            2. Then return to this test page to see the session monitoring in action
                        </p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Test Instructions</h6>
                                    <ol>
                                        <li>Wait for the warning dialog to appear (30 seconds before expiry)</li>
                                        <li>Test "Keep Me Logged In" button</li>
                                        <li>Test "Log Out Now" button</li>
                                        <li>Test automatic logout when session expires</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Current Settings</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Session Lifetime:</strong> 2 minutes</li>
                                        <li><strong>Warning Time:</strong> 30 seconds</li>
                                        <li><strong>Check Interval:</strong> 5 seconds</li>
                                        <li><strong>Test Mode:</strong> Active</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Session Status</h6>
                        <div id="session-status" class="alert alert-secondary">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            Loading session status...
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Test Actions</h6>
                        <button class="btn btn-primary me-2" onclick="testExtendSession()">
                            <i class="fas fa-clock me-1"></i>
                            Test Extend Session
                        </button>
                        <button class="btn btn-warning me-2" onclick="testSessionStatus()">
                            <i class="fas fa-info-circle me-1"></i>
                            Check Session Status
                        </button>
                        <button class="btn btn-danger" onclick="testLogout()">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            Test Logout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Test functions
async function testExtendSession() {
    try {
        const apiUrl = '{{ url("/api/extend-session") }}';
        console.log('Calling API URL:', apiUrl);
        
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Session extended successfully!');
            updateSessionStatus();
        } else {
            alert('❌ Failed to extend session: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Extend session error:', error);
        alert('❌ Error: ' + error.message);
    }
}

async function testSessionStatus() {
    try {
        const apiUrl = '{{ url("/api/session-status") }}';
        console.log('Calling API URL:', apiUrl);
        
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        updateSessionStatus(data);
    } catch (error) {
        console.error('Session status error:', error);
        document.getElementById('session-status').innerHTML = 
            '<div class="alert alert-danger">❌ Error loading session status: ' + error.message + '</div>';
    }
}

function testLogout() {
    if (confirm('Are you sure you want to log out?')) {
        window.location.href = '/logout';
    }
}

function updateSessionStatus(data = null) {
    const statusDiv = document.getElementById('session-status');
    
    if (data) {
        if (data.success) {
            const timeLeft = Math.floor(data.time_until_expiry / 60);
            const secondsLeft = data.time_until_expiry % 60;
            
            statusDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Session Active</strong><br>
                    <small>Time until expiry: ${timeLeft}:${secondsLeft.toString().padStart(2, '0')}</small>
                </div>
            `;
        } else {
            const isNoSession = data.message && data.message.includes('No session data found');
            const alertClass = isNoSession ? 'alert-warning' : 'alert-danger';
            const icon = isNoSession ? 'fa-exclamation-triangle' : 'fa-times-circle';
            const title = isNoSession ? 'Not Logged In' : 'Session Expired';
            
            statusDiv.innerHTML = `
                <div class="alert ${alertClass}">
                    <i class="fas ${icon} me-2"></i>
                    <strong>${title}</strong><br>
                    <small>${data.message || 'Session is no longer valid'}</small>
                    ${isNoSession ? '<br><small class="text-muted">Please log in through the main application to test session expiry.</small>' : ''}
                </div>
            `;
        }
    } else {
        statusDiv.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Session Status</strong><br>
                <small>Click "Check Session Status" to see current status</small>
            </div>
        `;
    }
}

// Load session status on page load
document.addEventListener('DOMContentLoaded', function() {
    testSessionStatus();
    
    // Update status every 10 seconds
    setInterval(testSessionStatus, 10000);
});
</script>
@endsection
