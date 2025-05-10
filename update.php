<?php
//Connect to the database server.
$link = mysqli_connect("localhost", "root", "") or die(mysqli_connect_error());

//Select the database.
mysqli_select_db($link, "latihan") or die(mysqli_error($link));

$idURL = $_GET['id'];


//SQL query
$query = "SELECT * FROM person WHERE Person_ID = '$idURL'"
	or die(mysqli_connect_error());
	
//Execute the query (the recordset $rs contains the result)
$result = mysqli_query($link, $query);

$row = mysqli_fetch_assoc($result);
    
	$pNama = $row["Person_name"];
	$pTel = $row["Person_telephone"];
	$pAdd = $row["Person_address"];
?>

<!DOCTYPE html>
<html>
<body>
<form method="post" action="myUpdate2.php">
Nama:
<input type ="text" name="nama" size="40" value="<?php echo $pNama; ?>">
<br>
Telefon:
<input type ="text" name="telefon" size="10" value="<?php echo $pTel; ?>">
<br>
Alamat: <input type="text" name = "alamat" value="<?php echo $pAdd; ?>">
<br>
<input type ="hidden" name="id2" value="<?php echo $idURL; ?>"> 
<input type ="submit" value="Ubah">
<input type ="reset" value="Batal">
<br>
</form>
<hr>