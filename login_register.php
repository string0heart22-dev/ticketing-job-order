<?php 
// Use centralized session initialization
require_once 'session_init.php';
require_once "config.php";

if(isset($_POST['register'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = strtolower($_POST['role']);

    // Add page-specific permission columns
    $pages = ['tickets_installation', 'tickets', 'tickets_maintenance', 'tickets_pullout', 'service_areas', 'service_plans', 'inventory', 'reports', 'users', 'installation_form', 'olt'];
    foreach ($pages as $page) {
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS can_$page TINYINT(1) DEFAULT 1");
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS can_delete_$page TINYINT(1) DEFAULT 1");
    }

    // Get permission values
    $permissions = [];
    foreach ($pages as $page) {
        $permissions["can_$page"] = isset($_POST["can_$page"]) ? 1 : 0;
        $permissions["can_delete_$page"] = isset($_POST["can_delete_$page"]) ? 1 : 0;
    }

    // Build INSERT query
    $columns = "name, email, password, role";
    $values = "'$name', '$email', '$password', '$role'";
    foreach ($pages as $page) {
        $columns .= ", can_$page, can_delete_$page";
        $values .= ", {$permissions["can_$page"]}, {$permissions["can_delete_$page"]}";
    }

    $checkEmail = $conn->query("SELECT email FROM users WHERE email='$email'");
    if($checkEmail->num_rows > 0){
        $_SESSION['register_error'] = "Email already exists.";
        $_SESSION['active_form'] = "register";
        header("Location: USERs.php");
        exit();
    } else {
        $conn->query("INSERT INTO users ($columns) VALUES ($values)");
        header("Location: USERs.php");
        exit();
    }
}


if (isset($_POST['login'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
    if($result->num_rows > 0){
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])){
            $_SESSION['userID'] = $user['userID'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['name']; // For request.php compatibility
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Store page-specific permissions
            $pages = ['tickets_installation', 'tickets', 'tickets_maintenance', 'tickets_pullout', 'service_areas', 'service_plans', 'inventory', 'reports', 'users', 'installation_form', 'olt'];
            foreach ($pages as $page) {
                $_SESSION["can_$page"] = $user["can_$page"] ?? 1;
                $_SESSION["can_delete_$page"] = $user["can_delete_$page"] ?? 1;
            }

            $_SESSION['LAST_ACTIVITY'] = time(); // Set initial activity timestamp
            
            // Redirect to dashboard
               if($user['role'] == 'admin' || $user['role'] == 'Employee') {
                header("Location: htmlpage.php");
                exit();
            } else {
                
                header("Location: PAGEFORUSER/htmlpage.php ");
                exit();
            }
        } else {
            // Password is incorrect
            $_SESSION['login_error'] = "Invalid email or password.";
            $_SESSION['active_form'] = "login";
            header("Location: Login.php");
            exit();
        }
    } else {
        // Email not found
        $_SESSION['login_error'] = "Invalid email or password.";
        $_SESSION['active_form'] = "login";
        header("Location: Login.php");
        exit();
    }
}

// If we reach here without login or register, redirect to login
header("Location: Login.php");
exit();
?>