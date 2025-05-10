<!-- To display all information of database. -->
<!DOCTYPE html>
<html>
<body>
<?php
//Connect to the database server.
$link = mysqli_connect("localhost", "root", "") or die(mysqli_connect_error());

//Select the database.
mysqli_select_db($link, "mydb") or die(mysqli_error($link));

//SQL query
$query = "SELECT * FROM user"
	or die(mysqli_connect_error());
	
//Execute the query (the recordset $rs contains the result)
$result = mysqli_query($link, $query);

if (mysqli_num_rows($result) > 0){
    // output data of each row
    while($row = mysqli_fetch_assoc($result)){
        $ID = $row["UserID"];
        $name = $row["UserName"];
        $age = $row["UserAge"];
        $gender = $row["UserGender"];
        $title = $row["UserTitle"];
        $hobby = $row["UserHobby"];
        $comment = $row["UserComment"];
?>	
	<table>
	<tr>
		<td><?php echo $name; ?></td>
		<td><?php echo $age; ?></td>
		<td><?php echo $gender; ?></td>
        <td><?php echo $title; ?></td>
        <td><?php echo $hobby; ?></td>
        <td><?php echo $comment; ?></td>
		<td>
			<a href="Lab7_Q3_update.php?id=<?php echo $ID; ?>">update</a> 
			<a href="Lab7_Q3_delete.php?id=<?php echo $ID; ?>">delete</a>
		</td>
	</tr>
	</table>
<?php
    }
} else {
    echo "0 results";

}
?>
<br><br>
<div align="center">[ <a href="index.php">Homepage</a> |
<a href="Lab7_Q3_insertData.php">Add new data</a> ] </div>
	