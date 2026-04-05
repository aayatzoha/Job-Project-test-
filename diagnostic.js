// Diagnostic Script for Company Dashboard
// Add this to the browser console to diagnose issues

console.log('=== COMPANY DASHBOARD DIAGNOSTIC ===\n');

// Test 1: Check if window.dashboardManager exists
console.log('1. Checking window.dashboardManager...');
if (typeof window.dashboardManager === 'undefined') {
    console.error('   ❌ FAIL: window.dashboardManager is undefined');
} else {
    console.log('   ✅ PASS: window.dashboardManager exists');
    console.log('   Type:', typeof window.dashboardManager);
    console.log('   Constructor:', window.dashboardManager.constructor.name);
}

// Test 2: Check if showJobCreationForm method exists
console.log('\n2. Checking showJobCreationForm method...');
if (typeof window.dashboardManager === 'undefined') {
    console.error('   ❌ SKIP: window.dashboardManager is undefined');
} else if (typeof window.dashboardManager.showJobCreationForm === 'undefined') {
    console.error('   ❌ FAIL: showJobCreationForm method does not exist');
} else {
    console.log('   ✅ PASS: showJobCreationForm method exists');
    console.log('   Type:', typeof window.dashboardManager.showJobCreationForm);
}

// Test 3: Check if showAssessmentCreationForm method exists
console.log('\n3. Checking showAssessmentCreationForm method...');
if (typeof window.dashboardManager === 'undefined') {
    console.error('   ❌ SKIP: window.dashboardManager is undefined');
} else if (typeof window.dashboardManager.showAssessmentCreationForm === 'undefined') {
    console.error('   ❌ FAIL: showAssessmentCreationForm method does not exist');
} else {
    console.log('   ✅ PASS: showAssessmentCreationForm method exists');
    console.log('   Type:', typeof window.dashboardManager.showAssessmentCreationForm);
}

// Test 4: Check global functions
console.log('\n4. Checking global functions...');
if (typeof showJobCreationForm === 'undefined') {
    console.error('   ❌ FAIL: showJobCreationForm global function is undefined');
} else {
    console.log('   ✅ PASS: showJobCreationForm global function exists');
}

if (typeof showAssessmentCreationForm === 'undefined') {
    console.error('   ❌ FAIL: showAssessmentCreationForm global function is undefined');
} else {
    console.log('   ✅ PASS: showAssessmentCreationForm global function exists');
}

// Test 5: Check if buttons exist
console.log('\n5. Checking for buttons...');
const jobButtons = document.querySelectorAll('[onclick*="showJobCreationForm"]');
const assessmentButtons = document.querySelectorAll('[onclick*="showAssessmentCreationForm"]');
console.log('   Job creation buttons found:', jobButtons.length);
console.log('   Assessment creation buttons found:', assessmentButtons.length);

if (jobButtons.length > 0) {
    console.log('   First job button onclick:', jobButtons[0].getAttribute('onclick'));
}
if (assessmentButtons.length > 0) {
    console.log('   First assessment button onclick:', assessmentButtons[0].getAttribute('onclick'));
}

// Test 6: Check loaded scripts
console.log('\n6. Checking loaded scripts...');
const scripts = Array.from(document.querySelectorAll('script[src]'));
const relevantScripts = scripts.filter(s => 
    s.src.includes('dashboard') || 
    s.src.includes('company') || 
    s.src.includes('main.js') || 
    s.src.includes('auth.js')
);
console.log('   Relevant scripts loaded:');
relevantScripts.forEach(script => {
    console.log('   -', script.src);
});

// Test 7: Try to call the function
console.log('\n7. Testing function call...');
console.log('   Attempting to call window.dashboardManager.showJobCreationForm()...');
try {
    if (typeof window.dashboardManager !== 'undefined' && 
        typeof window.dashboardManager.showJobCreationForm === 'function') {
        console.log('   ✅ Function is callable (not calling to avoid modal)');
        console.log('   To test, run: window.dashboardManager.showJobCreationForm()');
    } else {
        console.error('   ❌ Function is not callable');
    }
} catch (error) {
    console.error('   ❌ Error:', error.message);
}

// Test 8: Check CompanyDashboardManager class
console.log('\n8. Checking CompanyDashboardManager class...');
if (typeof CompanyDashboardManager === 'undefined') {
    console.error('   ❌ FAIL: CompanyDashboardManager class is not defined');
} else {
    console.log('   ✅ PASS: CompanyDashboardManager class exists');
    console.log('   Prototype methods:', Object.getOwnPropertyNames(CompanyDashboardManager.prototype));
}

// Test 9: Check DashboardManager base class
console.log('\n9. Checking DashboardManager base class...');
if (typeof DashboardManager === 'undefined') {
    console.error('   ❌ FAIL: DashboardManager class is not defined');
} else {
    console.log('   ✅ PASS: DashboardManager class exists');
}

// Test 10: Check for JavaScript errors
console.log('\n10. Checking for JavaScript errors...');
console.log('   Check the console above for any red error messages');

console.log('\n=== DIAGNOSTIC COMPLETE ===');
console.log('\nTo test the buttons manually:');
console.log('1. Run: window.dashboardManager.showJobCreationForm()');
console.log('2. Run: window.dashboardManager.showAssessmentCreationForm()');
console.log('3. Or click the buttons in the UI');

