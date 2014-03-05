<?php

require_once __DIR__ .'/../../vendor/autoload.php';

if (isset($_GET['display'])) {
    $captcha = new \Flywheel\Captcha\Math();
    $captcha->show();
    exit;
}

session_start();


if (isset($_POST['submit'])) {
    $input = $_POST['captcha'];
    if (\Flywheel\Captcha\Math::check($input)) {
        echo "<p>CORRECT!</p>";
    } else {
        echo "<p>WRONG!</p>";
    }
}
?>

<form action="" method="POST">
    <img src="<?php echo $_SERVER['PHP_SELF'] ?>?display" alt="Captcha Image">
    <input placeholder="Nhập kết quả phép tính bên cạnh" value="<?php echo @$input ?>" type="text" name="captcha" size="50">
    <input name="submit" value="submit" type="submit">
</form>