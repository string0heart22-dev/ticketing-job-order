<?php
// Role-based access control helper functions

function getUserRole() {
    return strtolower($_SESSION['role'] ?? 'user');
}

function isRegularUser() {
    return getUserRole() === 'user';
}

function isEmployee() {
    return getUserRole() === 'employee';
}

function isRestrictedUser() {
    return isRegularUser() || isEmployee();
}

function isAdmin() {
    return getUserRole() === 'admin';
}

/**
 * Call at the top of any admin-only page.
 * If the logged-in user is an Employee (or plain User), redirect them to the user portal.
 * If not logged in at all, redirect to Login.
 */
function requireAdminRole() {
    if (!isset($_SESSION['userID'])) {
        header('Location: Login.php');
        exit;
    }
    if (isRestrictedUser()) {
        header('Location: /PAGEFORUSER/htmlpage.php');
        exit;
    }
}

function canDelete() {
    return isAdmin(); // Only admin can delete
}

function canAccessNOCClear() {
    return isAdmin(); // Only admin can access NOC Clear
}

function canAccessCSClear() {
    return isAdmin(); // Only admin can access CS Clear
}

function canAccessUserPage() {
    return isAdmin(); // Only admin can access Users page
}

function canAccessServiceAreas() {
    return isAdmin(); // Only admin can access Service Areas
}

function canAccessServicePlans() {
    return isAdmin(); // Only admin can access Service Plans
}

function canCreateMaintenanceTicket() {
    return isAdmin(); // Only admin can create maintenance tickets
}

function canCreatePullOutTicket() {
    return isAdmin(); // Only admin can create pull out tickets
}

function canAssignTickets() {
    return isAdmin(); // Only admin can assign tickets
}

function canClaimTickets() {
    return isRestrictedUser(); // User and Employee can claim
}

function canEditTechnicalDetails() {
    return true; // All users can edit technical details
}

function canUploadImages() {
    return true; // All users can upload images
}

function canViewTickets() {
    return true; // All users can view tickets
}
