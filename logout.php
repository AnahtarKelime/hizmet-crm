<?php
session_start();
session_destroy();
setcookie('remember_token', '', time() - 3600, '/');
?>
<script>
    localStorage.removeItem('login_status');
    window.location.href = 'index.php';
</script>
<?php exit; ?>