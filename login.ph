session_start();
$username = $password = $userError = $passError = '';
if(isset($_POST['sub'])){
$username = $_POST['username']; $password = $_POST['password'];
if($username === 'admin' && $password === 'password'){
$_SESSION['login'] = true; header('LOCATION:trylog.php'); die();
}
if($username !== 'admin')$userError = 'Invalid Username';
if($password !== 'password')$passError = 'Invalid Password';
}
?>
<div id="right">
   <div id="login">
  <form name="form1" method="post" action="">
     <br />
     <label for="me">Username</label>
     <br />
     <input name="user" type="text" label="me">
     <br />
     <label for="Hello">Password</label>
     <br />
     <input id="pass" label="Hello" type="password" onclick="javascript:gginput();">
     <br />
     <br />
     <input name="submit" type="submit" value="Log in">
     or <a href="#" id="reg" >Register </a>
  </form>
   </div>
   <div id="blurb">
  <p id="blurbtxt">Welcome message here!</p>
   </div>
</div>
